<?php

declare(strict_types=1);

namespace App\Modules\Patient\Services;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Billing\Models\FinancialLedgerEntry;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Emergency\Models\EmergencyTriage;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\EncounterDiagnosis;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Emr\Models\VitalSign;
use App\Modules\Ipd\Models\BedAllocation;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Models\Specimen;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Models\PatientContact;
use App\Modules\Patient\Models\PatientIdentifier;
use App\Modules\Patient\Models\PatientMerge;
use App\Modules\Pharmacy\Models\Prescription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PatientMergeService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Merge a duplicate/temporary source patient into the surviving canonical target patient.
     * Preserves every clinical record, stores old MRN as a resolvable alias, and logs full audit history.
     */
    public function merge(Patient $source, Patient $target, User $actor, string $reason): PatientMerge
    {
        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Source and target patients must be different records.');
        }

        return DB::transaction(function () use ($source, $target, $actor, $reason): PatientMerge {
            // Lock in deterministic primary-key order to prevent deadlocks
            $ids = [$source->id, $target->id];
            sort($ids);
            $locked = Patient::query()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            /** @var Patient $lockedSource */
            $lockedSource = $locked->get($source->id);
            /** @var Patient $lockedTarget */
            $lockedTarget = $locked->get($target->id);

            if ($lockedSource->status === Patient::STATUS_MERGED) {
                throw new \DomainException('Source patient has already been merged.');
            }

            if ($lockedTarget->status !== Patient::STATUS_ACTIVE) {
                throw new \DomainException('Target patient must be an active patient record.');
            }

            $movedCounts = [
                'appointments' => Appointment::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'encounters' => Encounter::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'emergency_triages' => EmergencyTriage::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'ipd_admissions' => IpdAdmission::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'bed_allocations' => BedAllocation::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'vital_signs' => VitalSign::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'clinical_notes' => ClinicalNote::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'diagnoses' => EncounterDiagnosis::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'allergies' => PatientAllergy::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'lab_orders' => LabOrder::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'specimens' => Specimen::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'imaging_studies' => ImagingStudy::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'prescriptions' => Prescription::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'invoices' => Invoice::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'financial_ledger_entries' => FinancialLedgerEntry::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id]),
                'contacts' => PatientContact::query()->where('patient_id', $lockedSource->id)->update(['patient_id' => $lockedTarget->id, 'is_primary' => false]),
            ];

            // Move external identifiers without creating duplicate primary CNIC collisions
            $sourceIdentifiers = PatientIdentifier::query()->where('patient_id', $lockedSource->id)->get();
            foreach ($sourceIdentifiers as $identifier) {
                $existsOnTarget = PatientIdentifier::query()
                    ->where('patient_id', $lockedTarget->id)
                    ->where('system', $identifier->system)
                    ->where('value', $identifier->value)
                    ->exists();

                if (! $existsOnTarget) {
                    $identifier->patient_id = $lockedTarget->id;
                    $identifier->is_primary = false;
                    $identifier->save();
                }
            }

            // Preserve source MRN as a historical alias on the surviving target patient
            PatientIdentifier::query()->create([
                'patient_id' => $lockedTarget->id,
                'system' => 'merged_mrn',
                'value' => $lockedSource->mrn,
                'issuer' => config('hmis.facility_name', 'Cardiac Hospital Gilgit'),
                'is_primary' => false,
                'verified' => true,
                'valid_from' => $lockedSource->created_at ?? Carbon::now('UTC'),
                'valid_to' => Carbon::now('UTC'),
            ]);

            // Copy missing CNIC/phone from source to target if target lacks it
            if ($lockedTarget->cnic_normalized === null && $lockedSource->cnic_normalized !== null) {
                $lockedTarget->cnic_normalized = $lockedSource->cnic_normalized;
                $lockedTarget->identity_verification_status = 'verified';
                $lockedTarget->version = $lockedTarget->version + 1;
                $lockedTarget->save();
            }

            $sourceSnapshot = [
                'source_mrn' => $lockedSource->mrn,
                'source_full_name' => $lockedSource->full_name,
                'source_cnic' => $lockedSource->cnic_normalized,
                'target_mrn' => $lockedTarget->mrn,
                'moved_records' => $movedCounts,
            ];

            // Mark source record as merged (never hard-delete)
            $lockedSource->status = Patient::STATUS_MERGED;
            $lockedSource->merged_into_patient_id = $lockedTarget->id;
            $lockedSource->version = $lockedSource->version + 1;
            $lockedSource->save();

            $mergeRecord = PatientMerge::query()->create([
                'source_patient_id' => $lockedSource->id,
                'target_patient_id' => $lockedTarget->id,
                'merged_by_user_id' => $actor->id,
                'reason' => trim($reason),
                'snapshot_metadata' => $sourceSnapshot,
                'merged_at' => Carbon::now('UTC'),
            ]);

            $this->auditLogger->log(
                action: 'patient.merged',
                actor: $actor,
                subject: $mergeRecord,
                patientId: $lockedTarget->id,
                oldValues: ['source_patient_id' => $lockedSource->id, 'source_mrn' => $lockedSource->mrn],
                newValues: $sourceSnapshot,
                reason: $reason,
            );

            return $mergeRecord;
        });
    }
}
