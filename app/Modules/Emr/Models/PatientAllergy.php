<?php

declare(strict_types=1);

namespace App\Modules\Emr\Models;

use App\Models\User;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PatientAllergy extends Model
{
    protected $fillable = [
        'uuid',
        'patient_id',
        'substance',
        'category',
        'severity',
        'reaction',
        'clinical_status',
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
        static::creating(function (self $allergy): void {
            if (empty($allergy->uuid)) {
                $allergy->uuid = (string) Str::uuid7();
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
