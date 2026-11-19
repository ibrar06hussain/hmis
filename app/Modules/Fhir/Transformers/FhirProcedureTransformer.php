<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Transformers;

use App\Modules\Cardiology\Models\CardiacProcedure;

final class FhirProcedureTransformer
{
    /**
     * Map CardiacProcedure (Cath Lab PCI / Cardiac Surgery) to HL7 FHIR R4 Procedure.
     *
     * @return array<string, mixed>
     */
    public function toFhirR4(CardiacProcedure $procedure): array
    {
        $procedure->loadMissing(['patient', 'encounter', 'leadOperator.user']);

        $status = match ($procedure->status) {
            CardiacProcedure::STATUS_SCHEDULED => 'preparation',
            CardiacProcedure::STATUS_IN_PROGRESS => 'in-progress',
            CardiacProcedure::STATUS_COMPLETED => 'completed',
            CardiacProcedure::STATUS_CANCELLED => 'stopped',
            default => 'unknown',
        };

        $resource = [
            'resourceType' => 'Procedure',
            'id' => $procedure->uuid,
            'identifier' => [[
                'system' => 'urn:chg:procedure',
                'value' => $procedure->procedure_number,
            ]],
            'status' => $status,
            'category' => [
                'text' => str_replace('_', ' ', $procedure->category),
            ],
            'code' => [
                'text' => $procedure->procedure_name,
            ],
            'subject' => [
                'reference' => 'Patient/'.$procedure->patient->uuid,
                'display' => $procedure->patient->full_name,
            ],
            'encounter' => [
                'reference' => 'Encounter/'.$procedure->encounter->uuid,
            ],
            'performedDateTime' => ($procedure->completed_at ?? $procedure->scheduled_at)?->setTimezone('UTC')->toIso8601String(),
        ];

        if ($procedure->leadOperator !== null) {
            $resource['performer'] = [[
                'actor' => [
                    'reference' => 'Practitioner/'.$procedure->leadOperator->uuid,
                    'display' => $procedure->leadOperator->user?->name,
                ],
            ]];
        }

        if ($procedure->operative_findings !== null) {
            $resource['note'] = [['text' => $procedure->operative_findings]];
        }

        return $resource;
    }
}
