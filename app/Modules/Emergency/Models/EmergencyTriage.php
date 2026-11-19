<?php

declare(strict_types=1);

namespace App\Modules\Emergency\Models;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmergencyTriage extends Model
{
    public const LEVEL_LABELS = [
        1 => 'Level 1 — Resuscitation (Immediate)',
        2 => 'Level 2 — Emergent (<= 10 min)',
        3 => 'Level 3 — Urgent (<= 30 min)',
        4 => 'Level 4 — Less Urgent',
        5 => 'Level 5 — Non-Urgent',
    ];

    protected $fillable = [
        'uuid',
        'encounter_id',
        'patient_id',
        'triage_level',
        'arrival_mode',
        'chest_pain_flag',
        'stemi_alert',
        'presenting_complaint',
        'triage_notes',
        'disposition',
        'triaged_by_user_id',
        'triaged_at',
    ];

    protected function casts(): array
    {
        return [
            'triage_level' => 'integer',
            'chest_pain_flag' => 'boolean',
            'stemi_alert' => 'boolean',
            'triaged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $triage): void {
            if (empty($triage->uuid)) {
                $triage->uuid = (string) Str::uuid7();
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

    public function triagedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by_user_id');
    }

    public function levelLabel(): string
    {
        return self::LEVEL_LABELS[$this->triage_level] ?? 'Level '.$this->triage_level;
    }
}
