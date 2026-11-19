<?php

declare(strict_types=1);

namespace App\Modules\Emr\Models;

use App\Models\User;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VitalSign extends Model
{
    protected $fillable = [
        'uuid',
        'patient_id',
        'encounter_id',
        'recorded_by_user_id',
        'recorded_at',
        'systolic_bp',
        'diastolic_bp',
        'heart_rate',
        'respiratory_rate',
        'temperature_c',
        'spo2_percent',
        'weight_kg',
        'height_cm',
        'bmi',
        'blood_glucose_mg_dl',
        'pain_score',
        'rhythm_note',
        'is_abnormal',
        'abnormal_flags',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'systolic_bp' => 'integer',
            'diastolic_bp' => 'integer',
            'heart_rate' => 'integer',
            'respiratory_rate' => 'integer',
            'temperature_c' => 'float',
            'spo2_percent' => 'integer',
            'weight_kg' => 'float',
            'height_cm' => 'float',
            'bmi' => 'float',
            'blood_glucose_mg_dl' => 'integer',
            'pain_score' => 'integer',
            'is_abnormal' => 'boolean',
            'abnormal_flags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $vital): void {
            if (empty($vital->uuid)) {
                $vital->uuid = (string) Str::uuid7();
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
