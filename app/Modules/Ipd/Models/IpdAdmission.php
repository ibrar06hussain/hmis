<?php

declare(strict_types=1);

namespace App\Modules\Ipd\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Bed;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Ward;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class IpdAdmission extends Model
{
    public const STATUS_ADMITTED = 'admitted';

    public const STATUS_DISCHARGED = 'discharged';

    public const STATUS_TRANSFERRED_OUT = 'transferred_out';

    public const STATUS_LAMA = 'lama';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'uuid',
        'admission_number',
        'patient_id',
        'encounter_id',
        'ward_id',
        'bed_id',
        'admitting_department_id',
        'attending_staff_id',
        'admission_type',
        'status',
        'admission_diagnosis',
        'admitted_at',
        'discharged_at',
        'discharge_summary',
        'discharge_condition',
        'follow_up_plan',
        'admitted_by_user_id',
        'discharged_by_user_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at' => 'datetime',
            'discharged_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $admission): void {
            if (empty($admission->uuid)) {
                $admission->uuid = (string) Str::uuid7();
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

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function admittingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'admitting_department_id');
    }

    public function attendingStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'attending_staff_id');
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by_user_id');
    }

    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by_user_id');
    }

    public function bedAllocations(): HasMany
    {
        return $this->hasMany(BedAllocation::class, 'admission_id')->orderByDesc('allocated_at');
    }
}
