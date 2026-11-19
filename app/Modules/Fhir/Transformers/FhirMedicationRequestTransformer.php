<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Transformers;

use App\Modules\Pharmacy\Models\PrescriptionItem;

final class FhirMedicationRequestTransformer
{
    /**
     * Map an internal PrescriptionItem to an HL7 FHIR R4 MedicationRequest resource.
     *
     * @return array<string, mixed>
     */
    public function toFhirR4(PrescriptionItem $item): array
    {
        $item->loadMissing(['medication', 'prescription.patient', 'prescription.encounter', 'prescription.prescribedBy']);
        $rx = $item->prescription;

        $status = match ($item->status) {
            PrescriptionItem::STATUS_DISPENSED => 'completed',
            PrescriptionItem::STATUS_CANCELLED => 'cancelled',
            default => 'active',
        };

        return [
            'resourceType' => 'MedicationRequest',
            'id' => $item->uuid,
            'identifier' => [[
                'system' => 'urn:chg:prescription',
                'value' => $rx->prescription_number,
            ]],
            'status' => $status,
            'intent' => 'order',
            'medicationCodeableConcept' => [
                'coding' => [[
                    'system' => 'urn:chg:formulary',
                    'code' => $item->medication->code,
                    'display' => "{$item->medication->generic_name} {$item->medication->strength} ({$item->medication->dosage_form})",
                ]],
                'text' => "{$item->medication->generic_name} {$item->medication->strength}",
            ],
            'subject' => [
                'reference' => 'Patient/'.$rx->patient->uuid,
                'display' => $rx->patient->full_name,
            ],
            'encounter' => [
                'reference' => 'Encounter/'.$rx->encounter->uuid,
            ],
            'authoredOn' => $rx->prescribed_at?->setTimezone('UTC')->toIso8601String(),
            'dosageInstruction' => [[
                'text' => "{$item->dose} {$item->route} {$item->frequency} for {$item->duration_days} days".($item->instructions ? " — {$item->instructions}" : ''),
            ]],
            'dispenseRequest' => [
                'quantity' => [
                    'value' => $item->quantity_prescribed,
                    'unit' => $item->medication->dosage_form,
                ],
            ],
        ];
    }
}
