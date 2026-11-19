<?php

declare(strict_types=1);

namespace App\Modules\Pacs\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Modules\Ris\Models\ImagingCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImagingStudy extends Model
{
    public const STATUS_ORDERED = 'ordered';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_REPORTED = 'reported';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'patient_id',
        'encounter_id',
        'imaging_catalog_id',
        'ordered_by_user_id',
        'accession_number',
        'modality',
        'priority',
        'procedure_name',
        'clinical_indication',
        'study_instance_uid',
        'pacs_server_key',
        'status',
        'report_summary',
        'reported_by_user_id',
        'reported_at',
        'verified_by_user_id',
        'verified_at',
        'study_date',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'verified_at' => 'datetime',
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

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ImagingCatalog::class, 'imaging_catalog_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_user_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
