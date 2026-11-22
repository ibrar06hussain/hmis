<?php

declare(strict_types=1);

namespace App\Modules\Lis\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LabOrderItem extends Model
{
    public const STATUS_ORDERED = 'ordered';

    public const STATUS_RESULT_ENTERED = 'result_entered';

    public const STATUS_TECH_VALIDATED = 'tech_validated';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_AMENDED = 'amended';

    protected $fillable = [
        'uuid',
        'lab_order_id',
        'lab_test_catalog_id',
        'specimen_id',
        'status',
        'result_value_numeric',
        'result_value_text',
        'unit',
        'ref_low',
        'ref_high',
        'abnormal_flag',
        'is_critical',
        'critical_acknowledged_by_user_id',
        'critical_acknowledged_at',
        'result_source',
        'analyzer_device_code',
        'entered_by_user_id',
        'entered_at',
        'tech_validated_by_user_id',
        'tech_validated_at',
        'verified_by_user_id',
        'verified_at',
        'amendment_reason',
        'remarks',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'result_value_numeric' => 'float',
            'ref_low' => 'float',
            'ref_high' => 'float',
            'is_critical' => 'boolean',
            'critical_acknowledged_at' => 'datetime',
            'entered_at' => 'datetime',
            'tech_validated_at' => 'datetime',
            'verified_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid7();
            }
        });
    }

    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTestCatalog::class, 'lab_test_catalog_id');
    }

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(Specimen::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function criticalAcknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'critical_acknowledged_by_user_id');
    }
}
