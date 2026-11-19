<?php

declare(strict_types=1);

namespace App\Modules\Terminology\Models;

use Illuminate\Database\Eloquent\Model;

class TerminologyCode extends Model
{
    public const SYSTEM_ICD10 = 'ICD-10';

    public const SYSTEM_ICD11 = 'ICD-11';

    protected $fillable = [
        'coding_system',
        'terminology_version',
        'code',
        'display_name',
        'normalized_display',
        'chapter',
        'aliases',
        'is_cardiology_core',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'is_cardiology_core' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $code): void {
            $code->normalized_display = strtolower(trim($code->display_name));
        });
    }
}
