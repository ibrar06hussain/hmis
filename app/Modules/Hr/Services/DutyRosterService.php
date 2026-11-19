<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Hr\Models\DutyRoster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DutyRosterService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Assign a staff member to a shift while preventing double-booking on the same date & shift.
     *
     * @param  array<string, mixed>  $data
     */
    public function assign(array $data, User $actor): DutyRoster
    {
        $staffProfileId = (int) $data['staff_profile_id'];
        $dutyDate = Carbon::parse((string) $data['duty_date'])->toDateString();
        $shift = (string) $data['shift'];

        return DB::transaction(function () use ($data, $actor, $staffProfileId, $dutyDate, $shift): DutyRoster {
            $conflict = DutyRoster::query()
                ->where('staff_profile_id', $staffProfileId)
                ->whereDate('duty_date', $dutyDate)
                ->where('shift', $shift)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new \DomainException("Shift conflict: Staff member is already rostered for the {$shift} shift on {$dutyDate}.");
            }

            $roster = DutyRoster::query()->create([
                'staff_profile_id' => $staffProfileId,
                'department_id' => (int) $data['department_id'],
                'ward_id' => ! empty($data['ward_id']) ? (int) $data['ward_id'] : null,
                'duty_date' => $dutyDate,
                'shift' => $shift,
                'status' => 'scheduled',
                'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
                'assigned_by_user_id' => $actor->id,
            ]);

            $this->auditLogger->log(
                action: 'hr.roster.assigned',
                actor: $actor,
                subject: $roster,
                newValues: [
                    'staff_profile_id' => $staffProfileId,
                    'department_id' => $roster->department_id,
                    'duty_date' => $dutyDate,
                    'shift' => $shift,
                ],
            );

            return $roster->fresh(['staffProfile.user', 'department', 'ward']);
        });
    }
}
