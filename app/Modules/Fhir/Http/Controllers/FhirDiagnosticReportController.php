<?php

declare(strict_types=1);

namespace App\Modules\Fhir\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fhir\Transformers\FhirDiagnosticReportTransformer;
use App\Modules\Lis\Models\LabOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FhirDiagnosticReportController extends Controller
{
    public function __construct(
        private readonly FhirDiagnosticReportTransformer $transformer,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $order = LabOrder::query()->with(['patient', 'encounter'])->where('uuid', $uuid)->first();

        if ($order === null) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => "DiagnosticReport '{$uuid}' was not found.",
                ]],
            ], 404, ['Content-Type' => 'application/fhir+json']);
        }

        if ($order->encounter !== null && Gate::denies('view', $order->encounter)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'forbidden',
                    'diagnostics' => 'Authenticated principal is not authorized to read this DiagnosticReport.',
                ]],
            ], 403, ['Content-Type' => 'application/fhir+json']);
        }

        // Clinical Safety Gate: Never publish unverified lab results over FHIR as final reports
        if ($order->status !== LabOrder::STATUS_VERIFIED) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'processing',
                    'diagnostics' => 'DiagnosticReport is not yet verified by an authorized laboratory verifier.',
                ]],
            ], 409, ['Content-Type' => 'application/fhir+json']);
        }

        return response()->json(
            $this->transformer->toFhirR4($order),
            200,
            ['Content-Type' => 'application/fhir+json'],
        );
    }
}
