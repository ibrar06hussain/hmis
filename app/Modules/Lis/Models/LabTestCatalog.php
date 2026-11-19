<?php

declare(strict_types=1);

namespace App\Modules\Lis\Models;

use Illuminate\Database\Eloquent\Model;

class LabTestCatalog extends Model
{
    protected $table = 'lab_test_catalog';

    protected $fillable = [
        'code',
        'name',
        'loinc_code',
        'section',
        'specimen_type',
        'unit',
        'ref_low_male',
        'ref_high_male',
        'ref_low_female',
        'ref_high_female',
        'critical_low',
        'critical_high',
        'turnaround_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ref_low_male' => 'float',
            'ref_high_male' => 'float',
            'ref_low_female' => 'float',
            'ref_high_female' => 'float',
            'critical_low' => 'float',
            'critical_high' => 'float',
            'turnaround_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    public function referenceRangeForSex(string $sex): array
    {
        if ($sex === 'female') {
            return [$this->ref_low_female ?? $this->ref_low_male, $this->ref_high_female ?? $this->ref_high_male];
        }

        return [$this->ref_low_male, $this->ref_high_male];
    }
}
