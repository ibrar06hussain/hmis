<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BreakGlassLog extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'patient_id',
        'reason_code',
        'justification',
        'ip_address',
        'granted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (empty($log->uuid)) {
                $log->uuid = (string) Str::uuid7();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
