<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Ward;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DutyRoster extends Model
{
    protected $fillable = [
        'uuid',
        'staff_profile_id',
        'department_id',
        'ward_id',
        'duty_date',
        'shift',
        'status',
        'notes',
        'assigned_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'duty_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $roster): void {
            if (empty($roster->uuid)) {
                $roster->uuid = (string) Str::uuid7();
            }
        });
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
