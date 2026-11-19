<?php

declare(strict_types=1);

namespace App\Modules\Emr\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\Encounter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ClinicalNoteService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Create a structured clinical note draft (OPD / Cardiology template).
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraft(Encounter $encounter, array $data, User $actor): ClinicalNote
    {
        return DB::transaction(function () use ($encounter, $data, $actor): ClinicalNote {
            $note = ClinicalNote::query()->create([
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'author_staff_id' => $actor->staffProfile?->id,
                'author_user_id' => $actor->id,
                'note_type' => (string) ($data['note_type'] ?? 'cardiology_eval'),
                'status' => ClinicalNote::STATUS_DRAFT,
                'revision_number' => 1,
                'chief_complaint' => $data['chief_complaint'] ?? null,
                'history_of_present_illness' => $data['history_of_present_illness'] ?? null,
                'past_medical_history' => $data['past_medical_history'] ?? null,
                'family_history' => $data['family_history'] ?? null,
                'social_history' => $data['social_history'] ?? null,
                'medication_history' => $data['medication_history'] ?? null,
                'physical_examination' => $data['physical_examination'] ?? null,
                'assessment_and_diagnosis' => $data['assessment_and_diagnosis'] ?? null,
                'treatment_plan' => $data['treatment_plan'] ?? null,
                'follow_up_instructions' => $data['follow_up_instructions'] ?? null,
                'referral_notes' => $data['referral_notes'] ?? null,
                'structured_payload' => $this->extractCardiologyPayload($data),
                'version' => 1,
            ]);

            $this->auditLogger->log(
                action: 'emr.note.draft_created',
                actor: $actor,
                subject: $note,
                patientId: $encounter->patient_id,
                newValues: [
                    'encounter_id' => $encounter->id,
                    'note_type' => $note->note_type,
                    'revision_number' => 1,
                ],
            );

            return $note;
        });
    }

    /**
     * Update an unsigned draft clinical note. Signed/amended notes are strictly rejected.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(ClinicalNote $note, array $data, User $actor): ClinicalNote
    {
        return DB::transaction(function () use ($note, $data, $actor): ClinicalNote {
            $locked = ClinicalNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ClinicalNote::STATUS_DRAFT) {
                throw new \DomainException('Signed or amended clinical notes cannot be modified directly. Submit an amendment revision instead.');
            }

            $locked->fill([
                'chief_complaint' => $data['chief_complaint'] ?? $locked->chief_complaint,
                'history_of_present_illness' => $data['history_of_present_illness'] ?? $locked->history_of_present_illness,
                'past_medical_history' => $data['past_medical_history'] ?? $locked->past_medical_history,
                'family_history' => $data['family_history'] ?? $locked->family_history,
                'social_history' => $data['social_history'] ?? $locked->social_history,
                'medication_history' => $data['medication_history'] ?? $locked->medication_history,
                'physical_examination' => $data['physical_examination'] ?? $locked->physical_examination,
                'assessment_and_diagnosis' => $data['assessment_and_diagnosis'] ?? $locked->assessment_and_diagnosis,
                'treatment_plan' => $data['treatment_plan'] ?? $locked->treatment_plan,
                'follow_up_instructions' => $data['follow_up_instructions'] ?? $locked->follow_up_instructions,
                'referral_notes' => $data['referral_notes'] ?? $locked->referral_notes,
                'structured_payload' => $this->extractCardiologyPayload($data, $locked->structured_payload ?? []),
                'version' => $locked->version + 1,
            ]);
            $locked->save();

            $this->auditLogger->log(
                action: 'emr.note.draft_updated',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: ['version' => $locked->version],
            );

            return $locked;
        });
    }

    /**
     * Digitally sign and lock a draft clinical note.
     */
    public function sign(ClinicalNote $note, User $signer): ClinicalNote
    {
        return DB::transaction(function () use ($note, $signer): ClinicalNote {
            $locked = ClinicalNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ClinicalNote::STATUS_DRAFT) {
                throw new \DomainException('Only draft clinical notes can be signed.');
            }

            $locked->status = ClinicalNote::STATUS_SIGNED;
            $locked->signed_by_user_id = $signer->id;
            $locked->signed_at = Carbon::now('UTC');
            $locked->version = $locked->version + 1;
            $locked->save();

            $this->auditLogger->log(
                action: 'emr.note.signed',
                actor: $signer,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: [
                    'note_uuid' => $locked->uuid,
                    'revision_number' => $locked->revision_number,
                    'signed_at' => $locked->signed_at->toIso8601String(),
                ],
            );

            return $locked;
        });
    }

    /**
     * Create an audited amendment revision superseding a signed clinical note.
     * Original signed note content remains permanently preserved in the database.
     *
     * @param  array<string, mixed>  $data
     */
    public function amend(ClinicalNote $signedNote, array $data, string $reason, User $actor): ClinicalNote
    {
        return DB::transaction(function () use ($signedNote, $data, $reason, $actor): ClinicalNote {
            $original = ClinicalNote::query()->whereKey($signedNote->id)->lockForUpdate()->firstOrFail();

            if ($original->status !== ClinicalNote::STATUS_SIGNED) {
                throw new \DomainException('Only the latest signed clinical note revision can be amended.');
            }

            // Transition original to 'amended' (clinical text untouched and immutable)
            $original->status = ClinicalNote::STATUS_AMENDED;
            $original->version = $original->version + 1;
            $original->save();

            $now = Carbon::now('UTC');

            $amendedRevision = ClinicalNote::query()->create([
                'encounter_id' => $original->encounter_id,
                'patient_id' => $original->patient_id,
                'author_staff_id' => $actor->staffProfile?->id,
                'author_user_id' => $actor->id,
                'note_type' => $original->note_type,
                'status' => ClinicalNote::STATUS_SIGNED,
                'revision_number' => $original->revision_number + 1,
                'supersedes_note_id' => $original->id,
                'amendment_reason' => trim($reason),
                'chief_complaint' => $data['chief_complaint'] ?? $original->chief_complaint,
                'history_of_present_illness' => $data['history_of_present_illness'] ?? $original->history_of_present_illness,
                'past_medical_history' => $data['past_medical_history'] ?? $original->past_medical_history,
                'family_history' => $data['family_history'] ?? $original->family_history,
                'social_history' => $data['social_history'] ?? $original->social_history,
                'medication_history' => $data['medication_history'] ?? $original->medication_history,
                'physical_examination' => $data['physical_examination'] ?? $original->physical_examination,
                'assessment_and_diagnosis' => $data['assessment_and_diagnosis'] ?? $original->assessment_and_diagnosis,
                'treatment_plan' => $data['treatment_plan'] ?? $original->treatment_plan,
                'follow_up_instructions' => $data['follow_up_instructions'] ?? $original->follow_up_instructions,
                'referral_notes' => $data['referral_notes'] ?? $original->referral_notes,
                'structured_payload' => $this->extractCardiologyPayload($data, $original->structured_payload ?? []),
                'signed_by_user_id' => $actor->id,
                'signed_at' => $now,
                'version' => 1,
            ]);

            $this->auditLogger->log(
                action: 'emr.note.amended',
                actor: $actor,
                subject: $amendedRevision,
                patientId: $original->patient_id,
                oldValues: [
                    'supersedes_note_id' => $original->id,
                    'previous_revision' => $original->revision_number,
                ],
                newValues: [
                    'new_note_id' => $amendedRevision->id,
                    'revision_number' => $amendedRevision->revision_number,
                ],
                reason: $reason,
            );

            return $amendedRevision;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function extractCardiologyPayload(array $data, array $existing = []): array
    {
        return array_filter([
            'nyha_class' => $data['nyha_class'] ?? ($existing['nyha_class'] ?? null),
            'chest_pain_type' => $data['chest_pain_type'] ?? ($existing['chest_pain_type'] ?? null),
            'cardiac_risk_factors' => $data['cardiac_risk_factors'] ?? ($existing['cardiac_risk_factors'] ?? []),
            'ecg_summary' => $data['ecg_summary'] ?? ($existing['ecg_summary'] ?? null),
        ], fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    }
}
