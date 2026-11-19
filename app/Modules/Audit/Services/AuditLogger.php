<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class AuditLogger
{
    /** @var list<string> */
    private const REDACTED_KEYS = ['password', 'remember_token', 'token', 'secret', 'api_key'];

    public function log(
        string $action,
        ?User $actor = null,
        ?Model $subject = null,
        ?int $patientId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;
        $user = $actor ?? ($request?->user());
        $actorRole = $user instanceof User ? $user->getRoleNames()->first() : null;

        return AuditLog::query()->create([
            'event_uuid' => (string) Str::uuid7(),
            'actor_user_id' => $user?->id,
            'actor_role' => is_string($actorRole) ? $actorRole : null,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
            'subject_id' => $subject !== null ? (int) $subject->getKey() : null,
            'patient_id' => $patientId,
            'old_values' => $oldValues !== null ? $this->sanitize($oldValues) : null,
            'new_values' => $newValues !== null ? $this->sanitize($newValues) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? Str::limit((string) $request->userAgent(), 255, '') : null,
            'reason' => $reason,
            'occurred_at' => Carbon::now('UTC'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            $sanitized[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $sanitized;
    }
}
