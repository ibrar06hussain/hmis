<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Transformers;

use App\Modules\Patient\Models\Patient;

final class FhirPatientTransformer
{
    /**
     * Transform internal relational Patient aggregate to an HL7 FHIR R4 Patient representation.
     *
     * @return array<string, mixed>
     */
    public function toFhirR4(Patient $patient): array
    {
        $patient->loadMissing(['identifiers', 'contacts', 'mergedInto']);

        $identifiers = [
            [
                'use' => 'usual',
                'type' => [
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                        'code' => 'MR',
                        'display' => 'Medical record number',
                    ]],
                ],
                'system' => (string) config('hmis.fhir.mrn_system', 'urn:oid:2.16.586.1.1001.1'),
                'value' => $patient->mrn,
            ],
        ];

        foreach ($patient->identifiers as $identifier) {
            $system = match ($identifier->system) {
                'cnic' => (string) config('hmis.fhir.cnic_system', 'https://nadra.gov.pk/cnic'),
                'merged_mrn' => (string) config('hmis.fhir.mrn_system', 'urn:oid:2.16.586.1.1001.1'),
                default => 'urn:chg:identifier:'.$identifier->system,
            };

            $identifiers[] = [
                'use' => $identifier->system === 'merged_mrn' ? 'old' : 'secondary',
                'system' => $system,
                'value' => $identifier->value,
            ];
        }

        $telecom = [];
        if ($patient->phone_primary !== null) {
            $telecom[] = ['system' => 'phone', 'value' => $patient->phone_primary, 'use' => 'mobile'];
        }
        if ($patient->phone_secondary !== null) {
            $telecom[] = ['system' => 'phone', 'value' => $patient->phone_secondary, 'use' => 'home'];
        }
        if ($patient->email !== null) {
            $telecom[] = ['system' => 'email', 'value' => $patient->email];
        }

        $contacts = [];
        foreach ($patient->contacts as $contact) {
            $contacts[] = [
                'relationship' => [[
                    'text' => $contact->relationship,
                ]],
                'name' => ['text' => $contact->full_name],
                'telecom' => $contact->phone !== null ? [['system' => 'phone', 'value' => $contact->phone]] : [],
            ];
        }

        $resource = [
            'resourceType' => 'Patient',
            'id' => $patient->uuid,
            'meta' => [
                'versionId' => (string) $patient->version,
                'lastUpdated' => $patient->updated_at?->setTimezone('UTC')->toIso8601String(),
            ],
            'identifier' => $identifiers,
            'active' => $patient->status === Patient::STATUS_ACTIVE,
            'name' => [[
                'use' => $patient->is_unknown_emergency ? 'anonymous' : 'official',
                'text' => $patient->full_name,
            ]],
            'gender' => in_array($patient->sex, ['male', 'female', 'other', 'unknown'], true) ? $patient->sex : 'unknown',
            'address' => [[
                'use' => 'home',
                'line' => $patient->address_line !== null ? [$patient->address_line] : [],
                'city' => $patient->city,
                'district' => $patient->district,
                'state' => $patient->province,
                'country' => $patient->nationality === 'Pakistani' ? 'PK' : $patient->nationality,
            ]],
        ];

        if ($patient->date_of_birth !== null) {
            $resource['birthDate'] = $patient->date_of_birth->format('Y-m-d');
        }

        if ($telecom !== []) {
            $resource['telecom'] = $telecom;
        }

        if ($contacts !== []) {
            $resource['contact'] = $contacts;
        }

        if ($patient->status === Patient::STATUS_MERGED && $patient->mergedInto !== null) {
            $resource['link'] = [[
                'other' => ['reference' => 'Patient/'.$patient->mergedInto->uuid],
                'type' => 'replaced-by',
            ]];
        }

        return $resource;
    }
}
