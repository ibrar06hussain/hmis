<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'medication_id',
        'movement_type',
        'quantity_delta',
        'balance_after',
        'reference_type',
        'reference_id',
        'notes',
        'actor_user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'balance_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $movement): void {
            if (empty($movement->uuid)) {
                $movement->uuid = (string) Str::uuid7();
            }
        });
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
