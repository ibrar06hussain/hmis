<?php

declare(strict_types=1);

namespace App\Modules\Iam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Models\BreakGlassLog;
use App\Modules\Emr\Models\Encounter;
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

        $todayUtcStart = Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))
            ->startOfDay()
            ->setTimezone('UTC');

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
            'primaryRole' => $user->getRoleNames()->first() ?? 'staff',
            'metrics' => $metrics,
            'recentPatients' => $recentPatients,
            'recentAuditLogs' => $recentAuditLogs,
            'activeBreakGlassCount' => $activeBreakGlassCount,
        ]);
    }
}
