<?php

declare(strict_types=1);

namespace App\Modules\Emr\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AllergyService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Patient $patient, array $data, User $actor): PatientAllergy
    {
        return DB::transaction(function () use ($patient, $data, $actor): PatientAllergy {
            $allergy = PatientAllergy::query()->create([
                'patient_id' => $patient->id,
                'substance' => trim((string) $data['substance']),
                'category' => (string) ($data['category'] ?? 'medication'),
                'severity' => (string) ($data['severity'] ?? 'moderate'),
                'reaction' => isset($data['reaction']) && trim((string) $data['reaction']) !== ''
                    ? trim((string) $data['reaction'])
                    : null,
                'clinical_status' => 'active',
                'recorded_by_user_id' => $actor->id,
                'recorded_at' => Carbon::now('UTC'),
            ]);

            $this->auditLogger->log(
                action: 'emr.allergy.recorded',
                actor: $actor,
                subject: $allergy,
                patientId: $patient->id,
                newValues: [
                    'substance' => $allergy->substance,
                    'category' => $allergy->category,
                    'severity' => $allergy->severity,
                ],
            );

            return $allergy;
        });
    }
}
