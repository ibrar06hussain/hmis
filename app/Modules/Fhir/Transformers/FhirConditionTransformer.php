<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Transformers;

use App\Modules\Emr\Models\EncounterDiagnosis;

final class FhirConditionTransformer
{
    /**
     * Transform version-locked EncounterDiagnosis to an HL7 FHIR R4 Condition resource.
     *
     * @return array<string, mixed>
     */
    public function toFhirR4(EncounterDiagnosis $diagnosis): array
    {
        $diagnosis->loadMissing(['patient', 'encounter', 'recordedBy']);

        $systemUri = match ($diagnosis->coding_system) {
            'ICD-10' => 'http://hl7.org/fhir/sid/icd-10',
            'ICD-11' => 'http://id.who.int/icd/release/11/mms',
            default => 'urn:chg:terminology:'.$diagnosis->coding_system,
        };

        $verificationStatus = match ($diagnosis->clinical_status) {
            'provisional' => 'provisional',
            'ruled_out' => 'refuted',
            default => 'confirmed',
        };

        $clinicalStatus = $diagnosis->clinical_status === 'resolved' ? 'resolved' : 'active';

        return [
            'resourceType' => 'Condition',
            'id' => $diagnosis->uuid,
            'clinicalStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code' => $clinicalStatus,
                ]],
            ],
            'verificationStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                    'code' => $verificationStatus,
                ]],
            ],
            'category' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-category',
                    'code' => 'encounter-diagnosis',
                    'display' => ucfirst($diagnosis->diagnosis_rank).' Diagnosis',
                ]],
            ]],
            'code' => [
                'coding' => [[
                    'system' => $systemUri,
                    'version' => $diagnosis->terminology_version,
                    'code' => $diagnosis->code,
                    'display' => $diagnosis->display_name,
                ]],
                'text' => $diagnosis->display_name,
            ],
            'subject' => [
                'reference' => 'Patient/'.$diagnosis->patient->uuid,
                'display' => $diagnosis->patient->full_name,
            ],
            'encounter' => [
                'reference' => 'Encounter/'.$diagnosis->encounter->uuid,
            ],
            'recordedDate' => $diagnosis->recorded_at?->setTimezone('UTC')->toIso8601String(),
        ];
    }
}
