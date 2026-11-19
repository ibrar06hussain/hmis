<?php

declare(strict_types=1);

namespace App\Modules\Emr\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\EncounterDiagnosis;
use App\Modules\Terminology\Models\TerminologyCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EncounterDiagnosisService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Record a version-locked ICD-10 / ICD-11 diagnosis onto an encounter.
     * Copies code, version, and display_name at recording time so future ICD updates never alter history.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Encounter $encounter, array $data, User $actor): EncounterDiagnosis
    {
        $term = TerminologyCode::query()->findOrFail((int) $data['terminology_code_id']);

        return DB::transaction(function () use ($encounter, $data, $actor, $term): EncounterDiagnosis {
            $diagnosis = EncounterDiagnosis::query()->create([
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'terminology_code_id' => $term->id,
                'coding_system' => $term->coding_system,
                'terminology_version' => $term->terminology_version,
                'code' => $term->code,
                'display_name' => $term->display_name,
                'diagnosis_rank' => (string) ($data['diagnosis_rank'] ?? 'primary'),
                'clinical_status' => (string) ($data['clinical_status'] ?? 'confirmed'),
                'clinical_context' => isset($data['clinical_context']) && trim((string) $data['clinical_context']) !== ''
                    ? trim((string) $data['clinical_context'])
                    : null,
                'recorded_by_user_id' => $actor->id,
                'recorded_at' => Carbon::now('UTC'),
            ]);

            $this->auditLogger->log(
                action: 'emr.diagnosis.recorded',
                actor: $actor,
                subject: $diagnosis,
                patientId: $encounter->patient_id,
                newValues: [
                    'encounter_id' => $encounter->id,
                    'coding_system' => $term->coding_system,
                    'terminology_version' => $term->terminology_version,
                    'code' => $term->code,
                    'diagnosis_rank' => $diagnosis->diagnosis_rank,
                ],
            );

            return $diagnosis;
        });
    }
}
