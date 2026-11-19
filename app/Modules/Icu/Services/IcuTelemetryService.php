<?php

declare(strict_types=1);

namespace App\Modules\Icu\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Icu\Models\IcuDeviceObservation;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Organization\Models\Bed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class IcuTelemetryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Ingest a timestamped ICU bedside monitor / ventilator / infusion pump reading.
     * ALWAYS enters as 'unverified_raw' — never silently treated as a verified clinical observation.
     *
     * @param  array<string, mixed>  $data
     */
    public function ingestForBed(Bed $bed, array $data): IcuDeviceObservation
    {
        $admission = IpdAdmission::query()
            ->where('bed_id', $bed->id)
            ->where('status', IpdAdmission::STATUS_ADMITTED)
            ->first();

        if ($admission === null) {
            throw new \DomainException("No active admitted patient is currently assigned to bed {$bed->bed_number}.");
        }

        $obsType = (string) $data['observation_type'];
        $value = isset($data['value_numeric']) && $data['value_numeric'] !== '' ? (float) $data['value_numeric'] : null;
        $quality = (string) ($data['data_quality'] ?? 'good');
        $isAbnormal = $this->isAbnormal($obsType, $value, $quality);

        return DB::transaction(function () use ($bed, $admission, $data, $obsType, $value, $quality, $isAbnormal): IcuDeviceObservation {
            $obs = IcuDeviceObservation::query()->create([
                'patient_id' => $admission->patient_id,
                'encounter_id' => $admission->encounter_id,
                'bed_id' => $bed->id,
                'device_code' => trim((string) $data['device_code']),
                'observation_type' => $obsType,
                'value_numeric' => $value,
                'unit' => $data['unit'] ?? null,
                'data_quality' => $quality,
                'is_abnormal' => $isAbnormal,
                'verification_status' => IcuDeviceObservation::STATUS_UNVERIFIED_RAW,
                'waveform_reference' => $data['waveform_reference'] ?? null,
                'observed_at' => ! empty($data['observed_at']) ? Carbon::parse((string) $data['observed_at']) : Carbon::now('UTC'),
            ]);

            $this->auditLogger->log(
                action: $quality === 'sensor_disconnected' ? 'icu.device.disconnected' : 'icu.telemetry.ingested_raw',
                actor: null,
                subject: $obs,
                patientId: $admission->patient_id,
                newValues: [
                    'bed_number' => $bed->bed_number,
                    'device_code' => $obs->device_code,
                    'observation_type' => $obsType,
                    'value' => $value,
                    'data_quality' => $quality,
                    'is_abnormal' => $isAbnormal,
                ],
            );

            return $obs;
        });
    }

    public function verify(IcuDeviceObservation $observation, User $clinician): IcuDeviceObservation
    {
        return DB::transaction(function () use ($observation, $clinician): IcuDeviceObservation {
            $locked = IcuDeviceObservation::query()->whereKey($observation->id)->lockForUpdate()->firstOrFail();

            $locked->verification_status = IcuDeviceObservation::STATUS_CLINICIAN_VERIFIED;
            $locked->verified_by_user_id = $clinician->id;
            $locked->verified_at = Carbon::now('UTC');
            $locked->save();

            $this->auditLogger->log(
                action: 'icu.telemetry.verified',
                actor: $clinician,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: ['observation_type' => $locked->observation_type, 'value' => $locked->value_numeric],
            );

            return $locked;
        });
    }

    public function rejectArtifact(IcuDeviceObservation $observation, User $clinician): IcuDeviceObservation
    {
        return DB::transaction(function () use ($observation, $clinician): IcuDeviceObservation {
            $locked = IcuDeviceObservation::query()->whereKey($observation->id)->lockForUpdate()->firstOrFail();

            $locked->verification_status = IcuDeviceObservation::STATUS_REJECTED_ARTIFACT;
            $locked->verified_by_user_id = $clinician->id;
            $locked->verified_at = Carbon::now('UTC');
            $locked->save();

            $this->auditLogger->log(
                action: 'icu.telemetry.rejected_artifact',
                actor: $clinician,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: ['observation_type' => $locked->observation_type, 'value' => $locked->value_numeric],
            );

            return $locked;
        });
    }

    private function isAbnormal(string $type, ?float $value, string $quality): bool
    {
        if ($quality === 'sensor_disconnected') {
            return true;
        }
        if ($value === null) {
            return false;
        }

        return match ($type) {
            'heart_rate' => $value < 50 || $value > 120,
            'spo2' => $value < 90,
            'nibp_systolic' => $value < 90 || $value >= 180,
            'art_map' => $value < 65 || $value > 110,
            'resp_rate' => $value < 10 || $value > 26,
            default => false,
        };
    }
}
