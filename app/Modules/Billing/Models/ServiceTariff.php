<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceTariff extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'unit_price_pkr',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_pkr' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
