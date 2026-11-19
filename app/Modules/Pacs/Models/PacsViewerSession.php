<?php

declare(strict_types=1);

namespace App\Modules\Pacs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PacsViewerSession extends Model
{
    protected $fillable = [
        'uuid',
        'imaging_study_id',
        'user_id',
        'study_instance_uid',
        'launch_token_hash',
        'ip_address',
        'launched_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'launched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid7();
            }
        });
    }

    public function imagingStudy(): BelongsTo
    {
        return $this->belongsTo(ImagingStudy::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
