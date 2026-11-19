<?php

declare(strict_types=1);

namespace App\Modules\Lis\Models;

use App\Models\User;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Specimen extends Model
{
    public const STATUS_PENDING_COLLECTION = 'pending_collection';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_RECEIVED = 'received_in_lab';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IN_ANALYSIS = 'in_analysis';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'uuid',
        'barcode',
        'lab_order_id',
        'patient_id',
        'specimen_type',
        'status',
        'collected_by_user_id',
        'collected_at',
        'received_by_user_id',
        'received_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $specimen): void {
            if (empty($specimen->uuid)) {
                $specimen->uuid = (string) Str::uuid7();
            }
        });
    }

    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
