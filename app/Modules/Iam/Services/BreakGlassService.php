<?php

declare(strict_types=1);

namespace App\Modules\Iam\Services;

use App\Models\User;
use App\Modules\Audit\Models\BreakGlassLog;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BreakGlassService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function hasActiveGrant(User $user, Patient $patient): bool
    {
        return BreakGlassLog::query()
            ->where('user_id', $user->id)
            ->where('patient_id', $patient->id)
            ->where('expires_at', '>', Carbon::now('UTC'))
            ->exists();
    }

    public function grant(
        User $user,
        Patient $patient,
        string $reasonCode,
        string $justification,
    ): BreakGlassLog {
        $ttlHours = max(1, (int) config('hmis.break_glass_ttl_hours', 4));
        $now = Carbon::now('UTC');

        return DB::transaction(function () use ($user, $patient, $reasonCode, $justification, $ttlHours, $now): BreakGlassLog {
            $log = BreakGlassLog::query()->create([
                'user_id' => $user->id,
                'patient_id' => $patient->id,
                'reason_code' => $reasonCode,
                'justification' => $justification,
                'ip_address' => app()->bound('request') ? request()->ip() : null,
                'granted_at' => $now,
                'expires_at' => $now->copy()->addHours($ttlHours),
            ]);

            $this->auditLogger->log(
                action: 'security.break_glass.granted',
                actor: $user,
                subject: $log,
                patientId: $patient->id,
                newValues: [
                    'mrn' => $patient->mrn,
                    'reason_code' => $reasonCode,
                    'expires_at' => $log->expires_at->toIso8601String(),
                ],
                reason: $justification,
            );

            return $log;
        });
    }

    public function revoke(BreakGlassLog $grant, User $actor, string $reason): BreakGlassLog
    {
        $now = Carbon::now('UTC');

        return DB::transaction(function () use ($grant, $actor, $reason, $now): BreakGlassLog {
            $locked = BreakGlassLog::query()->whereKey($grant->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw new \DomainException('This break-glass override has already expired or been revoked.');
            }

            $previousExpiresAt = $locked->expires_at?->toIso8601String();
            $locked->expires_at = $now;
            $locked->save();

            $this->auditLogger->log(
                action: 'security.break_glass.revoked',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                oldValues: ['expires_at' => $previousExpiresAt],
                newValues: ['expires_at' => $now->toIso8601String(), 'revoked_by' => $actor->username],
                reason: $reason,
            );

            return $locked->fresh(['user', 'patient']);
        });
    }
}
