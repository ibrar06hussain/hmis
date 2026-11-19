<?php

declare(strict_types=1);

namespace App\Modules\Patient\Models;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Emr\Models\VitalSign;
use App\Modules\Organization\Models\Facility;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Support\CnicNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Patient extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_MERGED = 'merged';

    public const STATUS_DECEASED = 'deceased';

    public const STATUS_INACTIVE = 'inactive';

    public const REG_STANDARD = 'standard';

    public const REG_EMERGENCY = 'emergency';

    public const REG_NEWBORN = 'newborn';

    protected $fillable = [
        'uuid',
        'mrn',
        'status',
        'merged_into_patient_id',
        'registered_facility_id',
        'registered_by_user_id',
        'full_name',
        'normalized_name',
        'father_or_spouse_name',
        'normalized_father_name',
        'date_of_birth',
        'dob_estimated',
        'sex',
        'blood_group',
        'marital_status',
        'occupation',
        'nationality',
        'cnic_normalized',
        'phone_primary',
        'phone_secondary',
        'email',
        'address_line',
        'city',
        'tehsil',
        'district',
        'province',
        'registration_type',
        'is_unknown_emergency',
        'identity_verification_status',
        'photo_path',
        'registration_notes',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'dob_estimated' => 'boolean',
            'is_unknown_emergency' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $patient): void {
            if (empty($patient->uuid)) {
                $patient->uuid = (string) Str::uuid7();
            }
            $patient->normalized_name = self::normalizeSearchText($patient->full_name);
            $patient->normalized_father_name = self::normalizeSearchText($patient->father_or_spouse_name);
            $patient->cnic_normalized = CnicNormalizer::normalize($patient->cnic_normalized);
        });

        static::updating(function (self $patient): void {
            // Protect MRN immutability at the model level as defense-in-depth
            if ($patient->isDirty('mrn')) {
                throw new \LogicException('Hospital MRN is permanent and cannot be modified.');
            }
            if ($patient->isDirty('full_name')) {
                $patient->normalized_name = self::normalizeSearchText($patient->full_name);
            }
            if ($patient->isDirty('father_or_spouse_name')) {
                $patient->normalized_father_name = self::normalizeSearchText($patient->father_or_spouse_name);
            }
            if ($patient->isDirty('cnic_normalized')) {
                $patient->cnic_normalized = CnicNormalizer::normalize($patient->cnic_normalized);
            }
        });
    }

    public static function normalizeSearchText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/\s+/', ' ', strtolower(trim($value)));

        return $clean === '' ? null : $clean;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_patient_id');
    }

    public function registeredFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'registered_facility_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(PatientIdentifier::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PatientContact::class);
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class)->orderByDesc('started_at');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderByDesc('appointment_date');
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class)->orderByDesc('recorded_at');
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class)->orderByDesc('created_at');
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class)->orderByDesc('recorded_at');
    }

    public function activeAllergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class)->where('clinical_status', 'active');
    }

    public function imagingStudies(): HasMany
    {
        return $this->hasMany(ImagingStudy::class)->orderByDesc('study_date');
    }

    public function mergesAsTarget(): HasMany
    {
        return $this->hasMany(PatientMerge::class, 'target_patient_id');
    }

    public function formattedCnic(): ?string
    {
        return CnicNormalizer::format($this->cnic_normalized);
    }

    public function maskedCnic(): ?string
    {
        return CnicNormalizer::mask($this->cnic_normalized);
    }

    public function ageDisplay(): string
    {
        if ($this->date_of_birth === null) {
            return 'Unknown';
        }

        $dob = Carbon::instance($this->date_of_birth);
        $years = (int) $dob->diffInYears(Carbon::now('UTC'));

        if ($years >= 2) {
            return ($this->dob_estimated ? '~' : '').$years.' yrs';
        }

        $months = (int) $dob->diffInMonths(Carbon::now('UTC'));
        if ($months >= 1) {
            return ($this->dob_estimated ? '~' : '').$months.' mos';
        }

        $days = (int) $dob->diffInDays(Carbon::now('UTC'));

        return ($this->dob_estimated ? '~' : '').$days.' days';
    }

    public function isMinor(): bool
    {
        if ($this->date_of_birth === null) {
            return false;
        }

        return Carbon::instance($this->date_of_birth)->age < 18;
    }

    public function canonicalPatient(): self
    {
        $current = $this;
        $hops = 0;

        while ($current->status === self::STATUS_MERGED && $current->merged_into_patient_id !== null && $hops < 10) {
            $next = self::query()->find($current->merged_into_patient_id);
            if ($next === null) {
                break;
            }
            $current = $next;
            $hops++;
        }

        return $current;
    }
}
