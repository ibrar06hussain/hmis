<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NursingNote extends Model
{
    protected $fillable = [
        'uuid',
        'patient_id',
        'encounter_id',
        'ipd_admission_id',
        'shift',
        'note_category',
        'intake_ml',
        'output_ml',
        'content',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'intake_ml' => 'integer',
            'output_ml' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            if (empty($note->uuid)) {
                $note->uuid = (string) Str::uuid7();
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function netFluidBalanceMl(): ?int
    {
        if ($this->intake_ml === null && $this->output_ml === null) {
            return null;
        }

        return ($this->intake_ml ?? 0) - ($this->output_ml ?? 0);
    }
}
