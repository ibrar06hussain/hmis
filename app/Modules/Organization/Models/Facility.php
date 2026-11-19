<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Facility extends Model
{
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'short_name',
        'facility_type',
        'city',
        'region',
        'country',
        'phone',
        'address',
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
        static::creating(function (self $facility): void {
            if (empty($facility->uuid)) {
                $facility->uuid = (string) Str::uuid7();
            }
        });
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
