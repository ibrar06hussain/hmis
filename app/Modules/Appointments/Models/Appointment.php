<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Appointment extends Model
{
    public const STATUS_BOOKED = 'booked';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_IN_CONSULT = 'in_consult';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'patient_id',
        'department_id',
        'clinician_staff_id',
        'encounter_id',
        'appointment_date',
        'token_number',
        'visit_type',
        'status',
        'chief_complaint',
        'checked_in_at',
        'created_by_user_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'token_number' => 'integer',
            'checked_in_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $appointment): void {
            if (empty($appointment->uuid)) {
                $appointment->uuid = (string) Str::uuid7();
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

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'clinician_staff_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function formattedToken(): string
    {
        return sprintf('#%03d', $this->token_number);
    }
}
