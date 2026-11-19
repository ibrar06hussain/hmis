<?php

declare(strict_types=1);

namespace App\Modules\Lis\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LabOrder extends Model
{
    public const STATUS_ORDERED = 'ordered';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_IN_ANALYSIS = 'in_analysis';

    public const STATUS_PRELIMINARY = 'preliminary';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'order_number',
        'patient_id',
        'encounter_id',
        'ordered_by_user_id',
        'priority',
        'status',
        'clinical_notes',
        'ordered_at',
        'verified_by_user_id',
        'verified_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'verified_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid7();
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(Specimen::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }
}
