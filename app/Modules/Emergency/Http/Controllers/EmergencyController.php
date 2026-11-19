<?php

declare(strict_types=1);

namespace App\Modules\Emergency\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Emergency\Models\EmergencyTriage;
use App\Modules\Emergency\Services\EmergencyTriageService;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmergencyController extends Controller
{
    public function __construct(
        private readonly EmergencyTriageService $triageService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EmergencyTriage::class);

        $filter = $request->string('disposition', 'pending')->toString();

        $triages = EmergencyTriage::query()
            ->with(['patient', 'encounter', 'triagedBy'])
            ->when($filter !== 'all', fn ($q) => $q->where('disposition', $filter))
            ->orderByDesc('stemi_alert')
            ->orderBy('triage_level')
            ->orderByDesc('triaged_at')
            ->limit(100)
            ->get();

        $activePatients = Patient::query()
            ->active()
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('modules.emergency.index', [
            'triages' => $triages,
            'activePatients' => $activePatients,
            'filter' => $filter,
            'preselectedPatientId' => $request->integer('patient_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', EmergencyTriage::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'triage_level' => ['required', 'integer', 'between:1,5'],
            'arrival_mode' => ['required', Rule::in(['walk_in', 'ambulance_1122', 'referral', 'police'])],
            'chest_pain_flag' => ['nullable', 'boolean'],
            'stemi_alert' => ['nullable', 'boolean'],
            'presenting_complaint' => ['required', 'string', 'min:3', 'max:255'],
            'triage_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $triage = $this->triageService->triage($validated, $user);
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['patient_id' => $exception->getMessage()]);
        }

        $msg = $triage->stemi_alert
            ? "CODE STEMI ALERT triaged for {$triage->patient->full_name} ({$triage->encounter->encounter_number})."
            : "Emergency triage recorded ({$triage->encounter->encounter_number}).";

        return redirect()
            ->route('emergency.index')
            ->with($triage->stemi_alert ? 'warning' : 'status', $msg);
    }

    public function updateDisposition(Request $request, EmergencyTriage $triage): RedirectResponse
    {
        Gate::authorize('updateDisposition', EmergencyTriage::class);

        $validated = $request->validate([
            'disposition' => ['required', Rule::in(['pending', 'admitted_ipd', 'admitted_ccu', 'cath_lab', 'discharged', 'referred', 'lama', 'expired'])],
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->triageService->updateDisposition($triage, (string) $validated['disposition'], $user);

        return back()->with('status', "ER disposition updated to '".str_replace('_', ' ', (string) $validated['disposition'])."'.");
    }
}
