<?php

declare(strict_types=1);

namespace App\Modules\BedManagement\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Organization\Models\Bed;
use Illuminate\Support\Facades\DB;

final class BedManagementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function markAvailableAfterCleaning(Bed $bed, User $actor): Bed
    {
        return DB::transaction(function () use ($bed, $actor): Bed {
            $locked = Bed::query()->whereKey($bed->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['cleaning', 'maintenance', 'reserved'], true)) {
                throw new \DomainException("Bed {$locked->bed_number} cannot be marked available from status '{$locked->status}'.");
            }

            $oldStatus = $locked->status;
            $locked->status = 'available';
            $locked->version = $locked->version + 1;
            $locked->save();

            $this->auditLogger->log(
                action: 'bed.marked_available',
                actor: $actor,
                subject: $locked,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'available', 'bed_number' => $locked->bed_number],
            );

            return $locked;
        });
    }

    public function setMaintenance(Bed $bed, User $actor, ?string $reason = null): Bed
    {
        return DB::transaction(function () use ($bed, $actor, $reason): Bed {
            $locked = Bed::query()->whereKey($bed->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'occupied') {
                throw new \DomainException("Cannot place occupied bed {$locked->bed_number} into maintenance. Transfer patient first.");
            }

            $oldStatus = $locked->status;
            $locked->status = 'maintenance';
            $locked->version = $locked->version + 1;
            $locked->save();

            $this->auditLogger->log(
                action: 'bed.maintenance_set',
                actor: $actor,
                subject: $locked,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'maintenance', 'bed_number' => $locked->bed_number],
                reason: $reason,
            );

            return $locked;
        });
    }
}
