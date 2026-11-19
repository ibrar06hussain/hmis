<?php

declare(strict_types=1);

namespace App\Modules\Icu\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Organization\Models\Bed;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IcuDeviceObservation extends Model
{
    public const STATUS_UNVERIFIED_RAW = 'unverified_raw';

    public const STATUS_CLINICIAN_VERIFIED = 'clinician_verified';

    public const STATUS_REJECTED_ARTIFACT = 'rejected_artifact';

    protected $fillable = [
        'uuid',
        'patient_id',
        'encounter_id',
        'bed_id',
        'device_code',
        'observation_type',
        'value_numeric',
        'unit',
        'data_quality',
        'is_abnormal',
        'verification_status',
        'waveform_reference',
        'observed_at',
        'verified_by_user_id',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'value_numeric' => 'float',
            'is_abnormal' => 'boolean',
            'observed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $obs): void {
            if (empty($obs->uuid)) {
                $obs->uuid = (string) Str::uuid7();
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

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
