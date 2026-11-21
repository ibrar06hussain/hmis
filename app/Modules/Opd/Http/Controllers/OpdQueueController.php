<?php

declare(strict_types=1);

namespace App\Modules\Opd\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Appointments\Http\Requests\StoreAppointmentRequest;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Patient\Models\Patient;
use App\Support\OfflineQrSvg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OpdQueueController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Appointment::class);

        $date = $request->string('date')->toString()
            ?: Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->toDateString();

        $departmentId = $request->integer('department_id') ?: null;

        $appointments = Appointment::query()
            ->with(['patient', 'department', 'clinician.user', 'encounter'])
            ->whereDate('appointment_date', $date)
            ->when($departmentId !== null, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('department_id')
            ->orderBy('token_number')
            ->get();

        $departments = Department::query()
            ->where('is_active', true)
            ->where('is_clinical', true)
            ->orderBy('name')
            ->get();

        $clinicians = StaffProfile::query()
            ->with(['user', 'department'])
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->role(['consultant', 'medical_officer']))
            ->orderBy('designation')
            ->get();

        $activePatients = Patient::query()
            ->active()
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('modules.opd.index', [
            'appointments' => $appointments,
            'departments' => $departments,
            'clinicians' => $clinicians,
            'activePatients' => $activePatients,
            'selectedDate' => $date,
            'selectedDepartmentId' => $departmentId,
            'preselectedPatientId' => $request->integer('patient_id') ?: null,
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Appointment::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $appointment = $this->appointmentService->book($request->validated(), $user);
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['patient_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('opd.index', [
                'date' => $appointment->appointment_date->format('Y-m-d'),
                'department_id' => $appointment->department_id,
            ])
            ->with('status', "Booked appointment for {$appointment->patient->full_name} — Token {$appointment->formattedToken()}.");
    }

    public function checkIn(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('checkIn', $appointment);

        /** @var User $user */
        $user = $request->user();

        try {
            $checkedIn = $this->appointmentService->checkIn($appointment, $user);
        } catch (\DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        return back()->with('status', "Checked in Token {$checkedIn->formattedToken()} (Encounter {$checkedIn->encounter->encounter_number}).");
    }

    public function startConsult(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('consult', $appointment);

        /** @var User $user */
        $user = $request->user();

        try {
            $encounter = $this->appointmentService->startConsultation($appointment, $user);
        } catch (\DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        $targetRoute = $request->string('mode')->toString() === 'rapid'
            ? 'encounters.rapid.show'
            : 'encounters.show';

        return redirect()
            ->route($targetRoute, $encounter)
            ->with('status', "Consultation active for Encounter {$encounter->encounter_number}.");
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('cancel', $appointment);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->appointmentService->cancel($appointment, $user, $request->string('reason')->toString() ?: 'Cancelled at OPD desk');
        } catch (\DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        return back()->with('warning', "Appointment Token {$appointment->formattedToken()} cancelled.");
    }

    public function visitSlip(Appointment $appointment): View
    {
        Gate::authorize('viewAny', Appointment::class);

        $appointment->load(['patient', 'department', 'clinician.user', 'encounter']);

        return view('modules.opd.visit-slip', [
            'appointment' => $appointment,
            'qrSvg' => OfflineQrSvg::render(
                sprintf(
                    'CHG-OPD|%s|%s|%s',
                    $appointment->patient->mrn,
                    $appointment->formattedToken(),
                    $appointment->appointment_date->format('Y-m-d')
                ),
                110,
                1
            ),
        ]);
    }
}
