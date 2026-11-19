<?php

declare(strict_types=1);

namespace App\Modules\Ipd\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\BedManagement\Services\BedManagementService;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Ipd\Services\IpdAdmissionService;
use App\Modules\Organization\Models\Bed;
use App\Modules\Organization\Models\Ward;
use App\Modules\Patient\Models\Patient;
use App\Support\OfflineQrSvg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IpdController extends Controller
{
    public function __construct(
        private readonly IpdAdmissionService $admissionService,
        private readonly BedManagementService $bedService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', IpdAdmission::class);

        $wards = Ward::query()
            ->with(['department', 'beds' => fn ($q) => $q->orderBy('bed_number')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $activeAdmissions = IpdAdmission::query()
            ->with(['patient', 'ward', 'bed', 'encounter', 'attendingStaff.user'])
            ->where('status', IpdAdmission::STATUS_ADMITTED)
            ->orderByDesc('admitted_at')
            ->get();

        $recentDischarges = IpdAdmission::query()
            ->with(['patient', 'ward', 'bed', 'encounter', 'attendingStaff.user', 'dischargedBy'])
            ->where('status', '!=', IpdAdmission::STATUS_ADMITTED)
            ->orderByDesc('discharged_at')
            ->limit(15)
            ->get();

        $availableBeds = Bed::query()
            ->with('ward')
            ->where('status', 'available')
            ->orderBy('ward_id')
            ->orderBy('bed_number')
            ->get();

        $activePatients = Patient::query()
            ->active()
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $clinicians = StaffProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->role(['consultant', 'medical_officer']))
            ->get();

        return view('modules.ipd.index', [
            'wards' => $wards,
            'activeAdmissions' => $activeAdmissions,
            'recentDischarges' => $recentDischarges,
            'availableBeds' => $availableBeds,
            'activePatients' => $activePatients,
            'clinicians' => $clinicians,
            'preselectedPatientId' => $request->integer('patient_id') ?: null,
        ]);
    }

    public function admit(Request $request): RedirectResponse
    {
        Gate::authorize('admit', IpdAdmission::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'attending_staff_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'admission_type' => ['required', Rule::in(['emergency', 'elective', 'transfer_in', 'cardiac_surgery'])],
            'admission_diagnosis' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $admission = $this->admissionService->admit($validated, $user);
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['bed_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('ipd.index')
            ->with('status', "Admitted {$admission->patient->full_name} ({$admission->admission_number}) to {$admission->ward->name} / Bed {$admission->bed->bed_number}.");
    }

    public function transferBed(Request $request, IpdAdmission $admission): RedirectResponse
    {
        Gate::authorize('transfer', $admission);

        $validated = $request->validate([
            'target_bed_id' => ['required', 'integer', 'exists:beds,id'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $targetBed = Bed::query()->findOrFail((int) $validated['target_bed_id']);

        try {
            $updated = $this->admissionService->transferBed($admission, $targetBed, $user, (string) $validated['reason']);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            return back()->withErrors(['target_bed_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('ipd.index')
            ->with('status', "Transferred {$updated->patient->full_name} to Bed {$updated->bed->bed_number}. Previous bed set to Cleaning.");
    }

    public function discharge(Request $request, IpdAdmission $admission): RedirectResponse
    {
        Gate::authorize('discharge', $admission);

        $validated = $request->validate([
            'discharge_status' => ['required', Rule::in(['discharged', 'transferred_out', 'lama', 'expired'])],
            'discharge_condition' => ['required', Rule::in(['stable', 'improved', 'unchanged', 'critical_referred', 'expired'])],
            'discharge_summary' => ['required', 'string', 'min:15', 'max:5000'],
            'follow_up_plan' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $discharged = $this->admissionService->discharge($admission, $validated, $user);
        } catch (\DomainException $exception) {
            return back()->withErrors(['admission' => $exception->getMessage()]);
        }

        return redirect()
            ->route('ipd.index')
            ->with('status', "Discharged {$discharged->patient->full_name} ({$discharged->admission_number}). Bed placed in Cleaning queue.");
    }

    public function markBedAvailable(Request $request, Bed $bed): RedirectResponse
    {
        Gate::authorize('manageBed', IpdAdmission::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->bedService->markAvailableAfterCleaning($bed, $user);
        } catch (\DomainException $exception) {
            return back()->withErrors(['bed' => $exception->getMessage()]);
        }

        return back()->with('status', "Bed {$bed->bed_number} marked Available.");
    }

    public function dischargeSummary(IpdAdmission $admission): View
    {
        Gate::authorize('viewSummary', $admission);

        $admission->load([
            'patient.allergies',
            'ward',
            'bed',
            'admittingDepartment',
            'attendingStaff.user',
            'dischargedBy',
            'encounter.diagnoses.terminologyCode',
            'encounter.prescriptions.items.medication',
            'encounter.vitalSigns',
        ]);

        return view('modules.ipd.discharge-summary', [
            'admission' => $admission,
            'patient' => $admission->patient,
            'diagnoses' => $admission->encounter?->diagnoses ?? collect(),
            'prescriptions' => $admission->encounter?->prescriptions ?? collect(),
            'latestVitals' => $admission->encounter?->vitalSigns?->first(),
            'qrSvg' => OfflineQrSvg::render(
                sprintf('CHG-DISCHARGE|%s|%s', $admission->admission_number, $admission->patient->mrn),
                96,
                1
            ),
        ]);
    }
}
