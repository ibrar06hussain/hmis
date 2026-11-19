<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Nursing\Models\NursingMarRecord;
use App\Modules\Nursing\Models\NursingNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class NursingService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Record a bedside Medication Administration Record (MAR) event.
     * Held or refused doses require a mandatory clinical reason.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordMar(Encounter $encounter, array $data, User $nurse): NursingMarRecord
    {
        $status = (string) ($data['status'] ?? NursingMarRecord::STATUS_ADMINISTERED);
        $reason = isset($data['clinical_reason']) ? trim((string) $data['clinical_reason']) : null;

        if (in_array($status, [NursingMarRecord::STATUS_HELD, NursingMarRecord::STATUS_REFUSED], true) && ($reason === null || $reason === '')) {
            throw new \InvalidArgumentException("A clinical reason is mandatory when marking a medication dose as '{$status}'.");
        }

        return DB::transaction(function () use ($encounter, $data, $status, $reason, $nurse): NursingMarRecord {
            $now = Carbon::now('UTC');

            $record = NursingMarRecord::query()->create([
                'patient_id' => $encounter->patient_id,
                'encounter_id' => $encounter->id,
                'ipd_admission_id' => $encounter->ipdAdmission?->id,
                'prescription_item_id' => ! empty($data['prescription_item_id']) ? (int) $data['prescription_item_id'] : null,
                'medication_name' => trim((string) $data['medication_name']),
                'dose_given' => trim((string) $data['dose_given']),
                'route' => (string) ($data['route'] ?? 'PO'),
                'scheduled_for' => ! empty($data['scheduled_for']) ? Carbon::parse((string) $data['scheduled_for']) : null,
                'administered_at' => $now,
                'status' => $status,
                'clinical_reason' => $reason ?: null,
                'administered_by_user_id' => $nurse->id,
            ]);

            $this->auditLogger->log(
                action: 'nursing.mar.recorded',
                actor: $nurse,
                subject: $record,
                patientId: $encounter->patient_id,
                newValues: [
                    'medication_name' => $record->medication_name,
                    'dose_given' => $record->dose_given,
                    'route' => $record->route,
                    'status' => $status,
                ],
                reason: $reason,
            );

            return $record;
        });
    }

    /**
     * Record a bedside nursing assessment, shift handover, or intake/output fluid balance note.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordNote(Encounter $encounter, array $data, User $nurse): NursingNote
    {
        return DB::transaction(function () use ($encounter, $data, $nurse): NursingNote {
            $note = NursingNote::query()->create([
                'patient_id' => $encounter->patient_id,
                'encounter_id' => $encounter->id,
                'ipd_admission_id' => $encounter->ipdAdmission?->id,
                'shift' => (string) ($data['shift'] ?? 'morning'),
                'note_category' => (string) ($data['note_category'] ?? 'assessment'),
                'intake_ml' => isset($data['intake_ml']) && $data['intake_ml'] !== '' ? (int) $data['intake_ml'] : null,
                'output_ml' => isset($data['output_ml']) && $data['output_ml'] !== '' ? (int) $data['output_ml'] : null,
                'content' => trim((string) $data['content']),
                'recorded_by_user_id' => $nurse->id,
                'recorded_at' => Carbon::now('UTC'),
            ]);

            $this->auditLogger->log(
                action: 'nursing.note.recorded',
                actor: $nurse,
                subject: $note,
                patientId: $encounter->patient_id,
                newValues: [
                    'shift' => $note->shift,
                    'note_category' => $note->note_category,
                    'net_fluid_ml' => $note->netFluidBalanceMl(),
                ],
            );

            return $note;
        });
    }
}
