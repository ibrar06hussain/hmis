<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Models\BreakGlassLog;
use App\Modules\Iam\Services\BreakGlassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AuditOversightController extends Controller
{
    public function __construct(
        private readonly BreakGlassService $breakGlassService,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('audit.view_logs'), 403, 'Audit log oversight requires audit.view_logs permission.');

        $actionFilter = trim($request->string('action')->toString());
        $preset = trim($request->string('preset')->toString());
        $roleFilter = trim($request->string('actor_role')->toString());
        $actorQuery = trim($request->string('actor_q')->toString());
        $mrnFilter = trim($request->string('mrn')->toString());
        $dateFrom = trim($request->string('date_from')->toString());
        $dateTo = trim($request->string('date_to')->toString());

        $displayTz = (string) config('hmis.display_timezone', 'Asia/Karachi');
        $nowUtc = Carbon::now('UTC');

        $query = AuditLog::query()
            ->with(['actor:id,name,username', 'patient:id,mrn'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($preset !== '') {
            match ($preset) {
                'break_glass' => $query->where('action', 'like', '%break_glass%'),
                'auth_2fa' => $query->where(fn ($q) => $q->where('action', 'like', 'auth.%')->orWhere('action', 'like', 'security.2fa.%')),
                'patient_merge' => $query->where('action', 'like', '%merge%'),
                'billing' => $query->where('action', 'like', 'billing.%'),
                'ai' => $query->where('action', 'like', 'ai.%'),
                default => null,
            };
        }

        if ($actionFilter !== '') {
            $query->where('action', 'like', '%'.$actionFilter.'%');
        }

        if ($roleFilter !== '') {
            $query->where('actor_role', $roleFilter);
        }

        if ($actorQuery !== '') {
            $query->whereHas('actor', function ($q) use ($actorQuery): void {
                $q->where('name', 'like', '%'.$actorQuery.'%')
                    ->orWhere('username', 'like', '%'.$actorQuery.'%');
            });
        }

        if ($mrnFilter !== '') {
            $query->whereHas('patient', fn ($q) => $q->where('mrn', 'like', '%'.strtoupper($mrnFilter).'%'));
        }

        if ($dateFrom !== '') {
            try {
                $fromUtc = Carbon::createFromFormat('Y-m-d', $dateFrom, $displayTz)->startOfDay()->utc();
                $query->where('occurred_at', '>=', $fromUtc);
            } catch (\Throwable) {
                // Ignore malformed date filter gracefully
            }
        }

        if ($dateTo !== '') {
            try {
                $toUtc = Carbon::createFromFormat('Y-m-d', $dateTo, $displayTz)->endOfDay()->utc();
                $query->where('occurred_at', '<=', $toUtc);
            } catch (\Throwable) {
                // Ignore malformed date filter gracefully
            }
        }

        $auditLogs = $query->paginate(25)->withQueryString();

        $activeBreakGlass = BreakGlassLog::query()
            ->with(['user:id,name,username', 'patient:id,mrn'])
            ->where('expires_at', '>', $nowUtc)
            ->orderByDesc('granted_at')
            ->get();

        $recentBreakGlass = BreakGlassLog::query()
            ->with(['user:id,name,username', 'patient:id,mrn'])
            ->orderByDesc('granted_at')
            ->limit(25)
            ->get();

        $availableRoles = AuditLog::query()
            ->whereNotNull('actor_role')
            ->distinct()
            ->orderBy('actor_role')
            ->pluck('actor_role');

        $metrics = [
            'events_24h' => AuditLog::query()->where('occurred_at', '>=', $nowUtc->copy()->subDay())->count(),
            'auth_failures_7d' => AuditLog::query()
                ->where('occurred_at', '>=', $nowUtc->copy()->subDays(7))
                ->whereIn('action', ['auth.login_failed', 'auth.2fa_failed'])
                ->count(),
            'active_break_glass' => $activeBreakGlass->count(),
            'total_break_glass' => BreakGlassLog::query()->count(),
        ];

        return view('modules.audit.index', [
            'auditLogs' => $auditLogs,
            'activeBreakGlass' => $activeBreakGlass,
            'recentBreakGlass' => $recentBreakGlass,
            'availableRoles' => $availableRoles,
            'metrics' => $metrics,
            'filters' => [
                'action' => $actionFilter,
                'preset' => $preset,
                'actor_role' => $roleFilter,
                'actor_q' => $actorQuery,
                'mrn' => $mrnFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function revokeBreakGlass(Request $request, BreakGlassLog $breakGlassLog): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('audit.view_logs'), 403, 'Only authorized audit governance staff may revoke break-glass access.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            $revoked = $this->breakGlassService->revoke($breakGlassLog, $user, (string) $validated['reason']);
        } catch (\DomainException $exception) {
            return back()->withErrors(['break_glass' => $exception->getMessage()]);
        }

        $mrn = $revoked->patient?->mrn ?? ('Patient #'.$revoked->patient_id);

        return redirect()
            ->route('admin.audit-logs.index')
            ->with('warning', "Break-glass override for {$mrn} ({$revoked->user?->name}) has been revoked immediately and recorded in the audit log.");
    }
}
