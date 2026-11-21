<?php

declare(strict_types=1);

namespace App\Modules\Opd\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Services\AllergyService;
use App\Modules\Emr\Services\ClinicalNoteService;
use App\Modules\Emr\Services\VitalSignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pre-Consult Nursing / Junior MO Triage & Vitals Station.
 * Captures vitals, cardiac risk factors, chief complaint, and allergies outside the
 * consultant's room so the consultant starts with a pre-populated chart.
 */
class PreConsultController extends Controller
{
    /** @var list<string> */
    public const CARDIAC_RISK_FACTORS = [
        'Hypertension (HTN)',
        'Diabetes Mellitus (DM)',
        'Current / Former Smoker',
        'Dyslipidemia',
        'Prior MI / PCI / Stent',
        'Prior CABG',
        'Family History of Premature CAD',
        'Chronic Kidney Disease (CKD)',
    ];

    public function __construct(
        private readonly VitalSignService $vitalSignService,
        private readonly ClinicalNoteService $clinicalNoteService,
        private readonly AllergyService $allergyService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->can('emr.vitals.record') && $user->hasAnyRole(['nurse', 'medical_officer', 'medical_superintendent']),
            403,
            'Pre-consult assessment station is restricted to OPD Nurses and Medical Officers.'
        );

        $todayPkt = Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->toDateString();

        $queue = Appointment::query()
            ->with([
                'patient.activeAllergies',
                'department',
                'clinician.user',
                'encounter.vitalSigns',
            ])
            ->whereDate('appointment_date', $todayPkt)
            ->whereIn('status', [Appointment::STATUS_CHECKED_IN, Appointment::STATUS_IN_CONSULT])
            ->whereNotNull('encounter_id')
            ->orderBy('token_number')
            ->get();

        return view('modules.opd.pre-consult', [
            'queue' => $queue,
            'riskFactors' => self::CARDIAC_RISK_FACTORS,
            'todayDate' => $todayPkt,
        ]);
    }

    public function store(Request $request, Encounter $encounter): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('emr.vitals.record'), 403);

        $validated = $request->validate([
            'systolic_bp' => ['required', 'integer', 'between:40,300'],
            'diastolic_bp' => ['required', 'integer', 'between:20,200'],
            'heart_rate' => ['required', 'integer', 'between:20,260'],
            'spo2_percent' => ['required', 'integer', 'between:40,100'],
            'weight_kg' => ['nullable', 'numeric', 'between:1,350'],
            'blood_glucose_mg_dl' => ['nullable', 'integer', 'between:20,800'],
            'rhythm_note' => ['nullable', 'string', 'max:100'],
            'chief_complaint' => ['required', 'string', 'min:2', 'max:255'],
            'risk_factors' => ['nullable', 'array'],
            'risk_factors.*' => ['string', 'max:100'],
            'new_allergy_substance' => ['nullable', 'string', 'max:120'],
            'new_allergy_category' => ['nullable', Rule::in(['medication', 'contrast_dye', 'food', 'environmental', 'other'])],
            'new_allergy_severity' => ['nullable', Rule::in(['mild', 'moderate', 'severe', 'life_threatening'])],
        ]);

        $vital = DB::transaction(function () use ($encounter, $validated, $user) {
            $vital = $this->vitalSignService->record($encounter, [
                'systolic_bp' => (int) $validated['systolic_bp'],
                'diastolic_bp' => (int) $validated['diastolic_bp'],
                'heart_rate' => (int) $validated['heart_rate'],
                'spo2_percent' => (int) $validated['spo2_percent'],
                'weight_kg' => $validated['weight_kg'] ?? null,
                'blood_glucose_mg_dl' => $validated['blood_glucose_mg_dl'] ?? null,
                'rhythm_note' => $validated['rhythm_note'] ?? null,
            ], $user);

            $riskList = array_values(array_filter((array) ($validated['risk_factors'] ?? [])));

            $encounter->chief_complaint = trim((string) $validated['chief_complaint']);
            $encounter->save();

            // Record concise nursing/MO pre-assessment draft note with risk factors for the consultant
            if ($user->can('emr.note.create')) {
                $this->clinicalNoteService->createDraft($encounter, [
                    'note_type' => 'nursing_note',
                    'chief_complaint' => trim((string) $validated['chief_complaint']),
                    'past_medical_history' => $riskList !== [] ? 'Cardiac Risk Factors: '.implode(', ', $riskList) : null,
                ], $user);
            }

            if (! empty($validated['new_allergy_substance'])) {
                $this->allergyService->record($encounter->patient, [
                    'substance' => trim((string) $validated['new_allergy_substance']),
                    'category' => (string) ($validated['new_allergy_category'] ?? 'medication'),
                    'severity' => (string) ($validated['new_allergy_severity'] ?? 'severe'),
                    'reaction' => 'Reported during OPD pre-consult triage',
                ], $user);
            }

            $this->auditLogger->log(
                action: 'opd.pre_consult.completed',
                actor: $user,
                subject: $encounter,
                patientId: $encounter->patient_id,
                newValues: [
                    'bp' => "{$vital->systolic_bp}/{$vital->diastolic_bp}",
                    'hr' => $vital->heart_rate,
                    'spo2' => $vital->spo2_percent,
                    'risk_factors' => $riskList,
                    'is_abnormal' => $vital->is_abnormal,
                ],
            );

            return $vital;
        });

        $msg = "Pre-consult vitals & risk factors saved for {$encounter->patient->full_name} — ready for Consultant.";
        if ($vital->is_abnormal) {
            $msg .= ' ABNORMAL VITALS FLAGGED: '.implode(', ', $vital->abnormal_flags ?? []).'.';
        }

        return redirect()
            ->route('opd.pre-consult.index')
            ->with($vital->is_abnormal ? 'warning' : 'status', $msg);
    }
}
