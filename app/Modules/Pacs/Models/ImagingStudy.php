<?php

declare(strict_types=1);

namespace App\Modules\Pacs\Models;

use App\Modules\Emr\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImagingStudy extends Model
{
    protected $fillable = [
        'uuid',
        'patient_id',
        'encounter_id',
        'accession_number',
        'modality',
        'procedure_name',
        'study_instance_uid',
        'pacs_server_key',
        'status',
        'report_summary',
        'study_date',
    ];

    protected function casts(): array
    {
        return [
            'study_date' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $study): void {
            if (empty($study->uuid)) {
                $study->uuid = (string) Str::uuid7();
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
}
