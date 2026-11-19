<?php

declare(strict_types=1);

namespace App\Modules\Patient\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Iam\Services\BreakGlassService;
use App\Modules\Pacs\Contracts\PacsGatewayInterface;
use App\Modules\Patient\Exceptions\DuplicatePatientException;
use App\Modules\Patient\Http\Requests\BreakGlassAccessRequest;
use App\Modules\Patient\Http\Requests\MergePatientRequest;
use App\Modules\Patient\Http\Requests\StorePatientRequest;
use App\Modules\Patient\Http\Requests\UpdatePatientRequest;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\PatientMergeService;
use App\Modules\Patient\Services\PatientRegistrationService;
use App\Modules\Patient\Services\PatientSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientSearchService $searchService,
        private readonly PatientRegistrationService $registrationService,
        private readonly PatientMergeService $mergeService,
        private readonly BreakGlassService $breakGlassService,
        private readonly PacsGatewayInterface $pacsGateway,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Patient::class);

        $search = $request->string('q')->toString();
        $status = $request->string('status', 'active')->toString();

        $patients = $this->searchService->search($search, $status, 15);

        return view('modules.patient.index', [
            'patients' => $patients,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Patient::class);

        $mode = $request->string('mode', Patient::REG_STANDARD)->toString();
        if (! in_array($mode, [Patient::REG_STANDARD, Patient::REG_EMERGENCY], true)) {
            $mode = Patient::REG_STANDARD;
        }

        return view('modules.patient.create', [
            'mode' => $mode,
            'duplicateCandidates' => session('duplicate_candidates', collect()),
            'duplicateMessage' => session('duplicate_message'),
        ]);
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $patient = $this->registrationService->register(
                data: $request->validated(),
                actor: $user,
                confirmDistinct: $request->boolean('confirm_distinct'),
            );
        } catch (DuplicatePatientException $exception) {
            return back()
                ->withInput()
                ->with('duplicate_candidates', $exception->candidates)
                ->with('duplicate_message', $exception->getMessage());
        }

        return redirect()
            ->route('patients.show', $patient)
            ->with('status', "Patient registered successfully with permanent MRN {$patient->mrn}.");
    }

    public function show(Request $request, Patient $patient): View|RedirectResponse
    {
        Gate::authorize('view', $patient);

        // Redirect merged MRNs transparently to canonical surviving patient with notice
        if ($patient->status === Patient::STATUS_MERGED && $patient->merged_into_patient_id !== null) {
            $canonical = $patient->canonicalPatient();

            return redirect()
                ->route('patients.show', $canonical)
                ->with('warning', "MRN {$patient->mrn} was merged into canonical record {$canonical->mrn}. Showing the surviving record.");
        }

        /** @var User $user */
        $user = $request->user();
        $canViewClinical = $user->can('viewClinicalProfile', $patient);
        $canBreakGlass = $user->can('requestBreakGlass', $patient);
        $hasActiveBreakGlass = $this->breakGlassService->hasActiveGrant($user, $patient);

        $patient->load([
            'identifiers',
            'contacts',
            'registeredFacility',
            'registeredBy',
            'mergesAsTarget.sourcePatient',
            'mergesAsTarget.mergedBy',
        ]);

        if ($canViewClinical) {
            $patient->load([
                'activeAllergies',
                'allergies.recordedBy',
                'encounters.department',
                'encounters.attendingStaff.user',
                'vitalSigns.recordedBy',
                'clinicalNotes.authorUser',
                'clinicalNotes.signedBy',
                'diagnoses.recordedBy',
                'ipdAdmissions.ward',
                'ipdAdmissions.bed',
                'appointments.department',
                'labOrders.items.test',
                'labOrders.items.verifiedBy',
                'labOrders.items.criticalAcknowledgedBy',
                'imagingStudies',
            ]);
        }

        return view('modules.patient.show', [
            'patient' => $patient,
            'canViewClinical' => $canViewClinical,
            'canBreakGlass' => $canBreakGlass,
            'hasActiveBreakGlass' => $hasActiveBreakGlass,
            'pacsGateway' => $this->pacsGateway,
            'activeTab' => $request->string('tab', 'overview')->toString(),
        ]);
    }

    public function edit(Patient $patient): View
    {
        Gate::authorize('update', $patient);

        return view('modules.patient.edit', [
            'patient' => $patient,
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('update', $patient);

        /** @var User $user */
        $user = $request->user();
        $this->registrationService->updateDemographics($patient, $request->validated(), $user);

        return redirect()
            ->route('patients.show', $patient)
            ->with('status', "Demographics updated for MRN {$patient->mrn}.");
    }

    public function card(Patient $patient): View
    {
        Gate::authorize('view', $patient);

        $patient->load(['registeredFacility', 'contacts']);

        return view('modules.patient.card', [
            'patient' => $patient,
        ]);
    }

    public function showMergeForm(Request $request): View
    {
        Gate::authorize('merge', Patient::class);

        $activePatients = Patient::query()
            ->active()
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('modules.patient.merge', [
            'patients' => $activePatients,
            'preselectedSourceId' => $request->integer('source_id') ?: null,
        ]);
    }

    public function merge(MergePatientRequest $request): RedirectResponse
    {
        Gate::authorize('merge', Patient::class);

        $validated = $request->validated();
        $source = Patient::query()->findOrFail((int) $validated['source_patient_id']);
        $target = Patient::query()->findOrFail((int) $validated['target_patient_id']);

        /** @var User $user */
        $user = $request->user();

        try {
            $merge = $this->mergeService->merge($source, $target, $user, (string) $validated['reason']);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['source_patient_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('patients.show', $target)
            ->with('status', "Merged {$source->mrn} into canonical MRN {$target->mrn}. Merge reference: {$merge->uuid}.");
    }

    public function breakGlass(BreakGlassAccessRequest $request, Patient $patient): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('iam.break_glass')) {
            abort(403, 'Not authorized for emergency break-glass access.');
        }

        $validated = $request->validated();
        $this->breakGlassService->grant(
            user: $user,
            patient: $patient,
            reasonCode: (string) $validated['reason_code'],
            justification: (string) $validated['justification'],
        );

        return redirect()
            ->route('patients.show', $patient)
            ->with('warning', 'Emergency Break-Glass clinical access granted and audited.');
    }
}
