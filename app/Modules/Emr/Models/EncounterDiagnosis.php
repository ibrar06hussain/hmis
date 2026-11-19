<?php

declare(strict_types=1);

namespace App\Modules\Emr\Models;

use App\Models\User;
use App\Modules\Patient\Models\Patient;
use App\Modules\Terminology\Models\TerminologyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EncounterDiagnosis extends Model
{
    protected $fillable = [
        'uuid',
        'encounter_id',
        'patient_id',
        'terminology_code_id',
        'coding_system',
        'terminology_version',
        'code',
        'display_name',
        'diagnosis_rank',
        'clinical_status',
        'clinical_context',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $diagnosis): void {
            if (empty($diagnosis->uuid)) {
                $diagnosis->uuid = (string) Str::uuid7();
            }
        });
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function terminologyCode(): BelongsTo
    {
        return $this->belongsTo(TerminologyCode::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
