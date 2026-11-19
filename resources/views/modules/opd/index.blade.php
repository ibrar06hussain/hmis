@extends('layouts.app')

@section('title', 'OPD Queue & Appointments')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-calendar2-check-fill me-2 text-primary"></i>OPD Appointments &amp; Consultation Queue</h1>
        <small class="text-muted">Atomic daily token numbering per clinical department; check-in automatically creates an AMB Encounter</small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    {{-- Queue Filter & Live Table --}}
    <div class="col-xl-8">
        <div class="hmis-card p-3 mb-3">
            <form method="GET" action="{{ route('opd.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="date" class="form-label small fw-semibold mb-1">Clinic Date</label>
                    <input type="date" id="date" name="date" value="{{ $selectedDate }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="department_id" class="form-label small fw-semibold mb-1">Clinical Department</label>
                    <select id="department_id" name="department_id" class="form-select">
                        <option value="">All Clinical Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" @selected($selectedDepartmentId === $dept->id)>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>

        <div class="hmis-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Token</th>
                            <th>Patient (MRN)</th>
                            <th>Department / Clinician</th>
                            <th>Visit</th>
                            <th>Status</th>
                            <th class="text-end">Workflow Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($appointments as $appt)
                            @php
                                $statusBadge = match($appt->status) {
                                    'booked' => 'bg-secondary',
                                    'checked_in' => 'bg-info text-dark',
                                    'in_consult' => 'bg-primary',
                                    'completed' => 'bg-success',
                                    'cancelled', 'no_show' => 'bg-dark',
                                    default => 'bg-light text-dark',
                                };
                            @endphp
                            <tr>
                                <td><span class="badge bg-dark fs-6 font-monospace">{{ $appt->formattedToken() }}</span></td>
                                <td>
                                    <a href="{{ route('patients.show', $appt->patient) }}" class="fw-semibold text-decoration-none">
                                        {{ $appt->patient->full_name }}
                                    </a>
                                    <div class="small text-muted hmis-mrn-badge">{{ $appt->patient->mrn }}</div>
                                </td>
                                <td class="small">
                                    <div class="fw-semibold">{{ $appt->department->name }}</div>
                                    <div class="text-muted">{{ $appt->clinician?->user?->name ?? 'Any On-Duty Clinician' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $appt->visit_type === 'urgent' ? 'bg-danger' : 'bg-light text-dark border' }} text-uppercase">
                                        {{ str_replace('_', ' ', $appt->visit_type) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadge }} text-uppercase">{{ str_replace('_', ' ', $appt->status) }}</span>
                                    @if($appt->encounter)
                                        <div class="small font-monospace text-muted">{{ $appt->encounter->encounter_number }}</div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('opd.appointments.slip', $appt) }}" class="btn btn-sm btn-outline-secondary" title="Print Bilingual OPD Token Slip">
                                        <i class="bi bi-printer"></i> Slip
                                    </a>
                                    @if($appt->status === 'booked')
                                        @can('checkIn', $appt)
                                            <form method="POST" action="{{ route('opd.appointments.check-in', $appt) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">Check In</button>
                                            </form>
                                        @endcan
                                    @elseif($appt->status === 'checked_in')
                                        @can('consult', $appt)
                                            <form method="POST" action="{{ route('opd.appointments.start-consult', $appt) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary">Start Consult</button>
                                            </form>
                                        @endcan
                                    @elseif(in_array($appt->status, ['in_consult', 'completed'], true) && $appt->encounter)
                                        @can('view', $appt->encounter)
                                            <a href="{{ route('encounters.show', $appt->encounter) }}" class="btn btn-sm btn-outline-primary">
                                                Open Workspace
                                            </a>
                                        @endcan
                                    @endif

                                    @if(in_array($appt->status, ['booked', 'checked_in'], true))
                                        @can('cancel', $appt)
                                            <form method="POST" action="{{ route('opd.appointments.cancel', $appt) }}" class="d-inline" onsubmit="return confirm('Cancel this OPD appointment?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No appointments scheduled for {{ $selectedDate }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Book Appointment Panel --}}
    @can('create', \App\Modules\Appointments\Models\Appointment::class)
        <div class="col-xl-4">
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-plus-circle me-1 text-primary"></i>Book OPD Appointment</h2>
                <form method="POST" action="{{ route('opd.appointments.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="patient_id" class="form-label small fw-semibold">Patient <span class="text-danger">*</span></label>
                        <select id="patient_id" name="patient_id" class="form-select" required>
                            <option value="">Select active patient...</option>
                            @foreach($activePatients as $p)
                                <option value="{{ $p->id }}" @selected((int) old('patient_id', $preselectedPatientId) === $p->id)>
                                    {{ $p->mrn }} — {{ $p->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="book_department_id" class="form-label small fw-semibold">Clinic Department <span class="text-danger">*</span></label>
                        <select id="book_department_id" name="department_id" class="form-select" required>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" @selected((int) old('department_id', $selectedDepartmentId) === $dept->id || ($selectedDepartmentId === null && $dept->code === 'CARD-OPD'))>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="clinician_staff_id" class="form-label small fw-semibold">Preferred Consultant / MO</label>
                        <select id="clinician_staff_id" name="clinician_staff_id" class="form-select">
                            <option value="">Any Available Clinician</option>
                            @foreach($clinicians as $staff)
                                <option value="{{ $staff->id }}" @selected((int) old('clinician_staff_id') === $staff->id)>
                                    {{ $staff->user->name }} ({{ $staff->designation }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="appointment_date" class="form-label small fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" id="appointment_date" name="appointment_date" value="{{ old('appointment_date', $selectedDate) }}" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label for="visit_type" class="form-label small fw-semibold">Visit Type</label>
                            <select id="visit_type" name="visit_type" class="form-select" required>
                                <option value="new" @selected(old('visit_type') === 'new')>New Visit</option>
                                <option value="follow_up" @selected(old('visit_type') === 'follow_up')>Follow-Up</option>
                                <option value="urgent" @selected(old('visit_type') === 'urgent')>Urgent Cardiac</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="chief_complaint" class="form-label small fw-semibold">Chief Complaint / Reason</label>
                        <input type="text" id="chief_complaint" name="chief_complaint" value="{{ old('chief_complaint') }}" class="form-control" placeholder="e.g., Chest tightness, HTN follow-up, Echo review">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-semibold btn-touch">Issue OPD Token</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
@endsection
