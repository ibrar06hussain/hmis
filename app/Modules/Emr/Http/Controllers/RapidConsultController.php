<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Services\RapidConsultService;
use App\Modules\Lis\Models\LabTestCatalog;
use App\Modules\Pharmacy\Exceptions\MedicationAllergyConflictException;
use App\Modules\Pharmacy\Models\Medication;
use App\Modules\Ris\Models\ImagingCatalog;
use App\Modules\Terminology\Services\TerminologySearchService;
use App\Support\OfflineQrSvg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RapidConsultController extends Controller
{
    public function __construct(
        private readonly RapidConsultService $rapidConsultService,
        private readonly TerminologySearchService $terminologySearch,
    ) {}

    public function show(Request $request, Encounter $encounter): View
    {
        Gate::authorize('view', $encounter);

        /** @var User $user */
        $user = $request->user();

        $encounter->load([
            'patient.activeAllergies',
            'department',
            'attendingStaff.user',
            'appointment',
            'vitalSigns.recordedBy',
            'clinicalNotes.authorUser',
            'diagnoses',
            'prescriptions.items.medication',
            'labOrders.items.test',
            'imagingStudies',
        ]);

        return view('modules.emr.rapid-consult', [
            'encounter' => $encounter,
            'patient' => $encounter->patient,
            'latestVital' => $encounter->vitalSigns->first(),
            'orderSets' => $this->rapidConsultService->resolvedOrderSetsForUser($user),
            'copyForward' => $this->rapidConsultService->copyForwardSnapshot($encounter),
            'nextWaiting' => $this->rapidConsultService->nextWaitingAppointment($encounter),
            'terminologyCodes' => $this->terminologySearch->search(null, null, 60),
            'labTests' => LabTestCatalog::query()->where('is_active', true)->orderBy('section')->orderBy('name')->get(),
            'imagingCatalog' => ImagingCatalog::query()->where('is_active', true)->orderBy('modality')->orderBy('name')->get(),
            'medications' => Medication::query()->where('is_active', true)->orderBy('generic_name')->get(),
        ]);
    }

    public function store(Request $request, Encounter $encounter): RedirectResponse
    {
        Gate::authorize('createNote', $encounter);

        $validated = $request->validate([
            'note_type' => ['nullable', Rule::in(['cardiology_eval', 'opd_consult', 'follow_up', 'er_triage', 'nursing_note'])],
            'chief_complaint' => ['required', 'string', 'min:2', 'max:255'],
            'history_of_present_illness' => ['nullable', 'string', 'max:3000'],
            'past_medical_history' => ['nullable', 'string', 'max:2000'],
            'medication_history' => ['nullable', 'string', 'max:2000'],
            'physical_examination' => ['nullable', 'string', 'max:3000'],
            'assessment_and_diagnosis' => ['nullable', 'string', 'max:2000'],
            'treatment_plan' => ['nullable', 'string', 'max:3000'],
            'follow_up_instructions' => ['nullable', 'string', 'max:500'],
            'nyha_class' => ['nullable', Rule::in(['I', 'II', 'III', 'IV'])],
            'chest_pain_type' => ['nullable', Rule::in(['none', 'typical_angina', 'atypical_angina', 'non_cardiac', 'acute_acs'])],
            'ecg_summary' => ['nullable', 'string', 'max:255'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,300'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,200'],
            'heart_rate' => ['nullable', 'integer', 'between:20,260'],
            'spo2_percent' => ['nullable', 'integer', 'between:40,100'],
            'weight_kg' => ['nullable', 'numeric', 'between:1,350'],
            'rhythm_note' => ['nullable', 'string', 'max:100'],
            'diagnosis_ids' => ['nullable', 'array'],
            'diagnosis_ids.*' => ['integer', 'exists:terminology_codes,id'],
            'medications' => ['nullable', 'array'],
            'medications.*.medication_id' => ['nullable', 'integer', 'exists:medications,id'],
            'medications.*.dose' => ['nullable', 'string', 'max:60'],
            'medications.*.route' => ['nullable', Rule::in(['PO', 'SL', 'IV', 'SC', 'IM'])],
            'medications.*.frequency' => ['nullable', Rule::in(['OD', 'BD', 'TDS', 'QID', 'HS', 'STAT', 'PRN'])],
            'medications.*.duration_days' => ['nullable', 'integer', 'min:1', 'max:180'],
            'medications.*.quantity_prescribed' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'medications.*.meal_timing' => ['nullable', Rule::in(['before_meal', 'after_meal', 'with_meal', 'empty_stomach', 'anytime'])],
            'medications.*.instructions' => ['nullable', 'string', 'max:255'],
            'confirm_allergy_override' => ['nullable', 'boolean'],
            'allergy_override_reason' => ['nullable', 'string', 'max:255'],
            'lab_test_ids' => ['nullable', 'array'],
            'lab_test_ids.*' => ['integer', 'exists:lab_test_catalog,id'],
            'lab_priority' => ['nullable', Rule::in(['routine', 'urgent', 'stat'])],
            'imaging_catalog_ids' => ['nullable', 'array'],
            'imaging_catalog_ids.*' => ['integer', 'exists:imaging_catalog,id'],
            'imaging_priority' => ['nullable', Rule::in(['routine', 'urgent', 'stat'])],
            'sign_now' => ['nullable', 'boolean'],
            'complete_encounter' => ['nullable', 'boolean'],
            'post_action' => ['nullable', Rule::in(['print_rx', 'workspace', 'next_patient'])],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $result = $this->rapidConsultService->commit($encounter, $validated, $user);
        } catch (MedicationAllergyConflictException $exception) {
            return back()
                ->withInput()
                ->with('allergy_conflict', $exception->conflictingAllergies)
                ->withErrors(['medications' => $exception->getMessage()]);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['rapid' => $exception->getMessage()]);
        }

        $summary = sprintf(
            'Rapid consult saved (%s): %d dx, %d med(s), %d lab(s), %d imaging.',
            strtoupper($result['note']->status),
            $result['counts']['diagnoses'],
            $result['counts']['medications'],
            $result['counts']['lab_tests'],
            $result['counts']['imaging_studies'],
        );

        $flashKey = $result['contrast_warnings'] !== [] ? 'warning' : 'status';
        if ($result['contrast_warnings'] !== []) {
            $summary .= ' CONTRAST ALLERGY ALERT on: '.implode(', ', $result['contrast_warnings']).'.';
        }

        $postAction = (string) ($validated['post_action'] ?? 'print_rx');

        if ($postAction === 'print_rx') {
            return redirect()
                ->route('encounters.prescription.print', $encounter)
                ->with($flashKey, $summary);
        }

        return redirect()
            ->route('encounters.show', $encounter)
            ->with($flashKey, $summary);
    }

    public function printPrescription(Encounter $encounter): View
    {
        Gate::authorize('view', $encounter);

        $encounter->load([
            'patient.activeAllergies',
            'department',
            'attendingStaff.user',
            'vitalSigns',
            'clinicalNotes' => fn ($q) => $q->orderByDesc('created_at'),
            'diagnoses',
            'prescriptions.items.medication',
            'labOrders.items.test',
            'imagingStudies',
        ]);

        $latestNote = $encounter->clinicalNotes->first();
        $nextWaiting = $this->rapidConsultService->nextWaitingAppointment($encounter);

        return view('modules.emr.prescription-print', [
            'encounter' => $encounter,
            'patient' => $encounter->patient,
            'latestVital' => $encounter->vitalSigns->first(),
            'latestNote' => $latestNote,
            'nextWaiting' => $nextWaiting,
            'qrSvg' => OfflineQrSvg::render(
                sprintf('CHG-RX|%s|%s', $encounter->encounter_number, $encounter->patient->mrn),
                96,
                1
            ),
        ]);
    }
}
