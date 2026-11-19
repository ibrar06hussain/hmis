<?php

declare(strict_types=1);

namespace App\Modules\Cardiology\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CardiacProcedure extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'procedure_number',
        'patient_id',
        'encounter_id',
        'category',
        'procedure_name',
        'theatre_or_lab',
        'status',
        'lead_operator_staff_id',
        'anesthetist_staff_id',
        'who_checklist_completed',
        'access_site',
        'contrast_volume_ml',
        'fluoroscopy_minutes',
        'stents_implanted',
        'grafts_performed',
        'operative_findings',
        'complications',
        'post_op_orders',
        'scheduled_at',
        'completed_at',
        'signed_by_user_id',
        'signed_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'who_checklist_completed' => 'boolean',
            'contrast_volume_ml' => 'integer',
            'fluoroscopy_minutes' => 'float',
            'stents_implanted' => 'array',
            'grafts_performed' => 'array',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'signed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $procedure): void {
            if (empty($procedure->uuid)) {
                $procedure->uuid = (string) Str::uuid7();
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

    public function leadOperator(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'lead_operator_staff_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }
}
