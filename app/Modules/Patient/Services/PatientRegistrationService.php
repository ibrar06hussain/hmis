<?php

declare(strict_types=1);

namespace App\Modules\Patient\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Exceptions\DuplicatePatientException;
use App\Modules\Patient\Models\Patient;
use App\Support\CnicNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PatientRegistrationService
{
    public function __construct(
        private readonly MrnGeneratorService $mrnGenerator,
        private readonly DuplicateDetectionService $duplicateDetector,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Register a new patient (standard or emergency fast-track without CNIC).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DuplicatePatientException
     */
    public function register(array $data, User $actor, bool $confirmDistinct = false): Patient
    {
        $registrationType = (string) ($data['registration_type'] ?? Patient::REG_STANDARD);
        $isUnknownEmergency = (bool) ($data['is_unknown_emergency'] ?? false);

        // Enforce duplicate detection unless explicitly acknowledged (and hard CNIC duplicates require override permission)
        if (! $isUnknownEmergency) {
            $candidates = $this->duplicateDetector->findCandidates($data);
            $hasHardMatch = $this->duplicateDetector->hasHardMatch($candidates);

            if ($candidates->isNotEmpty()) {
                if ($hasHardMatch && (! $confirmDistinct || ! $actor->can('patient.register_override_duplicate'))) {
                    throw new DuplicatePatientException(
                        $candidates,
                        'An active patient with the exact same CNIC already exists. Open the existing patient or use authorized duplicate override.'
                    );
                }

                if (! $hasHardMatch && ! $confirmDistinct) {
                    throw new DuplicatePatientException($candidates);
                }
            }
        }

        $cnicNormalized = CnicNormalizer::normalize(isset($data['cnic']) ? (string) $data['cnic'] : null);
        $facility = Facility::query()->where('code', config('hmis.facility_code', 'CHG'))->first();

        $dob = $data['date_of_birth'] ?? null;
        $dobEstimated = (bool) ($data['dob_estimated'] ?? false);
        if (($dob === null || $dob === '') && isset($data['estimated_age_years']) && $data['estimated_age_years'] !== '' && $data['estimated_age_years'] !== null) {
            $dob = Carbon::now('UTC')->subYears((int) $data['estimated_age_years'])->startOfYear()->toDateString();
            $dobEstimated = true;
        }

        $identityStatus = match (true) {
            $isUnknownEmergency => 'unverified',
            $registrationType === Patient::REG_EMERGENCY && $cnicNormalized === null => 'unverified',
            $cnicNormalized === null => 'pending_cnic',
            default => 'verified',
        };

        return DB::transaction(function () use (
            $data,
            $actor,
            $registrationType,
            $isUnknownEmergency,
            $cnicNormalized,
            $facility,
            $dob,
            $dobEstimated,
            $identityStatus
        ): Patient {
            $mrn = $this->mrnGenerator->generate($facility?->code);

            $patient = Patient::query()->create([
                'mrn' => $mrn,
                'status' => Patient::STATUS_ACTIVE,
                'registered_facility_id' => $facility?->id,
                'registered_by_user_id' => $actor->id,
                'full_name' => trim((string) $data['full_name']),
                'father_or_spouse_name' => isset($data['father_or_spouse_name']) && trim((string) $data['father_or_spouse_name']) !== ''
                    ? trim((string) $data['father_or_spouse_name'])
                    : null,
                'date_of_birth' => $dob ?: null,
                'dob_estimated' => $dobEstimated,
                'sex' => (string) ($data['sex'] ?? 'unknown'),
                'blood_group' => $data['blood_group'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'nationality' => (string) ($data['nationality'] ?? 'Pakistani'),
                'cnic_normalized' => $cnicNormalized,
                'phone_primary' => isset($data['phone_primary']) && trim((string) $data['phone_primary']) !== ''
                    ? trim((string) $data['phone_primary'])
                    : null,
                'phone_secondary' => isset($data['phone_secondary']) && trim((string) $data['phone_secondary']) !== ''
                    ? trim((string) $data['phone_secondary'])
                    : null,
                'email' => $data['email'] ?? null,
                'address_line' => $data['address_line'] ?? null,
                'city' => $data['city'] ?? null,
                'tehsil' => $data['tehsil'] ?? null,
                'district' => $data['district'] ?? 'Gilgit',
                'province' => (string) ($data['province'] ?? 'Gilgit-Baltistan'),
                'registration_type' => $registrationType,
                'is_unknown_emergency' => $isUnknownEmergency,
                'identity_verification_status' => $identityStatus,
                'registration_notes' => $data['registration_notes'] ?? null,
                'version' => 1,
            ]);

            if ($cnicNormalized !== null) {
                $patient->identifiers()->create([
                    'system' => 'cnic',
                    'value' => $cnicNormalized,
                    'issuer' => 'NADRA',
                    'is_primary' => true,
                    'verified' => $identityStatus === 'verified',
                    'valid_from' => Carbon::now('UTC'),
                ]);
            }

            if (! empty($data['sehat_card_number'])) {
                $patient->identifiers()->create([
                    'system' => 'sehat_card',
                    'value' => trim((string) $data['sehat_card_number']),
                    'issuer' => 'Sehat Sahulat Program',
                    'is_primary' => false,
                    'verified' => false,
                    'valid_from' => Carbon::now('UTC'),
                ]);
            }

            if (! empty($data['emergency_contact_name'])) {
                $patient->contacts()->create([
                    'contact_type' => (string) ($data['emergency_contact_type'] ?? 'emergency'),
                    'full_name' => trim((string) $data['emergency_contact_name']),
                    'relationship' => trim((string) ($data['emergency_contact_relationship'] ?? 'Relative')),
                    'phone' => isset($data['emergency_contact_phone']) ? trim((string) $data['emergency_contact_phone']) : null,
                    'cnic_normalized' => CnicNormalizer::normalize(isset($data['emergency_contact_cnic']) ? (string) $data['emergency_contact_cnic'] : null),
                    'address' => $data['emergency_contact_address'] ?? null,
                    'is_primary' => true,
                ]);
            }

            $this->auditLogger->log(
                action: $registrationType === Patient::REG_EMERGENCY ? 'patient.registered_emergency' : 'patient.registered',
                actor: $actor,
                subject: $patient,
                patientId: $patient->id,
                newValues: [
                    'mrn' => $patient->mrn,
                    'full_name' => $patient->full_name,
                    'registration_type' => $patient->registration_type,
                    'identity_verification_status' => $patient->identity_verification_status,
                    'has_cnic' => $cnicNormalized !== null,
                ],
            );

            return $patient->fresh(['identifiers', 'contacts']);
        });
    }

    /**
     * Update mutable patient demographics (MRN is immutable).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDemographics(Patient $patient, array $data, User $actor): Patient
    {
        if ($patient->status === Patient::STATUS_MERGED) {
            throw new \DomainException('Cannot edit demographics of a merged patient record.');
        }

        $cnicNormalized = array_key_exists('cnic', $data)
            ? CnicNormalizer::normalize($data['cnic'] !== null ? (string) $data['cnic'] : null)
            : $patient->cnic_normalized;

        return DB::transaction(function () use ($patient, $data, $actor, $cnicNormalized): Patient {
            $locked = Patient::query()->whereKey($patient->id)->lockForUpdate()->firstOrFail();

            $oldSnapshot = $locked->only([
                'full_name',
                'father_or_spouse_name',
                'date_of_birth',
                'sex',
                'blood_group',
                'cnic_normalized',
                'phone_primary',
                'district',
                'identity_verification_status',
            ]);

            $locked->fill([
                'full_name' => trim((string) ($data['full_name'] ?? $locked->full_name)),
                'father_or_spouse_name' => $data['father_or_spouse_name'] ?? $locked->father_or_spouse_name,
                'date_of_birth' => $data['date_of_birth'] ?? $locked->date_of_birth,
                'dob_estimated' => (bool) ($data['dob_estimated'] ?? $locked->dob_estimated),
                'sex' => (string) ($data['sex'] ?? $locked->sex),
                'blood_group' => $data['blood_group'] ?? $locked->blood_group,
                'marital_status' => $data['marital_status'] ?? $locked->marital_status,
                'occupation' => $data['occupation'] ?? $locked->occupation,
                'cnic_normalized' => $cnicNormalized,
                'phone_primary' => $data['phone_primary'] ?? $locked->phone_primary,
                'phone_secondary' => $data['phone_secondary'] ?? $locked->phone_secondary,
                'email' => $data['email'] ?? $locked->email,
                'address_line' => $data['address_line'] ?? $locked->address_line,
                'city' => $data['city'] ?? $locked->city,
                'tehsil' => $data['tehsil'] ?? $locked->tehsil,
                'district' => $data['district'] ?? $locked->district,
                'province' => $data['province'] ?? $locked->province,
                'is_unknown_emergency' => (bool) ($data['is_unknown_emergency'] ?? $locked->is_unknown_emergency),
                'identity_verification_status' => $cnicNormalized !== null ? 'verified' : ($data['identity_verification_status'] ?? $locked->identity_verification_status),
                'registration_notes' => $data['registration_notes'] ?? $locked->registration_notes,
                'version' => $locked->version + 1,
            ]);
            $locked->save();

            if ($cnicNormalized !== null) {
                $locked->identifiers()->updateOrCreate(
                    ['system' => 'cnic', 'is_primary' => true],
                    [
                        'value' => $cnicNormalized,
                        'issuer' => 'NADRA',
                        'verified' => true,
                        'valid_from' => Carbon::now('UTC'),
                    ]
                );
            }

            $this->auditLogger->log(
                action: 'patient.demographics_updated',
                actor: $actor,
                subject: $locked,
                patientId: $locked->id,
                oldValues: $oldSnapshot,
                newValues: $locked->only(array_keys($oldSnapshot)),
            );

            return $locked->fresh(['identifiers', 'contacts']);
        });
    }
}
