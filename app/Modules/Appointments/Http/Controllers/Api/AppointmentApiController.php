<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Appointments\Http\Requests\StoreAppointmentRequest;
use App\Modules\Appointments\Http\Resources\AppointmentResource;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class AppointmentApiController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Appointment::class);

        $date = $request->string('date')->toString()
            ?: Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->toDateString();

        $appointments = Appointment::query()
            ->with(['patient', 'department', 'encounter'])
            ->whereDate('appointment_date', $date)
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->orderBy('department_id')
            ->orderBy('token_number')
            ->paginate(30);

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Appointment::class);

        /** @var User $user */
        $user = $request->user();

        $appointment = $this->appointmentService->book($request->validated(), $user);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    public function checkIn(Request $request, Appointment $appointment): AppointmentResource|JsonResponse
    {
        Gate::authorize('checkIn', $appointment);

        /** @var User $user */
        $user = $request->user();

        try {
            $checkedIn = $this->appointmentService->checkIn($appointment, $user);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new AppointmentResource($checkedIn);
    }
}
