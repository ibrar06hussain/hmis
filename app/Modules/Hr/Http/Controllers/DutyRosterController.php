<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hr\Models\DutyRoster;
use App\Modules\Hr\Services\DutyRosterService;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Ward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DutyRosterController extends Controller
{
    public function __construct(
        private readonly DutyRosterService $rosterService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DutyRoster::class);

        $date = $request->string('date')->toString()
            ?: Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->toDateString();

        $rosters = DutyRoster::query()
            ->with(['staffProfile.user', 'department', 'ward'])
            ->whereDate('duty_date', $date)
            ->orderBy('shift')
            ->orderBy('department_id')
            ->get();

        return view('modules.hr.rosters', [
            'rosters' => $rosters,
            'selectedDate' => $date,
            'staffProfiles' => StaffProfile::query()->with(['user', 'department'])->where('is_active', true)->orderBy('designation')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'wards' => Ward::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', DutyRoster::class);

        $validated = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'duty_date' => ['required', 'date'],
            'shift' => ['required', Rule::in(['morning', 'evening', 'night', 'on_call'])],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $roster = $this->rosterService->assign($validated, $user);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['shift' => $e->getMessage()]);
        }

        return redirect()
            ->route('rosters.index', ['date' => $roster->duty_date->format('Y-m-d')])
            ->with('status', "Assigned {$roster->staffProfile->user->name} to {$roster->shift} shift ({$roster->department->name}).");
    }
}
