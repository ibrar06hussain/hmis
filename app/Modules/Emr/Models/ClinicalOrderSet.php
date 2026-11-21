<?php

declare(strict_types=1);

namespace App\Modules\Emr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClinicalOrderSet extends Model
{
    public const CATEGORY_CARDIAC_PROTOCOL = 'cardiac_protocol';

    public const CATEGORY_PERSONAL_FAVORITE = 'personal_favorite';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'category',
        'description',
        'owner_user_id',
        'is_shared',
        'default_note_type',
        'default_nyha_class',
        'default_chest_pain_type',
        'default_exam_text',
        'default_assessment_text',
        'default_plan_text',
        'default_follow_up',
        'diagnosis_codes',
        'medications',
        'lab_test_codes',
        'imaging_codes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'is_active' => 'boolean',
            'diagnosis_codes' => 'array',
            'medications' => 'array',
            'lab_test_codes' => 'array',
            'imaging_codes' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $set): void {
            if (empty($set->uuid)) {
                $set->uuid = (string) Str::uuid7();
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
