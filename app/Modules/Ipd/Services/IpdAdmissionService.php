<?php

declare(strict_types=1);

namespace App\Modules\Ipd\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Services\EncounterNumberGeneratorService;
use App\Modules\Ipd\Models\AdmissionSequence;
use App\Modules\Ipd\Models\BedAllocation;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Organization\Models\Bed;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class IpdAdmissionService
{
    public function __construct(
        private readonly EncounterNumberGeneratorService $encounterNumberGenerator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Admit a patient to a specific available ward bed inside a locked ACID transaction.
     * Prevents bed double-booking and concurrent duplicate active admissions.
     *
     * @param  array<string, mixed>  $data
     */
    public function admit(array $data, User $actor): IpdAdmission
    {
        $patient = Patient::query()->findOrFail((int) $data['patient_id'])->canonicalPatient();
        if ($patient->status !== Patient::STATUS_ACTIVE) {
            throw new \DomainException('Cannot admit an inactive or merged patient.');
        }

        return DB::transaction(function () use ($data, $actor, $patient): IpdAdmission {
            $alreadyAdmitted = IpdAdmission::query()
                ->where('patient_id', $patient->id)
                ->where('status', IpdAdmission::STATUS_ADMITTED)
                ->lockForUpdate()
                ->exists();

            if ($alreadyAdmitted) {
                throw new \DomainException("Patient {$patient->mrn} already has an active inpatient admission.");
            }

            /** @var Bed $bed */
            $bed = Bed::query()->with('ward')->whereKey((int) $data['bed_id'])->lockForUpdate()->firstOrFail();

            if ($bed->status !== 'available') {
                throw new \DomainException("Bed conflict: Bed {$bed->bed_number} is currently '{$bed->status}' and cannot be allocated.");
            }

            $now = Carbon::now('UTC');
            $ward = $bed->ward;

            $encounter = Encounter::query()->create([
                'encounter_number' => $this->encounterNumberGenerator->generate(),
                'patient_id' => $patient->id,
                'department_id' => $ward->department_id,
                'attending_staff_id' => ! empty($data['attending_staff_id']) ? (int) $data['attending_staff_id'] : $actor->staffProfile?->id,
                'class' => 'IMP',
                'status' => 'in_progress',
                'chief_complaint' => trim((string) $data['admission_diagnosis']),
                'started_at' => $now,
                'version' => 1,
            ]);

            $admissionNumber = $this->generateAdmissionNumber();

            $admission = IpdAdmission::query()->create([
                'admission_number' => $admissionNumber,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'ward_id' => $ward->id,
                'bed_id' => $bed->id,
                'admitting_department_id' => $ward->department_id,
                'attending_staff_id' => $encounter->attending_staff_id,
                'admission_type' => (string) ($data['admission_type'] ?? 'emergency'),
                'status' => IpdAdmission::STATUS_ADMITTED,
                'admission_diagnosis' => trim((string) $data['admission_diagnosis']),
                'admitted_at' => $now,
                'admitted_by_user_id' => $actor->id,
                'version' => 1,
            ]);

            $bed->status = 'occupied';
            $bed->version = $bed->version + 1;
            $bed->save();

            BedAllocation::query()->create([
                'admission_id' => $admission->id,
                'patient_id' => $patient->id,
                'ward_id' => $ward->id,
                'bed_id' => $bed->id,
                'allocated_at' => $now,
                'allocated_by_user_id' => $actor->id,
                'transfer_reason' => 'Initial admission',
            ]);

            $this->auditLogger->log(
                action: 'ipd.admitted',
                actor: $actor,
                subject: $admission,
                patientId: $patient->id,
                newValues: [
                    'admission_number' => $admissionNumber,
                    'encounter_number' => $encounter->encounter_number,
                    'ward_code' => $ward->code,
                    'bed_number' => $bed->bed_number,
                ],
            );

            return $admission->fresh(['patient', 'ward', 'bed', 'encounter', 'attendingStaff.user']);
        });
    }

    /**
     * Transfer an admitted patient to a different available bed (deadlock-free deterministic locking).
     * Old bed transitions to 'cleaning' per clinical infection-control protocol.
     */
    public function transferBed(IpdAdmission $admission, Bed $targetBed, User $actor, string $reason): IpdAdmission
    {
        return DB::transaction(function () use ($admission, $targetBed, $actor, $reason): IpdAdmission {
            $lockedAdmission = IpdAdmission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();

            if ($lockedAdmission->status !== IpdAdmission::STATUS_ADMITTED) {
                throw new \DomainException('Only active admitted patients can be transferred to another bed.');
            }

            if ($lockedAdmission->bed_id === $targetBed->id) {
                throw new \InvalidArgumentException('Target bed must be different from the current bed.');
            }

            $bedIds = array_values(array_filter([$lockedAdmission->bed_id, $targetBed->id]));
            sort($bedIds);
            $lockedBeds = Bed::query()->with('ward')->whereIn('id', $bedIds)->lockForUpdate()->get()->keyBy('id');

            /** @var Bed $newBed */
            $newBed = $lockedBeds->get($targetBed->id);
            if ($newBed->status !== 'available') {
                throw new \DomainException("Bed conflict: Target bed {$newBed->bed_number} is currently '{$newBed->status}'.");
            }

            $now = Carbon::now('UTC');
            $oldBedNumber = null;

            if ($lockedAdmission->bed_id !== null && $lockedBeds->has($lockedAdmission->bed_id)) {
                /** @var Bed $oldBed */
                $oldBed = $lockedBeds->get($lockedAdmission->bed_id);
                $oldBedNumber = $oldBed->bed_number;
                $oldBed->status = 'cleaning';
                $oldBed->version = $oldBed->version + 1;
                $oldBed->save();

                BedAllocation::query()
                    ->where('admission_id', $lockedAdmission->id)
                    ->whereNull('released_at')
                    ->update(['released_at' => $now]);
            }

            $newBed->status = 'occupied';
            $newBed->version = $newBed->version + 1;
            $newBed->save();

            $lockedAdmission->ward_id = $newBed->ward_id;
            $lockedAdmission->bed_id = $newBed->id;
            $lockedAdmission->version = $lockedAdmission->version + 1;
            $lockedAdmission->save();

            // Update encounter department if transferred across clinical units (e.g., ER/Ward -> CCU)
            $lockedAdmission->encounter()->update(['department_id' => $newBed->ward->department_id]);

            BedAllocation::query()->create([
                'admission_id' => $lockedAdmission->id,
                'patient_id' => $lockedAdmission->patient_id,
                'ward_id' => $newBed->ward_id,
                'bed_id' => $newBed->id,
                'allocated_at' => $now,
                'allocated_by_user_id' => $actor->id,
                'transfer_reason' => trim($reason),
            ]);

            $this->auditLogger->log(
                action: 'ipd.bed_transferred',
                actor: $actor,
                subject: $lockedAdmission,
                patientId: $lockedAdmission->patient_id,
                oldValues: ['bed_number' => $oldBedNumber],
                newValues: ['bed_number' => $newBed->bed_number, 'ward_code' => $newBed->ward->code],
                reason: $reason,
            );

            return $lockedAdmission->fresh(['ward', 'bed', 'patient', 'encounter']);
        });
    }

    /**
     * Discharge an inpatient with structured discharge summary; releases bed to 'cleaning' state.
     *
     * @param  array<string, mixed>  $data
     */
    public function discharge(IpdAdmission $admission, array $data, User $actor): IpdAdmission
    {
        return DB::transaction(function () use ($admission, $data, $actor): IpdAdmission {
            $locked = IpdAdmission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== IpdAdmission::STATUS_ADMITTED) {
                throw new \DomainException('Patient admission is already closed.');
            }

            $now = Carbon::now('UTC');
            $finalStatus = (string) ($data['discharge_status'] ?? IpdAdmission::STATUS_DISCHARGED);

            if ($locked->bed_id !== null) {
                $bed = Bed::query()->whereKey($locked->bed_id)->lockForUpdate()->first();
                if ($bed !== null) {
                    $bed->status = 'cleaning';
                    $bed->version = $bed->version + 1;
                    $bed->save();
                }

                BedAllocation::query()
                    ->where('admission_id', $locked->id)
                    ->whereNull('released_at')
                    ->update(['released_at' => $now]);
            }

            $locked->status = $finalStatus;
            $locked->discharged_at = $now;
            $locked->discharge_condition = (string) ($data['discharge_condition'] ?? 'stable');
            $locked->discharge_summary = trim((string) $data['discharge_summary']);
            $locked->follow_up_plan = $data['follow_up_plan'] ?? null;
            $locked->discharged_by_user_id = $actor->id;
            $locked->version = $locked->version + 1;
            $locked->save();

            $locked->encounter()->update([
                'status' => 'finished',
                'ended_at' => $now,
            ]);

            $this->auditLogger->log(
                action: 'ipd.discharged',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: [
                    'admission_number' => $locked->admission_number,
                    'status' => $finalStatus,
                    'discharge_condition' => $locked->discharge_condition,
                ],
            );

            return $locked->fresh(['patient', 'ward', 'bed', 'encounter']);
        });
    }

    private function generateAdmissionNumber(): string
    {
        $code = strtoupper((string) config('hmis.facility_code', 'CHG'));
        $year = (int) Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->format('Y');

        $row = AdmissionSequence::query()
            ->where('facility_code', $code)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            try {
                AdmissionSequence::query()->create([
                    'facility_code' => $code,
                    'year' => $year,
                    'last_sequence' => 1,
                ]);

                return sprintf('ADM-%04d-%06d', $year, 1);
            } catch (QueryException) {
                $row = AdmissionSequence::query()
                    ->where('facility_code', $code)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $row->last_sequence = $row->last_sequence + 1;
        $row->save();

        return sprintf('ADM-%04d-%06d', $year, $row->last_sequence);
    }
}
