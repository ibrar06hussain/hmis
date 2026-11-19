<?php

declare(strict_types=1);

namespace App\Modules\Patient\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PatientMerge extends Model
{
    protected $fillable = [
        'uuid',
        'source_patient_id',
        'target_patient_id',
        'merged_by_user_id',
        'reason',
        'snapshot_metadata',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_metadata' => 'array',
            'merged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $merge): void {
            if (empty($merge->uuid)) {
                $merge->uuid = (string) Str::uuid7();
            }
        });
    }

    public function sourcePatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'source_patient_id');
    }

    public function targetPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'target_patient_id');
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by_user_id');
    }
}
