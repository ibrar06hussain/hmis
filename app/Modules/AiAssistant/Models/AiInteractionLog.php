<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiInteractionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'user_id',
        'patient_id',
        'encounter_id',
        'tool_name',
        'model_provider',
        'model_version',
        'prompt_summary',
        'response_summary',
        'status',
        'human_reviewed_by_user_id',
        'human_reviewed_at',
        'latency_ms',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'human_reviewed_at' => 'datetime',
            'occurred_at' => 'datetime',
            'latency_ms' => 'integer',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_reviewed_by_user_id');
    }
}
