<?php

declare(strict_types=1);

namespace App\Modules\Lis\Models;

use Illuminate\Database\Eloquent\Model;

class LabTestCatalog extends Model
{
    public const RESULT_TYPE_NUMERIC = 'numeric';

    public const RESULT_TYPE_SELECT = 'select';

    public const RESULT_TYPE_POSITIVE_NEGATIVE = 'positive_negative';

    public const RESULT_TYPE_TEXT = 'text';

    protected $table = 'lab_test_catalog';

    protected $fillable = [
        'code',
        'name',
        'loinc_code',
        'section',
        'panel_code',
        'panel_name',
        'specimen_type',
        'result_type',
        'reporting_options',
        'normal_text',
        'method_name',
        'unit',
        'ref_low_male',
        'ref_high_male',
        'ref_low_female',
        'ref_high_female',
        'critical_low',
        'critical_high',
        'turnaround_minutes',
        'price_pkr',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reporting_options' => 'array',
            'ref_low_male' => 'float',
            'ref_high_male' => 'float',
            'ref_low_female' => 'float',
            'ref_high_female' => 'float',
            'critical_low' => 'float',
            'critical_high' => 'float',
            'turnaround_minutes' => 'integer',
            'price_pkr' => 'float',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isQualitative(): bool
    {
        return in_array($this->result_type, [self::RESULT_TYPE_SELECT, self::RESULT_TYPE_POSITIVE_NEGATIVE, self::RESULT_TYPE_TEXT], true);
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

    public function referenceDisplayForSex(string $sex): string
    {
        if ($this->isQualitative()) {
            return $this->normal_text ?: (is_array($this->reporting_options) && $this->reporting_options !== [] ? (string) $this->reporting_options[0] : 'Qualitative');
        }

        [$low, $high] = $this->referenceRangeForSex($sex);
        if ($low === null && $high === null) {
            return '—';
        }

        return trim(($low ?? '—').' – '.($high ?? '—').' '.($this->unit ?? ''));
    }
}
