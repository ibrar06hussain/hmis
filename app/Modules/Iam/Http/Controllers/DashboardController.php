<?php

declare(strict_types=1);

namespace App\Modules\Iam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Models\BreakGlassLog;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Ward;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('staffProfile.department');

        $displayTz = (string) config('hmis.display_timezone', 'Asia/Karachi');
        $todayPkt = Carbon::now($displayTz)->toDateString();
        $todayUtcStart = Carbon::now($displayTz)->startOfDay()->setTimezone('UTC');
        $primaryRole = $user->getRoleNames()->first() ?? 'staff';

        // Focused Consultant Clinical Dashboard (zero admin/department clutter)
        if ($user->hasRole('consultant') && ! $user->can('audit.view_logs')) {
            $todayOpdQueue = Appointment::query()
                ->with(['patient.activeAllergies', 'department', 'encounter.vitalSigns'])
                ->whereDate('appointment_date', $todayPkt)
                ->whereIn('status', [
                    Appointment::STATUS_BOOKED,
                    Appointment::STATUS_CHECKED_IN,
                    Appointment::STATUS_IN_CONSULT,
                ])
                ->orderBy('token_number')
                ->limit(15)
                ->get();

            $activeAdmissions = IpdAdmission::query()
                ->with(['patient', 'ward', 'bed', 'encounter'])
                ->where('status', IpdAdmission::STATUS_ADMITTED)
                ->orderByDesc('admitted_at')
                ->limit(10)
                ->get();

            $pendingCoSignCount = ClinicalNote::query()
                ->where('status', ClinicalNote::STATUS_DRAFT)
                ->count();

            $consultantMetrics = [
                'opd_today' => Appointment::query()->whereDate('appointment_date', $todayPkt)->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])->count(),
                'waiting_now' => Appointment::query()->whereDate('appointment_date', $todayPkt)->where('status', Appointment::STATUS_CHECKED_IN)->count(),
                'pending_cosign' => $pendingCoSignCount,
                'active_inpatients' => IpdAdmission::query()->where('status', IpdAdmission::STATUS_ADMITTED)->count(),
            ];

            return view('dashboard.consultant', [
                'user' => $user,
                'primaryRole' => $primaryRole,
                'consultantMetrics' => $consultantMetrics,
                'todayOpdQueue' => $todayOpdQueue,
                'activeAdmissions' => $activeAdmissions,
                'todayDate' => $todayPkt,
            ]);
        }

        $metrics = [
            'active_patients' => Patient::query()->active()->count(),
            'registered_today' => Patient::query()->where('created_at', '>=', $todayUtcStart)->count(),
            'unverified_emergency' => Patient::query()->active()->where('identity_verification_status', 'unverified')->count(),
            'active_departments' => Department::query()->where('is_active', true)->count(),
            'total_wards' => Ward::query()->where('is_active', true)->count(),
            'active_encounters' => Encounter::query()->whereIn('status', ['arrived', 'in_progress'])->count(),
        ];

        $recentPatients = $user->can('patient.view_demographics')
            ? Patient::query()->active()->withCount('activeAllergies')->orderByDesc('id')->limit(8)->get()
            : collect();

        $recentAuditLogs = $user->can('audit.view_logs')
            ? AuditLog::query()->with('actor')->orderByDesc('occurred_at')->limit(8)->get()
            : collect();

        $activeBreakGlassCount = $user->can('audit.view_logs')
            ? BreakGlassLog::query()->where('expires_at', '>', Carbon::now('UTC'))->count()
            : 0;

        return view('dashboard.index', [
            'user' => $user,
            'primaryRole' => $primaryRole,
            'metrics' => $metrics,
            'recentPatients' => $recentPatients,
            'recentAuditLogs' => $recentAuditLogs,
            'activeBreakGlassCount' => $activeBreakGlassCount,
        ]);
    }
}
