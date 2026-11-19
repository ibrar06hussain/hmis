<?php

declare(strict_types=1);

namespace App\Modules\Emergency\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emergency\Models\EmergencyTriage;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Services\EncounterNumberGeneratorService;
use App\Modules\Organization\Models\Department;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EmergencyTriageService
{
    public function __construct(
        private readonly EncounterNumberGeneratorService $encounterNumberGenerator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Triage a patient in the Cardiac Emergency Unit and atomically create an EMER Encounter.
     *
     * @param  array<string, mixed>  $data
     */
    public function triage(array $data, User $actor): EmergencyTriage
    {
        $patient = Patient::query()->findOrFail((int) $data['patient_id'])->canonicalPatient();
        if ($patient->status !== Patient::STATUS_ACTIVE) {
            throw new \DomainException('Cannot triage an inactive or merged patient record.');
        }

        $erDepartment = Department::query()->where('code', 'CARD-ER')->firstOrFail();
        $stemiAlert = (bool) ($data['stemi_alert'] ?? false);
        $chestPain = (bool) ($data['chest_pain_flag'] ?? false) || $stemiAlert;
        $triageLevel = $stemiAlert ? min(2, (int) $data['triage_level']) : (int) $data['triage_level'];

        return DB::transaction(function () use ($data, $actor, $patient, $erDepartment, $stemiAlert, $chestPain, $triageLevel): EmergencyTriage {
            $now = Carbon::now('UTC');

            $encounter = Encounter::query()->create([
                'encounter_number' => $this->encounterNumberGenerator->generate(),
                'patient_id' => $patient->id,
                'department_id' => $erDepartment->id,
                'attending_staff_id' => $actor->staffProfile?->id,
                'class' => 'EMER',
                'status' => 'in_progress',
                'chief_complaint' => trim((string) $data['presenting_complaint']),
                'started_at' => $now,
                'version' => 1,
            ]);

            $triage = EmergencyTriage::query()->create([
                'encounter_id' => $encounter->id,
                'patient_id' => $patient->id,
                'triage_level' => $triageLevel,
                'arrival_mode' => (string) ($data['arrival_mode'] ?? 'walk_in'),
                'chest_pain_flag' => $chestPain,
                'stemi_alert' => $stemiAlert,
                'presenting_complaint' => trim((string) $data['presenting_complaint']),
                'triage_notes' => $data['triage_notes'] ?? null,
                'disposition' => 'pending',
                'triaged_by_user_id' => $actor->id,
                'triaged_at' => $now,
            ]);

            $this->auditLogger->log(
                action: $stemiAlert ? 'emergency.code_stemi_triaged' : 'emergency.triaged',
                actor: $actor,
                subject: $triage,
                patientId: $patient->id,
                newValues: [
                    'encounter_number' => $encounter->encounter_number,
                    'triage_level' => $triageLevel,
                    'chest_pain_flag' => $chestPain,
                    'stemi_alert' => $stemiAlert,
                ],
            );

            return $triage->fresh(['patient', 'encounter', 'triagedBy']);
        });
    }

    public function updateDisposition(EmergencyTriage $triage, string $disposition, User $actor): EmergencyTriage
    {
        return DB::transaction(function () use ($triage, $disposition, $actor): EmergencyTriage {
            $locked = EmergencyTriage::query()->whereKey($triage->id)->lockForUpdate()->firstOrFail();
            $oldDisposition = $locked->disposition;
            $locked->disposition = $disposition;
            $locked->save();

            if (in_array($disposition, ['discharged', 'referred', 'lama', 'expired'], true)) {
                $locked->encounter()->update([
                    'status' => 'finished',
                    'ended_at' => Carbon::now('UTC'),
                ]);
            }

            $this->auditLogger->log(
                action: 'emergency.disposition_updated',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                oldValues: ['disposition' => $oldDisposition],
                newValues: ['disposition' => $disposition],
            );

            return $locked;
        });
    }
}
