<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Emr\Models\EncounterDiagnosis;
use App\Modules\Fhir\Transformers\FhirConditionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FhirConditionController extends Controller
{
    public function __construct(
        private readonly FhirConditionTransformer $transformer,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $diagnosis = EncounterDiagnosis::query()->with('encounter')->where('uuid', $uuid)->first();

        if ($diagnosis === null) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => "Condition resource '{$uuid}' was not found.",
                ]],
            ], 404, ['Content-Type' => 'application/fhir+json']);
        }

        if (Gate::denies('view', $diagnosis->encounter)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'forbidden',
                    'diagnostics' => 'Authenticated principal is not authorized to read this Condition resource.',
                ]],
            ], 403, ['Content-Type' => 'application/fhir+json']);
        }

        return response()->json(
            $this->transformer->toFhirR4($diagnosis),
            200,
            ['Content-Type' => 'application/fhir+json'],
        );
    }
}
