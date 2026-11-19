<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Nursing\Models\NursingMarRecord;
use App\Modules\Nursing\Models\NursingNote;
use App\Modules\Nursing\Services\NursingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NursingStationController extends Controller
{
    public function __construct(
        private readonly NursingService $nursingService,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewWorkbench', NursingMarRecord::class);

        $activeAdmissions = IpdAdmission::query()
            ->with(['patient.activeAllergies', 'ward', 'bed', 'encounter'])
            ->where('status', IpdAdmission::STATUS_ADMITTED)
            ->orderBy('ward_id')
            ->get();

        $activeEncounters = Encounter::query()
            ->with(['patient', 'department'])
            ->whereIn('status', ['arrived', 'in_progress'])
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        $recentMar = NursingMarRecord::query()
            ->with(['patient', 'encounter', 'administeredBy'])
            ->orderByDesc('administered_at')
            ->limit(30)
            ->get();

        $recentNotes = NursingNote::query()
            ->with(['patient', 'encounter', 'recordedBy'])
            ->orderByDesc('recorded_at')
            ->limit(25)
            ->get();

        return view('modules.nursing.index', [
            'activeAdmissions' => $activeAdmissions,
            'activeEncounters' => $activeEncounters,
            'recentMar' => $recentMar,
            'recentNotes' => $recentNotes,
        ]);
    }

    public function storeMar(Request $request): RedirectResponse
    {
        Gate::authorize('record', NursingMarRecord::class);

        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'medication_name' => ['required', 'string', 'min:2', 'max:160'],
            'dose_given' => ['required', 'string', 'max:64'],
            'route' => ['required', Rule::in(['PO', 'IV', 'SL', 'SC', 'IM', 'Nebulized'])],
            'status' => ['required', Rule::in(['administered', 'held', 'refused', 'missed'])],
            'clinical_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $encounter = Encounter::query()->with('ipdAdmission')->findOrFail((int) $validated['encounter_id']);

        /** @var User $user */
        $user = $request->user();

        try {
            $record = $this->nursingService->recordMar($encounter, $validated, $user);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['clinical_reason' => $e->getMessage()]);
        }

        return redirect()
            ->route('nursing.index')
            ->with('status', "MAR recorded: {$record->medication_name} ({$record->dose_given} {$record->route}) — ".strtoupper($record->status).'.');
    }

    public function storeNote(Request $request): RedirectResponse
    {
        Gate::authorize('record', NursingMarRecord::class);

        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'shift' => ['required', Rule::in(['morning', 'evening', 'night'])],
            'note_category' => ['required', Rule::in(['assessment', 'shift_handover', 'fluid_balance', 'cardiac_monitoring'])],
            'intake_ml' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'output_ml' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'content' => ['required', 'string', 'min:5', 'max:3000'],
        ]);

        $encounter = Encounter::query()->with('ipdAdmission')->findOrFail((int) $validated['encounter_id']);

        /** @var User $user */
        $user = $request->user();
        $this->nursingService->recordNote($encounter, $validated, $user);

        return redirect()
            ->route('nursing.index')
            ->with('status', 'Nursing shift note / fluid balance recorded.');
    }
}
