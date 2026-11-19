<?php

declare(strict_types=1);

namespace App\Modules\Iam\Models;

use App\Models\User;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StaffProfile extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'facility_id',
        'department_id',
        'employee_code',
        'designation',
        'specialty',
        'license_number',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            if (empty($profile->uuid)) {
                $profile->uuid = (string) Str::uuid7();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
