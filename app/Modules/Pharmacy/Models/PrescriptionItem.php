<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PrescriptionItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPENSED = 'dispensed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'prescription_id',
        'medication_id',
        'dose',
        'route',
        'frequency',
        'duration_days',
        'quantity_prescribed',
        'quantity_dispensed',
        'instructions',
        'meal_timing',
        'status',
        'dispensed_by_user_id',
        'dispensed_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'quantity_prescribed' => 'integer',
            'quantity_dispensed' => 'integer',
            'dispensed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid7();
            }
        });
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by_user_id');
    }
}
