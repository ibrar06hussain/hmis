<?php

declare(strict_types=1);

namespace App\Modules\Patient\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Patient\Exceptions\DuplicatePatientException;
use App\Modules\Patient\Http\Requests\StorePatientRequest;
use App\Modules\Patient\Http\Resources\PatientResource;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\DuplicateDetectionService;
use App\Modules\Patient\Services\PatientRegistrationService;
use App\Modules\Patient\Services\PatientSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PatientApiController extends Controller
{
    public function __construct(
        private readonly PatientSearchService $searchService,
        private readonly DuplicateDetectionService $duplicateDetector,
        private readonly PatientRegistrationService $registrationService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Patient::class);

        $patients = $this->searchService->search(
            term: $request->string('q')->toString(),
            status: $request->string('status', 'active')->toString(),
            perPage: min(50, max(1, $request->integer('per_page', 15))),
        );

        return PatientResource::collection($patients);
    }

    public function show(Patient $patient): PatientResource
    {
        Gate::authorize('view', $patient);

        return new PatientResource($patient->canonicalPatient());
    }

    public function checkDuplicates(Request $request): JsonResponse
    {
        Gate::authorize('create', Patient::class);

        $candidates = $this->duplicateDetector->findCandidates($request->all());

        return response()->json([
            'has_hard_match' => $this->duplicateDetector->hasHardMatch($candidates),
            'count' => $candidates->count(),
            'candidates' => $candidates->map(fn (array $item): array => [
                'match_level' => $item['match_level'],
                'score' => $item['score'],
                'reasons' => $item['reasons'],
                'patient' => (new PatientResource($item['patient']))->resolve(),
            ])->values(),
        ]);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $patient = $this->registrationService->register(
                data: $request->validated(),
                actor: $user,
                confirmDistinct: $request->boolean('confirm_distinct'),
            );
        } catch (DuplicatePatientException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'has_hard_match' => $this->duplicateDetector->hasHardMatch($exception->candidates),
                'candidates' => $exception->candidates->map(fn (array $item): array => [
                    'match_level' => $item['match_level'],
                    'score' => $item['score'],
                    'reasons' => $item['reasons'],
                    'patient' => (new PatientResource($item['patient']))->resolve(),
                ])->values(),
            ], 409);
        }

        return (new PatientResource($patient))
            ->response()
            ->setStatusCode(201);
    }
}
