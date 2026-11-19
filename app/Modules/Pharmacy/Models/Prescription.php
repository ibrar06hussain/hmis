<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Prescription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PARTIALLY_DISPENSED = 'partially_dispensed';

    public const STATUS_DISPENSED = 'dispensed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'prescription_number',
        'patient_id',
        'encounter_id',
        'prescribed_by_user_id',
        'status',
        'allergy_override_used',
        'allergy_override_reason',
        'clinical_notes',
        'prescribed_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'allergy_override_used' => 'boolean',
            'prescribed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $rx): void {
            if (empty($rx->uuid)) {
                $rx->uuid = (string) Str::uuid7();
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

    public function prescribedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}
