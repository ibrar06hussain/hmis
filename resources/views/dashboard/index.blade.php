@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Welcome, {{ $user->name }}</h1>
        <p class="text-muted mb-0">
            Role: <span class="badge bg-primary text-uppercase">{{ str_replace('_', ' ', $primaryRole) }}</span>
            @if($user->staffProfile?->department)
                &middot; Department: <strong>{{ $user->staffProfile->department->name }}</strong>
            @endif
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        @can('patient.register')
            <a href="{{ route('patients.create', ['mode' => 'standard']) }}" class="btn btn-primary btn-touch">
                <i class="bi bi-person-plus-fill me-1"></i>New Patient Registration
            </a>
        @endcan
        @can('patient.register_emergency')
            <a href="{{ route('patients.create', ['mode' => 'emergency']) }}" class="btn btn-danger btn-touch">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>Emergency Intake (No CNIC)
            </a>
        @endcan
    </div>
</div>

{{-- Operational Metrics --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Active Patients (MPI)</div>
            <div class="fs-3 fw-bold text-primary">{{ number_format($metrics['active_patients']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Registered Today</div>
            <div class="fs-3 fw-bold text-success">{{ number_format($metrics['registered_today']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Unverified ER IDs</div>
            <div class="fs-3 fw-bold text-warning">{{ number_format($metrics['unverified_emergency']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Active Encounters</div>
            <div class="fs-3 fw-bold">{{ number_format($metrics['active_encounters']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Departments</div>
            <div class="fs-3 fw-bold">{{ number_format($metrics['active_departments']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Active Wards</div>
            <div class="fs-3 fw-bold">{{ number_format($metrics['total_wards']) }}</div>
        </div>
    </div>
</div>

<div class="row g-4">
    @can('patient.view_demographics')
        <div class="col-lg-7">
            <div class="hmis-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h6 fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Recent Patient Registrations</h2>
                    <a href="{{ route('patients.index') }}" class="btn btn-sm btn-outline-primary">View MPI</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>MRN</th>
                                <th>Patient</th>
                                <th>Sex / Age</th>
                                <th>Identity</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPatients as $patient)
                                <tr>
                                    <td><span class="hmis-mrn-badge text-primary">{{ $patient->mrn }}</span></td>
                                    <td>
                                        <div class="fw-semibold">{{ $patient->full_name }}</div>
                                        <small class="text-muted">{{ $patient->district ?? 'Gilgit' }}</small>
                                    </td>
                                    <td>{{ ucfirst($patient->sex) }} / {{ $patient->ageDisplay() }}</td>
                                    <td>
                                        @if($patient->identity_verification_status === 'unverified')
                                            <span class="badge bg-warning text-dark">Unverified ER</span>
                                        @elseif($patient->identity_verification_status === 'pending_cnic')
                                            <span class="badge bg-secondary">No CNIC</span>
                                        @else
                                            <span class="badge bg-success-subtle text-success">Verified</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No patients registered yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endcan

    @can('audit.view_logs')
        <div class="col-lg-5">
            <div class="hmis-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h6 fw-bold mb-0"><i class="bi bi-shield-check me-2 text-danger"></i>Security &amp; Audit Activity</h2>
                    @if($activeBreakGlassCount > 0)
                        <span class="badge bg-danger">{{ $activeBreakGlassCount }} Active Break-Glass</span>
                    @endif
                </div>
                <div class="list-group list-group-flush small">
                    @forelse($recentAuditLogs as $log)
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">{{ $log->action }}</span>
                                <span class="text-muted">{{ \App\Support\DateTimeHelper::formatDateTime($log->occurred_at, 'd M, H:i') }}</span>
                            </div>
                            <div class="text-muted">
                                Actor: {{ $log->actor?->name ?? 'System/Guest' }} ({{ $log->actor_role ?? 'n/a' }})
                                @if($log->reason)
                                    &middot; <em>{{ \Illuminate\Support\Str::limit($log->reason, 60) }}</em>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-muted py-3 text-center">No audit entries recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endcan
</div>
@endsection
