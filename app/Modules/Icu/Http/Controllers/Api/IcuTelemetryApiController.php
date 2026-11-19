<?php

declare(strict_types=1);

namespace App\Modules\Icu\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Icu\Services\IcuTelemetryService;
use App\Modules\Organization\Models\Bed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IcuTelemetryApiController extends Controller
{
    public function __construct(
        private readonly IcuTelemetryService $telemetryService,
    ) {}

    /**
     * Authenticated LAN bedside device telemetry gateway.
     * Ingested observations ALWAYS remain 'unverified_raw' until verified by an authorized clinician/nurse.
     */
    public function ingest(Request $request): JsonResponse
    {
        if (! $request->user()?->can('emr.vitals.record') && ! $request->user()?->can('iam.manage_users')) {
            return response()->json(['message' => 'Forbidden: ICU telemetry ingestion requires clinical or device gateway privileges.'], 403);
        }

        $validated = $request->validate([
            'bed_number' => ['required', 'string', 'exists:beds,bed_number'],
            'device_code' => ['required', 'string', 'max:48'],
            'observation_type' => ['required', Rule::in(['heart_rate', 'spo2', 'nibp_systolic', 'nibp_diastolic', 'art_map', 'resp_rate', 'ventilator_fio2', 'infusion_rate'])],
            'value_numeric' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:32'],
            'data_quality' => ['nullable', Rule::in(['good', 'artifact_suspect', 'sensor_disconnected'])],
            'waveform_reference' => ['nullable', 'string', 'max:160'],
            'observed_at' => ['nullable', 'date'],
        ]);

        $bed = Bed::query()->where('bed_number', $validated['bed_number'])->firstOrFail();

        try {
            $obs = $this->telemetryService->ingestForBed($bed, $validated);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'uuid' => $obs->uuid,
            'verification_status' => $obs->verification_status,
            'is_abnormal' => $obs->is_abnormal,
            'data_quality' => $obs->data_quality,
        ], 202);
    }
}
