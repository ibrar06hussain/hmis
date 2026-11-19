<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Services;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Services\EncounterNumberGeneratorService;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AppointmentService
{
    public function __construct(
        private readonly EncounterNumberGeneratorService $encounterNumberGenerator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Book an OPD appointment with concurrency-safe sequential token allocation per department/date.
     *
     * @param  array<string, mixed>  $data
     */
    public function book(array $data, User $actor): Appointment
    {
        $patient = Patient::query()->findOrFail((int) $data['patient_id'])->canonicalPatient();
        if ($patient->status !== Patient::STATUS_ACTIVE) {
            throw new \DomainException('Cannot book an appointment for an inactive or deceased patient.');
        }

        $departmentId = (int) $data['department_id'];
        $appointmentDate = Carbon::parse((string) $data['appointment_date'])->toDateString();

        return DB::transaction(function () use ($data, $actor, $patient, $departmentId, $appointmentDate): Appointment {
            $maxToken = (int) Appointment::query()
                ->where('department_id', $departmentId)
                ->whereDate('appointment_date', $appointmentDate)
                ->lockForUpdate()
                ->max('token_number');

            $appointment = Appointment::query()->create([
                'patient_id' => $patient->id,
                'department_id' => $departmentId,
                'clinician_staff_id' => ! empty($data['clinician_staff_id']) ? (int) $data['clinician_staff_id'] : null,
                'appointment_date' => $appointmentDate,
                'token_number' => $maxToken + 1,
                'visit_type' => (string) ($data['visit_type'] ?? 'new'),
                'status' => Appointment::STATUS_BOOKED,
                'chief_complaint' => isset($data['chief_complaint']) && trim((string) $data['chief_complaint']) !== ''
                    ? trim((string) $data['chief_complaint'])
                    : null,
                'created_by_user_id' => $actor->id,
                'version' => 1,
            ]);

            $this->auditLogger->log(
                action: 'appointment.booked',
                actor: $actor,
                subject: $appointment,
                patientId: $patient->id,
                newValues: [
                    'appointment_date' => $appointmentDate,
                    'department_id' => $departmentId,
                    'token_number' => $appointment->token_number,
                    'visit_type' => $appointment->visit_type,
                ],
            );

            return $appointment->fresh(['patient', 'department', 'clinician.user']);
        });
    }

    /**
     * Check in a booked patient and atomically create an AMB OPD Encounter.
     */
    public function checkIn(Appointment $appointment, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor): Appointment {
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Appointment::STATUS_BOOKED) {
                throw new \DomainException("Only 'booked' appointments can be checked in (current status: {$locked->status}).");
            }

            $now = Carbon::now('UTC');

            $encounter = Encounter::query()->create([
                'encounter_number' => $this->encounterNumberGenerator->generate(),
                'patient_id' => $locked->patient_id,
                'department_id' => $locked->department_id,
                'attending_staff_id' => $locked->clinician_staff_id,
                'class' => 'AMB',
                'status' => 'arrived',
                'chief_complaint' => $locked->chief_complaint,
                'started_at' => $now,
                'version' => 1,
            ]);

            $locked->status = Appointment::STATUS_CHECKED_IN;
            $locked->encounter_id = $encounter->id;
            $locked->checked_in_at = $now;
            $locked->version = $locked->version + 1;
            $locked->save();

            $this->auditLogger->log(
                action: 'appointment.checked_in',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: [
                    'encounter_id' => $encounter->id,
                    'encounter_number' => $encounter->encounter_number,
                    'token_number' => $locked->token_number,
                ],
            );

            return $locked->fresh(['patient', 'department', 'clinician.user', 'encounter']);
        });
    }

    public function startConsultation(Appointment $appointment, User $actor): Encounter
    {
        return DB::transaction(function () use ($appointment, $actor): Encounter {
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [Appointment::STATUS_CHECKED_IN, Appointment::STATUS_IN_CONSULT], true)) {
                throw new \DomainException('Patient must be checked in before starting consultation.');
            }

            $encounter = Encounter::query()->whereKey($locked->encounter_id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Appointment::STATUS_CHECKED_IN) {
                $locked->status = Appointment::STATUS_IN_CONSULT;
                $locked->version = $locked->version + 1;
                $locked->save();

                $encounter->status = 'in_progress';
                if ($encounter->attending_staff_id === null && $actor->staffProfile !== null) {
                    $encounter->attending_staff_id = $actor->staffProfile->id;
                }
                $encounter->version = $encounter->version + 1;
                $encounter->save();

                $this->auditLogger->log(
                    action: 'opd.consultation_started',
                    actor: $actor,
                    subject: $encounter,
                    patientId: $encounter->patient_id,
                    newValues: ['encounter_number' => $encounter->encounter_number],
                );
            }

            return $encounter->fresh(['patient', 'department', 'attendingStaff.user']);
        });
    }

    public function completeConsultation(Encounter $encounter, User $actor): Encounter
    {
        return DB::transaction(function () use ($encounter, $actor): Encounter {
            $lockedEncounter = Encounter::query()->whereKey($encounter->id)->lockForUpdate()->firstOrFail();

            if ($lockedEncounter->status === 'finished') {
                return $lockedEncounter;
            }

            $lockedEncounter->status = 'finished';
            $lockedEncounter->ended_at = Carbon::now('UTC');
            $lockedEncounter->version = $lockedEncounter->version + 1;
            $lockedEncounter->save();

            $appointment = Appointment::query()->where('encounter_id', $lockedEncounter->id)->lockForUpdate()->first();
            if ($appointment !== null && $appointment->status !== Appointment::STATUS_COMPLETED) {
                $appointment->status = Appointment::STATUS_COMPLETED;
                $appointment->version = $appointment->version + 1;
                $appointment->save();
            }

            $this->auditLogger->log(
                action: 'opd.consultation_completed',
                actor: $actor,
                subject: $lockedEncounter,
                patientId: $lockedEncounter->patient_id,
                newValues: ['encounter_number' => $lockedEncounter->encounter_number],
            );

            return $lockedEncounter;
        });
    }

    public function cancel(Appointment $appointment, User $actor, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor, $reason): Appointment {
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELLED], true)) {
                throw new \DomainException("Appointment in status '{$locked->status}' cannot be cancelled.");
            }

            $oldStatus = $locked->status;
            $locked->status = Appointment::STATUS_CANCELLED;
            $locked->version = $locked->version + 1;
            $locked->save();

            if ($locked->encounter_id !== null) {
                Encounter::query()
                    ->whereKey($locked->encounter_id)
                    ->where('status', '!=', 'finished')
                    ->update(['status' => 'cancelled']);
            }

            $this->auditLogger->log(
                action: 'appointment.cancelled',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => Appointment::STATUS_CANCELLED],
                reason: $reason,
            );

            return $locked;
        });
    }
}
