<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    protected $fillable = [
        'code',
        'generic_name',
        'brand_name',
        'drug_class',
        'dosage_form',
        'strength',
        'unit_price_pkr',
        'stock_quantity',
        'reorder_level',
        'is_high_alert',
        'is_active',
        'added_by_user_id',
        'is_hospital_formulary',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_pkr' => 'float',
            'stock_quantity' => 'integer',
            'reorder_level' => 'integer',
            'is_high_alert' => 'boolean',
            'is_active' => 'boolean',
            'is_hospital_formulary' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->reorder_level;
    }
}
