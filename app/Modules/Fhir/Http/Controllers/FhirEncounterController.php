<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Fhir\Transformers\FhirEncounterTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FhirEncounterController extends Controller
{
    public function __construct(
        private readonly FhirEncounterTransformer $transformer,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $encounter = Encounter::query()->where('uuid', $uuid)->first();

        if ($encounter === null) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => "Encounter resource '{$uuid}' was not found.",
                ]],
            ], 404, ['Content-Type' => 'application/fhir+json']);
        }

        if (Gate::denies('view', $encounter)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'forbidden',
                    'diagnostics' => 'Authenticated principal is not authorized to read this Encounter resource.',
                ]],
            ], 403, ['Content-Type' => 'application/fhir+json']);
        }

        return response()->json(
            $this->transformer->toFhirR4($encounter),
            200,
            ['Content-Type' => 'application/fhir+json'],
        );
    }
}
