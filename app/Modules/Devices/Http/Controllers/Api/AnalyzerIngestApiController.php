<?php

declare(strict_types=1);

namespace App\Modules\Devices\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Devices\Models\AnalyzerDevice;
use App\Modules\Devices\Services\AnalyzerIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyzerIngestApiController extends Controller
{
    public function __construct(
        private readonly AnalyzerIngestionService $ingestionService,
    ) {}

    /**
     * Authenticated LAN gateway endpoint for laboratory analyzer payloads.
     * Stages parsed values into unverified LIS observations; NEVER auto-publishes.
     */
    public function ingest(Request $request): JsonResponse
    {
        if (! $request->user()?->can('lis.result.enter')) {
            return response()->json(['message' => 'Forbidden: LIS analyzer ingestion requires laboratory authorization.'], 403);
        }

        $validated = $request->validate([
            'device_code' => ['required', 'string', 'exists:analyzer_devices,device_code'],
            'raw_payload' => ['required', 'string', 'min:10'],
        ]);

        $device = AnalyzerDevice::query()->where('device_code', $validated['device_code'])->where('is_active', true)->firstOrFail();

        $result = $this->ingestionService->ingest($device, (string) $validated['raw_payload']);

        $statusCode = match ($result['status']) {
            'staged' => 202,
            'duplicate' => 200,
            default => 422,
        };

        return response()->json($result, $statusCode);
    }
}
