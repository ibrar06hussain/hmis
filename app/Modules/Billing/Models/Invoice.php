<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    public const STATUS_ISSUED = 'issued';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'uuid',
        'invoice_number',
        'patient_id',
        'encounter_id',
        'billing_category',
        'status',
        'subtotal_pkr',
        'exemption_pkr',
        'net_payable_pkr',
        'paid_pkr',
        'balance_pkr',
        'exemption_reason',
        'issued_by_user_id',
        'issued_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_pkr' => 'float',
            'exemption_pkr' => 'float',
            'net_payable_pkr' => 'float',
            'paid_pkr' => 'float',
            'balance_pkr' => 'float',
            'issued_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            if (empty($invoice->uuid)) {
                $invoice->uuid = (string) Str::uuid7();
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FinancialLedgerEntry::class)->orderByDesc('recorded_at');
    }
}
