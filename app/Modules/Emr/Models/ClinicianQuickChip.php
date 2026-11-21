<?php

declare(strict_types=1);

namespace App\Modules\Emr\Models;

use App\Models\User;
use App\Modules\Terminology\Models\TerminologyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicianQuickChip extends Model
{
    public const CATEGORY_SYMPTOM = 'symptom';

    public const CATEGORY_EXAM = 'exam';

    public const CATEGORY_ECG = 'ecg';

    public const CATEGORY_DIAGNOSIS_SHORTCUT = 'diagnosis_shortcut';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_SYMPTOM,
        self::CATEGORY_EXAM,
        self::CATEGORY_ECG,
        self::CATEGORY_DIAGNOSIS_SHORTCUT,
    ];

    protected $fillable = [
        'user_id',
        'category',
        'label',
        'value',
        'terminology_code_id',
        'is_hidden',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function terminologyCode(): BelongsTo
    {
        return $this->belongsTo(TerminologyCode::class);
    }
}
