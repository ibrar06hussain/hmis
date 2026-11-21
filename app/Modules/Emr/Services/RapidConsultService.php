<?php

declare(strict_types=1);

namespace App\Modules\Emr\Services;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\ClinicalOrderSet;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Models\LabTestCatalog;
use App\Modules\Lis\Services\LabOrderService;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Pharmacy\Models\Medication;
use App\Modules\Pharmacy\Models\Prescription;
use App\Modules\Pharmacy\Services\PharmacyService;
use App\Modules\Ris\Models\ImagingCatalog;
use App\Modules\Ris\Services\ImagingOrderService;
use App\Modules\Terminology\Models\TerminologyCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * High-Velocity 60-Second Cardiac OPD Consultation Service.
 *
 * Consolidates vitals, structured cardiology note (draft or signed), ICD-10/11 diagnoses,
 * multi-drug prescription (with active allergy safety guard), lab panels, imaging orders,
 * and encounter completion into a single atomic database transaction.
 */
final class RapidConsultService
{
    public function __construct(
        private readonly VitalSignService $vitalSignService,
        private readonly ClinicalNoteService $clinicalNoteService,
        private readonly EncounterDiagnosisService $diagnosisService,
        private readonly PharmacyService $pharmacyService,
        private readonly LabOrderService $labOrderService,
        private readonly ImagingOrderService $imagingOrderService,
        private readonly AppointmentService $appointmentService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Load available hospital protocols + clinician's personal favorite order sets,
     * pre-resolved to database IDs for instant 1-click UI population.
     *
     * @return list<array<string, mixed>>
     */
    public function resolvedOrderSetsForUser(User $user): array
    {
        $sets = ClinicalOrderSet::query()
            ->where('is_active', true)
            ->where(function ($q) use ($user): void {
                $q->where('is_shared', true)->orWhere('owner_user_id', $user->id);
            })
            ->orderByRaw('CASE WHEN owner_user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->orderBy('name')
            ->get();

        $medsByCode = Medication::query()->where('is_active', true)->get()->keyBy('code');
        $termsByCode = TerminologyCode::query()->where('is_active', true)->get()->keyBy('code');
        $labsByCode = LabTestCatalog::query()->where('is_active', true)->get()->keyBy('code');
        $imagingByCode = ImagingCatalog::query()->where('is_active', true)->get()->keyBy('code');

        return $sets->map(function (ClinicalOrderSet $set) use ($medsByCode, $termsByCode, $labsByCode, $imagingByCode): array {
            $resolvedMeds = [];
            foreach ((array) ($set->medications ?? []) as $row) {
                $med = $medsByCode->get((string) ($row['medication_code'] ?? ''));
                if ($med === null) {
                    continue;
                }
                $resolvedMeds[] = [
                    'medication_id' => $med->id,
                    'label' => "{$med->generic_name} {$med->strength}",
                    'dose' => (string) ($row['dose'] ?? '1 tab'),
                    'route' => (string) ($row['route'] ?? 'PO'),
                    'frequency' => (string) ($row['frequency'] ?? 'OD'),
                    'duration_days' => (int) ($row['duration_days'] ?? 14),
                    'quantity_prescribed' => (int) ($row['quantity'] ?? 14),
                    'meal_timing' => (string) ($row['meal_timing'] ?? 'after_meal'),
                    'instructions' => (string) ($row['instructions'] ?? ''),
                ];
            }

            $diagnosisIds = collect((array) ($set->diagnosis_codes ?? []))
                ->map(fn ($code) => $termsByCode->get((string) $code)?->id)
                ->filter()
                ->values()
                ->all();

            $labIds = collect((array) ($set->lab_test_codes ?? []))
                ->map(fn ($code) => $labsByCode->get((string) $code)?->id)
                ->filter()
                ->values()
                ->all();

            $imagingIds = collect((array) ($set->imaging_codes ?? []))
                ->map(fn ($code) => $imagingByCode->get((string) $code)?->id)
                ->filter()
                ->values()
                ->all();

            return [
                'id' => $set->id,
                'code' => $set->code,
                'name' => $set->name,
                'category' => $set->category,
                'is_personal' => $set->owner_user_id !== null,
                'description' => $set->description,
                'default_note_type' => $set->default_note_type,
                'default_nyha_class' => $set->default_nyha_class,
                'default_chest_pain_type' => $set->default_chest_pain_type,
                'default_exam_text' => $set->default_exam_text,
                'default_assessment_text' => $set->default_assessment_text,
                'default_plan_text' => $set->default_plan_text,
                'default_follow_up' => $set->default_follow_up,
                'diagnosis_ids' => $diagnosisIds,
                'medications' => $resolvedMeds,
                'lab_test_ids' => $labIds,
                'imaging_catalog_ids' => $imagingIds,
            ];
        })->values()->all();
    }

    /**
     * Build a copy-forward snapshot from the patient's most recent prior encounter
     * so follow-up visits take 1 click instead of retyping history & chronic medications.
     *
     * @return array<string, mixed>|null
     */
    public function copyForwardSnapshot(Encounter $currentEncounter): ?array
    {
        $previousEncounter = Encounter::query()
            ->with([
                'clinicalNotes' => fn ($q) => $q->whereIn('status', [ClinicalNote::STATUS_SIGNED, ClinicalNote::STATUS_DRAFT])->orderByDesc('created_at')->orderByDesc('id'),
                'diagnoses',
                'prescriptions.items.medication',
            ])
            ->where('patient_id', $currentEncounter->patient_id)
            ->where('id', '!=', $currentEncounter->id)
            ->orderByDesc('started_at')
            ->first();

        if ($previousEncounter === null) {
            return null;
        }

        $lastNote = $previousEncounter->clinicalNotes->first();
        $lastRxItems = $previousEncounter->prescriptions
            ->flatMap(fn ($rx) => $rx->items)
            ->filter(fn ($item) => $item->medication !== null && $item->medication->is_active)
            ->map(fn ($item) => [
                'medication_id' => $item->medication_id,
                'label' => "{$item->medication->generic_name} {$item->medication->strength}",
                'dose' => $item->dose,
                'route' => $item->route,
                'frequency' => $item->frequency,
                'duration_days' => $item->duration_days,
                'quantity_prescribed' => $item->quantity_prescribed,
                'meal_timing' => $item->meal_timing ?? 'after_meal',
                'instructions' => $item->instructions ?? '',
            ])
            ->values()
            ->all();

        return [
            'encounter_number' => $previousEncounter->encounter_number,
            'encounter_date' => $previousEncounter->started_at?->setTimezone(config('hmis.display_timezone', 'Asia/Karachi'))->format('d M Y'),
            'past_medical_history' => $lastNote?->past_medical_history ?? '',
            'medication_history' => $lastNote?->medication_history ?? '',
            'nyha_class' => $lastNote?->structured_payload['nyha_class'] ?? '',
            'chest_pain_type' => $lastNote?->structured_payload['chest_pain_type'] ?? '',
            'assessment_and_diagnosis' => $lastNote?->assessment_and_diagnosis ?? '',
            'treatment_plan' => $lastNote?->treatment_plan ?? '',
            'follow_up_instructions' => $lastNote?->follow_up_instructions ?? '',
            'diagnosis_ids' => $previousEncounter->diagnoses->pluck('terminology_code_id')->filter()->unique()->values()->all(),
            'medications' => $lastRxItems,
        ];
    }

    /**
     * Find the next checked-in waiting OPD appointment for today in the same department.
     */
    public function nextWaitingAppointment(Encounter $currentEncounter): ?Appointment
    {
        $todayPkt = Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->toDateString();

        return Appointment::query()
            ->with(['patient', 'encounter'])
            ->whereDate('appointment_date', $todayPkt)
            ->where('department_id', $currentEncounter->department_id)
            ->where('status', Appointment::STATUS_CHECKED_IN)
            ->where(function ($q) use ($currentEncounter): void {
                $q->whereNull('encounter_id')->orWhere('encounter_id', '!=', $currentEncounter->id);
            })
            ->orderBy('token_number')
            ->first();
    }

    /**
     * Atomically commit a complete 60-second consultation in a single transaction.
     *
     * @param  array<string, mixed>  $payload
     * @return array{note: ClinicalNote, prescription: ?Prescription, contrast_warnings: list<string>, counts: array<string, int>}
     */
    public function commit(Encounter $encounter, array $payload, User $actor): array
    {
        return DB::transaction(function () use ($encounter, $payload, $actor): array {
            $counts = [
                'vitals' => 0,
                'diagnoses' => 0,
                'medications' => 0,
                'lab_tests' => 0,
                'imaging_studies' => 0,
            ];
            $contrastWarnings = [];

            // 1. Optional inline vitals (only if consultant entered any vital measurement)
            $vitalInput = array_filter([
                'systolic_bp' => $payload['systolic_bp'] ?? null,
                'diastolic_bp' => $payload['diastolic_bp'] ?? null,
                'heart_rate' => $payload['heart_rate'] ?? null,
                'spo2_percent' => $payload['spo2_percent'] ?? null,
                'weight_kg' => $payload['weight_kg'] ?? null,
                'rhythm_note' => $payload['rhythm_note'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');

            if ($vitalInput !== [] && $actor->can('recordVitals', $encounter)) {
                $this->vitalSignService->record($encounter, $vitalInput, $actor);
                $counts['vitals'] = 1;
            }

            // 2. Structured Clinical Note (Draft or Immediately Signed if Consultant has emr.note.sign)
            $noteData = [
                'note_type' => (string) ($payload['note_type'] ?? 'cardiology_eval'),
                'chief_complaint' => (string) $payload['chief_complaint'],
                'history_of_present_illness' => $payload['history_of_present_illness'] ?? null,
                'past_medical_history' => $payload['past_medical_history'] ?? null,
                'medication_history' => $payload['medication_history'] ?? null,
                'physical_examination' => $payload['physical_examination'] ?? null,
                'assessment_and_diagnosis' => $payload['assessment_and_diagnosis'] ?? null,
                'treatment_plan' => $payload['treatment_plan'] ?? null,
                'follow_up_instructions' => $payload['follow_up_instructions'] ?? null,
                'nyha_class' => $payload['nyha_class'] ?? null,
                'chest_pain_type' => $payload['chest_pain_type'] ?? null,
                'ecg_summary' => $payload['ecg_summary'] ?? null,
            ];

            $note = $this->clinicalNoteService->createDraft($encounter, $noteData, $actor);

            $signNow = (bool) ($payload['sign_now'] ?? false);
            if ($signNow && $actor->can('signNote', $encounter)) {
                $note = $this->clinicalNoteService->sign($note, $actor);
            }

            // 3. Coded ICD-10 / ICD-11 Diagnoses (avoid duplicates already on this encounter)
            $existingTermIds = $encounter->diagnoses()->pluck('terminology_code_id')->all();
            $diagnosisIds = array_values(array_unique(array_map('intval', (array) ($payload['diagnosis_ids'] ?? []))));
            $rankIndex = 0;

            if ($diagnosisIds !== [] && $actor->can('recordDiagnosis', $encounter)) {
                foreach ($diagnosisIds as $termId) {
                    if ($termId <= 0 || in_array($termId, $existingTermIds, true)) {
                        continue;
                    }
                    $this->diagnosisService->record($encounter, [
                        'terminology_code_id' => $termId,
                        'diagnosis_rank' => $rankIndex === 0 && $existingTermIds === [] ? 'primary' : 'secondary',
                        'clinical_status' => 'confirmed',
                    ], $actor);
                    $rankIndex++;
                    $counts['diagnoses']++;
                }
            }

            // 4. Multi-Drug Batch Prescription (full active allergy guard enforced!)
            $medItems = collect((array) ($payload['medications'] ?? []))
                ->filter(fn ($row) => is_array($row) && ! empty($row['medication_id']))
                ->values()
                ->all();

            $prescription = null;
            if ($medItems !== [] && $actor->can('prescribe', Prescription::class)) {
                $prescription = $this->pharmacyService->prescribeBatch(
                    encounter: $encounter,
                    items: $medItems,
                    prescriber: $actor,
                    confirmAllergyOverride: (bool) ($payload['confirm_allergy_override'] ?? false),
                    overrideReason: $payload['allergy_override_reason'] ?? null,
                    clinicalNotes: $payload['follow_up_instructions'] ?? null,
                );
                $counts['medications'] = count($medItems);
            }

            // 5. Laboratory Panel Order
            $labIds = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['lab_test_ids'] ?? [])))));
            if ($labIds !== [] && $actor->can('create', LabOrder::class)) {
                $this->labOrderService->createOrder(
                    encounter: $encounter,
                    testIds: $labIds,
                    priority: (string) ($payload['lab_priority'] ?? 'routine'),
                    clinicalNotes: $payload['chief_complaint'] ?? null,
                    actor: $actor,
                );
                $counts['lab_tests'] = count($labIds);
            }

            // 6. Diagnostic Imaging / ECG / Echo Orders
            $imagingIds = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['imaging_catalog_ids'] ?? [])))));
            if ($imagingIds !== [] && $actor->can('create', ImagingStudy::class)) {
                foreach ($imagingIds as $catalogId) {
                    $result = $this->imagingOrderService->order(
                        encounter: $encounter,
                        catalogId: $catalogId,
                        priority: (string) ($payload['imaging_priority'] ?? 'routine'),
                        clinicalIndication: $payload['chief_complaint'] ?? null,
                        actor: $actor,
                    );
                    if ($result['contrast_allergy_warning']) {
                        $contrastWarnings[] = $result['study']->procedure_name;
                    }
                    $counts['imaging_studies']++;
                }
            }

            // 7. Complete Encounter & Linked OPD Appointment if requested
            if ((bool) ($payload['complete_encounter'] ?? false) && $actor->can('complete', $encounter)) {
                $this->appointmentService->completeConsultation($encounter, $actor);
            }

            $this->auditLogger->log(
                action: 'emr.rapid_consult.submitted',
                actor: $actor,
                subject: $encounter,
                patientId: $encounter->patient_id,
                newValues: array_merge($counts, [
                    'note_status' => $note->status,
                    'encounter_completed' => (bool) ($payload['complete_encounter'] ?? false),
                ]),
            );

            return [
                'note' => $note,
                'prescription' => $prescription,
                'contrast_warnings' => $contrastWarnings,
                'counts' => $counts,
            ];
        });
    }

    /**
     * Translate clinical dosing frequency into visual Urdu/English Morning-Noon-Night schedule
     * for low-literacy patient comprehension in Gilgit-Baltistan.
     *
     * @return array{morning: string, noon: string, night: string, urdu_label: string}
     */
    public static function scheduleMatrix(string $frequency, string $dose = '1'): array
    {
        $freq = strtoupper(trim($frequency));
        $unit = preg_match('/^([0-9\/\.]+)/', trim($dose), $m) ? $m[1] : '1';

        return match ($freq) {
            'OD' => ['morning' => $unit, 'noon' => '—', 'night' => '—', 'urdu_label' => 'دن میں ایک بار (صبح)'],
            'HS' => ['morning' => '—', 'noon' => '—', 'night' => $unit, 'urdu_label' => 'رات سوتے وقت'],
            'BD' => ['morning' => $unit, 'noon' => '—', 'night' => $unit, 'urdu_label' => 'صبح اور شام'],
            'TDS' => ['morning' => $unit, 'noon' => $unit, 'night' => $unit, 'urdu_label' => 'صبح، دوپہر، رات'],
            'QID' => ['morning' => $unit, 'noon' => $unit, 'night' => "{$unit}+{$unit}", 'urdu_label' => 'دن میں چار بار (ہر 6 گھنٹے)'],
            'PRN' => ['morning' => 'ضرورت', 'noon' => 'ضرورت', 'night' => 'ضرورت', 'urdu_label' => 'صرف ضرورت / درد ہونے پر'],
            'STAT' => ['morning' => 'فوری', 'noon' => '—', 'night' => '—', 'urdu_label' => 'فوری ایک خوراک'],
            default => ['morning' => $unit, 'noon' => '—', 'night' => '—', 'urdu_label' => $freq],
        };
    }

    public static function mealTimingUrdu(?string $mealTiming): string
    {
        return match ($mealTiming) {
            'before_meal' => 'کھانے سے پہلے (Before meal)',
            'after_meal' => 'کھانے کے بعد (After meal)',
            'with_meal' => 'کھانے کے ساتھ (With meal)',
            'empty_stomach' => 'نہار منہ (Empty stomach)',
            default => 'کھانے سے قطع نظر (Anytime)',
        };
    }
}
