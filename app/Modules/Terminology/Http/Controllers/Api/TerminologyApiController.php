<?php

declare(strict_types=1);

namespace App\Modules\Terminology\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Terminology\Models\TerminologyCode;
use App\Modules\Terminology\Services\TerminologySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminologyApiController extends Controller
{
    public function __construct(
        private readonly TerminologySearchService $searchService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $codes = $this->searchService->search(
            query: $request->string('q')->toString(),
            system: $request->string('system')->toString() ?: null,
            limit: min(50, max(1, $request->integer('limit', 20))),
        );

        return response()->json([
            'data' => $codes->map(fn (TerminologyCode $code): array => [
                'id' => $code->id,
                'coding_system' => $code->coding_system,
                'terminology_version' => $code->terminology_version,
                'code' => $code->code,
                'display_name' => $code->display_name,
                'chapter' => $code->chapter,
                'aliases' => $code->aliases ?? [],
                'is_cardiology_core' => $code->is_cardiology_core,
            ])->values(),
        ]);
    }
}
