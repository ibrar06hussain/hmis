<?php

declare(strict_types=1);

namespace App\Modules\Emr\Models;

use App\Models\User;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClinicalNote extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_AMENDED = 'amended';

    protected $fillable = [
        'uuid',
        'encounter_id',
        'patient_id',
        'author_staff_id',
        'author_user_id',
        'note_type',
        'status',
        'revision_number',
        'supersedes_note_id',
        'amendment_reason',
        'chief_complaint',
        'history_of_present_illness',
        'past_medical_history',
        'family_history',
        'social_history',
        'medication_history',
        'physical_examination',
        'assessment_and_diagnosis',
        'treatment_plan',
        'follow_up_instructions',
        'referral_notes',
        'structured_payload',
        'signed_by_user_id',
        'signed_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'structured_payload' => 'array',
            'signed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            if (empty($note->uuid)) {
                $note->uuid = (string) Str::uuid7();
            }
        });

        static::updating(function (self $note): void {
            // Defense-in-depth: signed notes cannot have clinical content mutated.
            // Only allowed status transition on a signed note is marking status='amended' when superseded by a new revision.
            $originalStatus = $note->getOriginal('status');
            if ($originalStatus === self::STATUS_SIGNED) {
                $dirtyKeys = array_keys($note->getDirty());
                $allowed = ['status', 'version', 'updated_at'];
                $illegal = array_diff($dirtyKeys, $allowed);
                if ($illegal !== [] || $note->status !== self::STATUS_AMENDED) {
                    throw new \LogicException('Signed clinical notes are immutable. Create an audited amendment revision instead.');
                }
            }

            if ($originalStatus === self::STATUS_AMENDED) {
                throw new \LogicException('Amended historical clinical notes are permanently immutable.');
            }
        });
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function authorStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'author_staff_id');
    }

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }

    public function supersedesNote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_note_id');
    }
}
