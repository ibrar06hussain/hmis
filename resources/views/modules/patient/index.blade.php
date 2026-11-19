@extends('layouts.app')

@section('title', 'Master Patient Index')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0">Master Patient Index (MPI)</h1>
        <small class="text-muted">Search by permanent MRN, historical merged MRN, CNIC, phone, or patient/father name</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @can('patient.register')
            <a href="{{ route('patients.create', ['mode' => 'standard']) }}" class="btn btn-primary btn-touch">
                <i class="bi bi-person-plus-fill me-1"></i>Register Patient
            </a>
        @endcan
        @can('patient.register_emergency')
            <a href="{{ route('patients.create', ['mode' => 'emergency']) }}" class="btn btn-danger btn-touch">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>Emergency Intake
            </a>
        @endcan
        @can('patient.merge')
            <a href="{{ route('patients.merge.form') }}" class="btn btn-outline-dark btn-touch">
                <i class="bi bi-intersect me-1"></i>Merge Duplicates
            </a>
        @endcan
    </div>
</div>

<div class="hmis-card p-3 mb-3">
    <form method="GET" action="{{ route('patients.index') }}" class="row g-2 align-items-end">
        <div class="col-md-7">
            <label for="q" class="form-label small fw-semibold mb-1">Patient Search</label>
            <input type="search" id="q" name="q" value="{{ $search }}" class="form-control" placeholder="CHG-2026-000001, 71501-1000001-1, 0345..., or full name">
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label small fw-semibold mb-1">Record Status</label>
            <select id="status" name="status" class="form-select">
                <option value="active" @selected($status === 'active')>Active Patients</option>
                <option value="merged" @selected($status === 'merged')>Merged (Historical)</option>
                <option value="all" @selected($status === 'all')>All Statuses</option>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>
</div>

<div class="hmis-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>MRN</th>
                    <th>Full Name &amp; Father/Spouse</th>
                    <th>Age / Sex</th>
                    <th>CNIC / Phone</th>
                    <th>District</th>
                    <th>Verification</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($patients as $patient)
                    <tr>
                        <td>
                            <span class="hmis-mrn-badge text-primary">{{ $patient->mrn }}</span>
                            @if($patient->status === 'merged')
                                <div><span class="badge bg-dark">MERGED</span></div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $patient->full_name }}</div>
                            @if($patient->father_or_spouse_name)
                                <small class="text-muted">S/O, D/O, W/O: {{ $patient->father_or_spouse_name }}</small>
                            @endif
                        </td>
                        <td>{{ $patient->ageDisplay() }} &middot; {{ ucfirst($patient->sex) }}</td>
                        <td>
                            <div>{{ $patient->maskedCnic() ?? 'Not provided' }}</div>
                            <small class="text-muted">{{ $patient->phone_primary ?? 'No phone' }}</small>
                        </td>
                        <td>{{ $patient->district ?? '—' }}</td>
                        <td>
                            @if($patient->identity_verification_status === 'unverified')
                                <span class="badge bg-warning text-dark">Unverified ER</span>
                            @elseif($patient->identity_verification_status === 'pending_cnic')
                                <span class="badge bg-secondary">Pending CNIC</span>
                            @else
                                <span class="badge bg-success-subtle text-success">Verified</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-primary">Profile</a>
                            <a href="{{ route('patients.card', $patient) }}" class="btn btn-sm btn-outline-secondary" title="Print MRN Card">
                                <i class="bi bi-printer"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No matching patients found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($patients->hasPages())
        <div class="p-3 border-top">
            {{ $patients->links() }}
        </div>
    @endif
</div>
@endsection
