<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Patient\Models\Patient;
use App\Modules\Pharmacy\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NursingMarRecord extends Model
{
    public const STATUS_ADMINISTERED = 'administered';

    public const STATUS_HELD = 'held';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_MISSED = 'missed';

    protected $fillable = [
        'uuid',
        'patient_id',
        'encounter_id',
        'ipd_admission_id',
        'prescription_item_id',
        'medication_name',
        'dose_given',
        'route',
        'scheduled_for',
        'administered_at',
        'status',
        'clinical_reason',
        'administered_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'administered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid7();
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

    public function admission(): BelongsTo
    {
        return $this->belongsTo(IpdAdmission::class, 'ipd_admission_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by_user_id');
    }
}
