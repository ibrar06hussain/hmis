<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cardiology\Models\CardiacProcedure;
use App\Modules\Fhir\Transformers\FhirProcedureTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FhirProcedureController extends Controller
{
    public function __construct(
        private readonly FhirProcedureTransformer $transformer,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $procedure = CardiacProcedure::query()->with('encounter')->where('uuid', $uuid)->first();

        if ($procedure === null) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => "Procedure resource '{$uuid}' was not found.",
                ]],
            ], 404, ['Content-Type' => 'application/fhir+json']);
        }

        if (Gate::denies('view', $procedure->encounter)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'forbidden',
                    'diagnostics' => 'Authenticated principal is not authorized to read this Procedure resource.',
                ]],
            ], 403, ['Content-Type' => 'application/fhir+json']);
        }

        return response()->json(
            $this->transformer->toFhirR4($procedure),
            200,
            ['Content-Type' => 'application/fhir+json'],
        );
    }
}
