<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fhir\Transformers\FhirMedicationRequestTransformer;
use App\Modules\Pharmacy\Models\PrescriptionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FhirMedicationRequestController extends Controller
{
    public function __construct(
        private readonly FhirMedicationRequestTransformer $transformer,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $item = PrescriptionItem::query()->with(['prescription.encounter', 'medication'])->where('uuid', $uuid)->first();

        if ($item === null) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => "MedicationRequest '{$uuid}' was not found.",
                ]],
            ], 404, ['Content-Type' => 'application/fhir+json']);
        }

        if (Gate::denies('view', $item->prescription->encounter)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'forbidden',
                    'diagnostics' => 'Authenticated principal is not authorized to read this MedicationRequest.',
                ]],
            ], 403, ['Content-Type' => 'application/fhir+json']);
        }

        return response()->json(
            $this->transformer->toFhirR4($item),
            200,
            ['Content-Type' => 'application/fhir+json'],
        );
    }
}
