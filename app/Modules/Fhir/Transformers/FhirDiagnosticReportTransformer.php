<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Transformers;

use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Models\LabOrderItem;

final class FhirDiagnosticReportTransformer
{
    /**
     * Map a VERIFIED LabOrder to an HL7 FHIR R4 DiagnosticReport.
     *
     * @return array<string, mixed>
     */
    public function toFhirR4(LabOrder $order): array
    {
        $order->loadMissing(['patient', 'encounter', 'items.test', 'verifiedBy']);

        $containedObservations = [];
        $resultRefs = [];

        foreach ($order->items->where('status', LabOrderItem::STATUS_VERIFIED) as $item) {
            $obsId = 'obs-'.$item->id;
            $resultRefs[] = ['reference' => '#'.$obsId, 'display' => $item->test->name];

            $obs = [
                'resourceType' => 'Observation',
                'id' => $obsId,
                'status' => 'final',
                'code' => [
                    'coding' => array_values(array_filter([
                        $item->test->loinc_code ? [
                            'system' => 'http://loinc.org',
                            'code' => $item->test->loinc_code,
                            'display' => $item->test->name,
                        ] : null,
                        [
                            'system' => 'urn:chg:lab-test',
                            'code' => $item->test->code,
                            'display' => $item->test->name,
                        ],
                    ])),
                ],
                'subject' => ['reference' => 'Patient/'.$order->patient->uuid],
            ];

            if ($item->result_value_numeric !== null) {
                $obs['valueQuantity'] = [
                    'value' => $item->result_value_numeric,
                    'unit' => $item->unit,
                    'system' => 'http://unitsofmeasure.org',
                ];
            } else {
                $obs['valueString'] = $item->result_value_text;
            }

            if ($item->abnormal_flag && $item->abnormal_flag !== 'normal') {
                $obs['interpretation'] = [[
                    'text' => strtoupper($item->abnormal_flag),
                ]];
            }

            $containedObservations[] = $obs;
        }

        return [
            'resourceType' => 'DiagnosticReport',
            'id' => $order->uuid,
            'identifier' => [[
                'system' => 'urn:chg:lab-order',
                'value' => $order->order_number,
            ]],
            'status' => 'final',
            'category' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/v2-0074',
                    'code' => 'LAB',
                    'display' => 'Laboratory',
                ]],
            ]],
            'code' => [
                'text' => 'Laboratory Investigation Report ('.$order->order_number.')',
            ],
            'subject' => [
                'reference' => 'Patient/'.$order->patient->uuid,
                'display' => $order->patient->full_name,
            ],
            'encounter' => $order->encounter !== null ? [
                'reference' => 'Encounter/'.$order->encounter->uuid,
            ] : null,
            'issued' => $order->verified_at?->setTimezone('UTC')->toIso8601String(),
            'contained' => $containedObservations,
            'result' => $resultRefs,
        ];
    }
}
