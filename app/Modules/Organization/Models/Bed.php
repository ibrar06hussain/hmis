<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Bed extends Model
{
    protected $fillable = [
        'uuid',
        'ward_id',
        'bed_number',
        'status',
        'has_cardiac_monitor',
        'has_ventilator',
        'is_isolation',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'has_cardiac_monitor' => 'boolean',
            'has_ventilator' => 'boolean',
            'is_isolation' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $bed): void {
            if (empty($bed->uuid)) {
                $bed->uuid = (string) Str::uuid7();
            }
        });
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }
}
