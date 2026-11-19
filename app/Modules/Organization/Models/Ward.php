<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ward extends Model
{
    protected $fillable = [
        'uuid',
        'department_id',
        'code',
        'name',
        'ward_type',
        'floor',
        'bed_capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'bed_capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ward): void {
            if (empty($ward->uuid)) {
                $ward->uuid = (string) Str::uuid7();
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
