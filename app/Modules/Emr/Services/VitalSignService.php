<?php

declare(strict_types=1);

namespace App\Modules\Emr\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\VitalSign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class VitalSignService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Record timestamped vital signs with automatic BMI and cardiac/clinical abnormal flagging.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Encounter $encounter, array $data, User $actor): VitalSign
    {
        $systolic = isset($data['systolic_bp']) && $data['systolic_bp'] !== '' ? (int) $data['systolic_bp'] : null;
        $diastolic = isset($data['diastolic_bp']) && $data['diastolic_bp'] !== '' ? (int) $data['diastolic_bp'] : null;
        $heartRate = isset($data['heart_rate']) && $data['heart_rate'] !== '' ? (int) $data['heart_rate'] : null;
        $respRate = isset($data['respiratory_rate']) && $data['respiratory_rate'] !== '' ? (int) $data['respiratory_rate'] : null;
        $temp = isset($data['temperature_c']) && $data['temperature_c'] !== '' ? (float) $data['temperature_c'] : null;
        $spo2 = isset($data['spo2_percent']) && $data['spo2_percent'] !== '' ? (int) $data['spo2_percent'] : null;
        $weight = isset($data['weight_kg']) && $data['weight_kg'] !== '' ? (float) $data['weight_kg'] : null;
        $height = isset($data['height_cm']) && $data['height_cm'] !== '' ? (float) $data['height_cm'] : null;
        $glucose = isset($data['blood_glucose_mg_dl']) && $data['blood_glucose_mg_dl'] !== '' ? (int) $data['blood_glucose_mg_dl'] : null;
        $painScore = isset($data['pain_score']) && $data['pain_score'] !== '' ? (int) $data['pain_score'] : null;

        $bmi = null;
        if ($weight !== null && $height !== null && $height > 0) {
            $heightMeters = $height / 100;
            $bmi = round($weight / ($heightMeters * $heightMeters), 1);
        }

        $flags = $this->evaluateAbnormalFlags($systolic, $diastolic, $heartRate, $respRate, $temp, $spo2);

        return DB::transaction(function () use (
            $encounter,
            $data,
            $actor,
            $systolic,
            $diastolic,
            $heartRate,
            $respRate,
            $temp,
            $spo2,
            $weight,
            $height,
            $bmi,
            $glucose,
            $painScore,
            $flags
        ): VitalSign {
            $vital = VitalSign::query()->create([
                'patient_id' => $encounter->patient_id,
                'encounter_id' => $encounter->id,
                'recorded_by_user_id' => $actor->id,
                'recorded_at' => Carbon::now('UTC'),
                'systolic_bp' => $systolic,
                'diastolic_bp' => $diastolic,
                'heart_rate' => $heartRate,
                'respiratory_rate' => $respRate,
                'temperature_c' => $temp,
                'spo2_percent' => $spo2,
                'weight_kg' => $weight,
                'height_cm' => $height,
                'bmi' => $bmi,
                'blood_glucose_mg_dl' => $glucose,
                'pain_score' => $painScore,
                'rhythm_note' => $data['rhythm_note'] ?? null,
                'is_abnormal' => $flags !== [],
                'abnormal_flags' => $flags,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLogger->log(
                action: 'emr.vitals.recorded',
                actor: $actor,
                subject: $vital,
                patientId: $encounter->patient_id,
                newValues: [
                    'encounter_id' => $encounter->id,
                    'bp' => $systolic !== null && $diastolic !== null ? "{$systolic}/{$diastolic}" : null,
                    'heart_rate' => $heartRate,
                    'spo2_percent' => $spo2,
                    'is_abnormal' => $vital->is_abnormal,
                    'abnormal_flags' => $flags,
                ],
            );

            return $vital;
        });
    }

    /**
     * @return list<string>
     */
    private function evaluateAbnormalFlags(
        ?int $systolic,
        ?int $diastolic,
        ?int $heartRate,
        ?int $respRate,
        ?float $temp,
        ?int $spo2,
    ): array {
        $flags = [];

        if (($systolic !== null && $systolic >= 180) || ($diastolic !== null && $diastolic >= 120)) {
            $flags[] = 'HYPERTENSIVE_CRISIS';
        } elseif (($systolic !== null && $systolic >= 140) || ($diastolic !== null && $diastolic >= 90)) {
            $flags[] = 'HYPERTENSION';
        } elseif (($systolic !== null && $systolic < 90) || ($diastolic !== null && $diastolic < 60)) {
            $flags[] = 'HYPOTENSION';
        }

        if ($heartRate !== null) {
            if ($heartRate > 100) {
                $flags[] = 'TACHYCARDIA';
            } elseif ($heartRate < 60) {
                $flags[] = 'BRADYCARDIA';
            }
        }

        if ($spo2 !== null && $spo2 < 92) {
            $flags[] = 'HYPOXIA';
        }

        if ($respRate !== null && ($respRate > 22 || $respRate < 12)) {
            $flags[] = 'ABNORMAL_RESPIRATION';
        }

        if ($temp !== null) {
            if ($temp >= 38.0) {
                $flags[] = 'FEVER';
            } elseif ($temp < 35.5) {
                $flags[] = 'HYPOTHERMIA';
            }
        }

        return $flags;
    }
}
