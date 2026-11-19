<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fhir\Transformers\FhirPatientTransformer;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FhirPatientController extends Controller
{
    public function __construct(
        private readonly FhirPatientTransformer $transformer,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $patient = Patient::query()->where('uuid', $uuid)->first();

        if ($patient === null) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => "Patient resource '{$uuid}' was not found.",
                ]],
            ], 404, ['Content-Type' => 'application/fhir+json']);
        }

        if (Gate::denies('view', $patient)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'forbidden',
                    'diagnostics' => 'Authenticated principal is not authorized to read this Patient resource.',
                ]],
            ], 403, ['Content-Type' => 'application/fhir+json']);
        }

        return response()->json(
            $this->transformer->toFhirR4($patient),
            200,
            ['Content-Type' => 'application/fhir+json'],
        );
    }
}
