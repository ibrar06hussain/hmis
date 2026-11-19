<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Transformers;

use App\Modules\Emr\Models\Encounter;

final class FhirEncounterTransformer
{
    /**
     * Transform internal Encounter to an HL7 FHIR R4 Encounter representation.
     *
     * @return array<string, mixed>
     */
    public function toFhirR4(Encounter $encounter): array
    {
        $encounter->loadMissing(['patient', 'department', 'attendingStaff.user']);

        $fhirStatus = match ($encounter->status) {
            'planned' => 'planned',
            'arrived' => 'arrived',
            'in_progress' => 'in-progress',
            'finished' => 'finished',
            'cancelled' => 'cancelled',
            default => 'unknown',
        };

        $classDisplay = match ($encounter->class) {
            'AMB' => 'ambulatory',
            'EMER' => 'emergency',
            'IMP' => 'inpatient encounter',
            'CARD_PROC' => 'cardiac procedure',
            default => $encounter->class,
        };

        $resource = [
            'resourceType' => 'Encounter',
            'id' => $encounter->uuid,
            'meta' => [
                'versionId' => (string) $encounter->version,
                'lastUpdated' => $encounter->updated_at?->setTimezone('UTC')->toIso8601String(),
            ],
            'identifier' => [[
                'system' => 'urn:chg:encounter',
                'value' => $encounter->encounter_number,
            ]],
            'status' => $fhirStatus,
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => $encounter->class,
                'display' => $classDisplay,
            ],
            'subject' => [
                'reference' => 'Patient/'.$encounter->patient->uuid,
                'display' => $encounter->patient->full_name,
            ],
            'serviceProvider' => $encounter->department !== null ? [
                'reference' => 'Organization/'.$encounter->department->uuid,
                'display' => $encounter->department->name,
            ] : null,
            'period' => array_filter([
                'start' => $encounter->started_at?->setTimezone('UTC')->toIso8601String(),
                'end' => $encounter->ended_at?->setTimezone('UTC')->toIso8601String(),
            ]),
        ];

        if ($encounter->attendingStaff !== null) {
            $resource['participant'] = [[
                'individual' => [
                    'reference' => 'Practitioner/'.$encounter->attendingStaff->uuid,
                    'display' => $encounter->attendingStaff->user?->name,
                ],
            ]];
        }

        if ($encounter->chief_complaint !== null) {
            $resource['reasonCode'] = [[
                'text' => $encounter->chief_complaint,
            ]];
        }

        return array_filter($resource, fn (mixed $v): bool => $v !== null);
    }
}
