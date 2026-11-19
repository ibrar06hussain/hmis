<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Append-only, immutable financial ledger.
 * Existing entries cannot be updated or deleted; reversals require a compensating entry.
 */
class FinancialLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'entry_number',
        'invoice_id',
        'patient_id',
        'entry_type',
        'payment_method',
        'amount_pkr',
        'reference_note',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_pkr' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid7();
            }
        });

        static::updating(function (): void {
            throw new \LogicException('Financial ledger entries are strictly immutable. Post a compensating refund or void reversal entry instead.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Financial ledger entries cannot be deleted.');
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
