<?php

declare(strict_types=1);

namespace App\Modules\Devices\Models;

use App\Modules\Organization\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AnalyzerDevice extends Model
{
    protected $fillable = [
        'uuid',
        'device_code',
        'manufacturer',
        'model',
        'serial_number',
        'firmware_version',
        'protocol',
        'host_or_port',
        'department_id',
        'interface_version',
        'connection_status',
        'test_code_map',
        'last_communication_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'test_code_map' => 'array',
            'last_communication_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $device): void {
            if (empty($device->uuid)) {
                $device->uuid = (string) Str::uuid7();
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
