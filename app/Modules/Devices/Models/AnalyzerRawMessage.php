<?php

declare(strict_types=1);

namespace App\Modules\Devices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AnalyzerRawMessage extends Model
{
    protected $fillable = [
        'uuid',
        'analyzer_device_id',
        'message_hash',
        'control_id',
        'protocol',
        'raw_payload',
        'parse_status',
        'error_message',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $msg): void {
            if (empty($msg->uuid)) {
                $msg->uuid = (string) Str::uuid7();
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AnalyzerDevice::class, 'analyzer_device_id');
    }
}
