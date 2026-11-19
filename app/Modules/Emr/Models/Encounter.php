<?php

declare(strict_types=1);

namespace App\Modules\Emr\Models;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Emergency\Models\EmergencyTriage;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Organization\Models\Department;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Patient\Models\Patient;
use App\Modules\Pharmacy\Models\Prescription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Encounter extends Model
{
    protected $fillable = [
        'uuid',
        'encounter_number',
        'patient_id',
        'department_id',
        'attending_staff_id',
        'class',
        'status',
        'chief_complaint',
        'started_at',
        'ended_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $encounter): void {
            if (empty($encounter->uuid)) {
                $encounter->uuid = (string) Str::uuid7();
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attendingStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'attending_staff_id');
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class)->orderByDesc('recorded_at');
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class)->orderByDesc('created_at');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(EncounterDiagnosis::class)->orderBy('diagnosis_rank')->orderByDesc('recorded_at');
    }

    public function emergencyTriage(): HasOne
    {
        return $this->hasOne(EmergencyTriage::class);
    }

    public function ipdAdmission(): HasOne
    {
        return $this->hasOne(IpdAdmission::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class)->orderByDesc('ordered_at');
    }

    public function imagingStudies(): HasMany
    {
        return $this->hasMany(ImagingStudy::class)->orderByDesc('created_at');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)->orderByDesc('prescribed_at');
    }
}
