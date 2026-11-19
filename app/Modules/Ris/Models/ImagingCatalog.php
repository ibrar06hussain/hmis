<?php

declare(strict_types=1);

namespace App\Modules\Ris\Models;

use Illuminate\Database\Eloquent\Model;

class ImagingCatalog extends Model
{
    protected $table = 'imaging_catalog';

    protected $fillable = [
        'code',
        'name',
        'modality',
        'requires_contrast',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_contrast' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
