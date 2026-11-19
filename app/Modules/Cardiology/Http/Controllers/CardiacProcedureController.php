<?php

declare(strict_types=1);

namespace App\Modules\Cardiology\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cardiology\Models\CardiacProcedure;
use App\Modules\Cardiology\Services\CardiacProcedureService;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Iam\Models\StaffProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CardiacProcedureController extends Controller
{
    public function __construct(
        private readonly CardiacProcedureService $procedureService,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewWorkbench', CardiacProcedure::class);

        $procedures = CardiacProcedure::query()
            ->with(['patient', 'encounter', 'leadOperator.user', 'signedBy'])
            ->orderByDesc('scheduled_at')
            ->limit(60)
            ->get();

        $activeEncounters = Encounter::query()
            ->with(['patient', 'department'])
            ->whereIn('status', ['arrived', 'in_progress'])
            ->orderByDesc('started_at')
            ->limit(60)
            ->get();

        $operators = StaffProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->role(['consultant', 'medical_officer']))
            ->get();

        return view('modules.cardiology.procedures', [
            'procedures' => $procedures,
            'activeEncounters' => $activeEncounters,
            'operators' => $operators,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('schedule', CardiacProcedure::class);

        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'category' => ['required', Rule::in(['cath_lab_pci', 'coronary_angiography', 'cardiac_surgery', 'pacemaker_icd', 'electrophysiology'])],
            'procedure_name' => ['required', 'string', 'min:3', 'max:180'],
            'theatre_or_lab' => ['required', 'string', 'max:80'],
            'lead_operator_staff_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'access_site' => ['nullable', Rule::in(['radial_right', 'radial_left', 'femoral', 'sternotomy', 'thoracotomy'])],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $encounter = Encounter::query()->with('patient')->findOrFail((int) $validated['encounter_id']);

        /** @var User $user */
        $user = $request->user();
        $result = $this->procedureService->schedule($encounter, $validated, $user);

        if ($result['contrast_allergy_warning']) {
            return redirect()
                ->route('cardiology.procedures.index')
                ->with('warning', "CONTRAST ALLERGY ALERT: Scheduled {$result['procedure']->procedure_number} ({$result['procedure']->procedure_name}), but patient has an active IV Contrast allergy!");
        }

        return redirect()
            ->route('cardiology.procedures.index')
            ->with('status', "Scheduled cardiac procedure {$result['procedure']->procedure_number}.");
    }

    public function complete(Request $request, CardiacProcedure $procedure): RedirectResponse
    {
        Gate::authorize('sign', CardiacProcedure::class);

        $validated = $request->validate([
            'who_checklist_completed' => ['nullable', 'boolean'],
            'access_site' => ['nullable', Rule::in(['radial_right', 'radial_left', 'femoral', 'sternotomy', 'thoracotomy'])],
            'contrast_volume_ml' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'fluoroscopy_minutes' => ['nullable', 'numeric', 'min:0', 'max:600'],
            'stent_summary' => ['nullable', 'string', 'max:2000'],
            'graft_summary' => ['nullable', 'string', 'max:2000'],
            'operative_findings' => ['required', 'string', 'min:10', 'max:5000'],
            'complications' => ['nullable', 'string', 'max:2000'],
            'post_op_orders' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $signed = $this->procedureService->completeAndSign($procedure, $validated, $user);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['who_checklist_completed' => $e->getMessage()]);
        }

        return redirect()
            ->route('cardiology.procedures.index')
            ->with('status', "Cardiac procedure {$signed->procedure_number} completed & digitally signed.");
    }
}
