<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'service_tariff_id',
        'description',
        'quantity',
        'unit_price_pkr',
        'line_total_pkr',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_pkr' => 'float',
            'line_total_pkr' => 'float',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(ServiceTariff::class, 'service_tariff_id');
    }
}
