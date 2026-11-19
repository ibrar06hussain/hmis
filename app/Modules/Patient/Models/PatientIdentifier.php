<?php

declare(strict_types=1);

namespace App\Modules\Patient\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientIdentifier extends Model
{
    protected $fillable = [
        'patient_id',
        'system',
        'value',
        'issuer',
        'is_primary',
        'verified',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified' => 'boolean',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
