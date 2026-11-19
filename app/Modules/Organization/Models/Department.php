<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Department extends Model
{
    protected $fillable = [
        'uuid',
        'facility_id',
        'code',
        'name',
        'category',
        'is_clinical',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_clinical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $department): void {
            if (empty($department->uuid)) {
                $department->uuid = (string) Str::uuid7();
            }
        });
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }
}
