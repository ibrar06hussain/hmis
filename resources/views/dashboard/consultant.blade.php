@extends('layouts.app')

@section('title', 'Consultant Clinic Dashboard')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Consultant Clinic Workspace — {{ $user->name }}</h1>
        <p class="text-muted mb-0">
            <span class="badge bg-primary text-uppercase">{{ str_replace('_', ' ', $primaryRole) }}</span>
            @if($user->staffProfile?->department)
                &middot; <strong>{{ $user->staffProfile->department->name }}</strong>
            @endif
            &middot; {{ $todayDate }} (PKT)
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('opd.index') }}" class="btn btn-primary btn-touch fw-semibold">
            <i class="bi bi-calendar2-check-fill me-1"></i>OPD Queue
        </a>
        <a href="{{ route('emr.cosign.index') }}" class="btn btn-outline-primary btn-touch fw-semibold">
            <i class="bi bi-pen-fill me-1"></i>Co-Sign Tray
            @if($consultantMetrics['pending_cosign'] > 0)
                <span class="badge bg-danger ms-1">{{ $consultantMetrics['pending_cosign'] }}</span>
            @endif
        </a>
        <a href="{{ route('emr.favorites.index') }}" class="btn btn-outline-success btn-touch">
            <i class="bi bi-bookmark-star-fill me-1"></i>My Order Sets
        </a>
        <a href="{{ route('cardiology.procedures.index') }}" class="btn btn-outline-danger btn-touch">
            <i class="bi bi-heart-fill me-1"></i>Cath Lab &amp; OT
        </a>
    </div>
</div>

{{-- Consultant Clinical KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Today's OPD Patients</div>
            <div class="fs-3 fw-bold text-primary">{{ number_format($consultantMetrics['opd_today']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="hmis-card p-3 h-100 border-warning">
            <div class="text-muted small">Waiting / Checked-In</div>
            <div class="fs-3 fw-bold text-warning-emphasis">{{ number_format($consultantMetrics['waiting_now']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="hmis-card p-3 h-100 {{ $consultantMetrics['pending_cosign'] > 0 ? 'border-info' : '' }}">
            <div class="text-muted small">Pending Co-Sign Drafts</div>
            <div class="fs-3 fw-bold text-info">{{ number_format($consultantMetrics['pending_cosign']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Active Inpatients (IPD/CCU)</div>
            <div class="fs-3 fw-bold text-danger">{{ number_format($consultantMetrics['active_inpatients']) }}</div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Today's OPD Queue with 1-Click Rapid 60s Consult --}}
    <div class="col-lg-7">
        <div class="hmis-card p-3 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h6 fw-bold mb-0"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Today's OPD Queue (1-Click Rapid Consult)</h2>
                <a href="{{ route('opd.index') }}" class="btn btn-sm btn-outline-primary">Full Queue</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Token</th>
                            <th>Patient (MRN)</th>
                            <th>Vitals</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($todayOpdQueue as $appt)
                            @php $vital = $appt->encounter?->vitalSigns?->first(); @endphp
                            <tr>
                                <td><span class="badge bg-dark font-monospace">{{ $appt->formattedToken() }}</span></td>
                                <td>
                                    <a href="{{ route('patients.show', $appt->patient) }}" class="fw-semibold text-decoration-none">{{ $appt->patient->full_name }}</a>
                                    <div class="text-muted hmis-mrn-badge">{{ $appt->patient->mrn }}</div>
                                </td>
                                <td>
                                    @if($vital)
                                        <span class="badge {{ $vital->is_abnormal ? 'bg-danger' : 'bg-success-subtle text-success' }}">
                                            {{ $vital->systolic_bp }}/{{ $vital->diastolic_bp }} &middot; HR {{ $vital->heart_rate }}
                                        </span>
                                    @else
                                        <span class="text-muted">Pending</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $appt->status) }}</span></td>
                                <td class="text-end text-nowrap">
                                    @if($appt->status === 'checked_in')
                                        <form method="POST" action="{{ route('opd.appointments.start-consult', $appt) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="mode" value="rapid">
                                            <button type="submit" class="btn btn-sm btn-warning fw-semibold">
                                                <i class="bi bi-lightning-charge-fill"></i> Rapid 60s
                                            </button>
                                        </form>
                                    @elseif($appt->encounter)
                                        <a href="{{ route('encounters.rapid.show', $appt->encounter) }}" class="btn btn-sm btn-warning fw-semibold">
                                            <i class="bi bi-lightning-charge-fill"></i> Rapid 60s
                                        </a>
                                    @else
                                        <a href="{{ route('patients.show', $appt->patient) }}" class="btn btn-sm btn-outline-secondary">Profile</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No pending OPD appointments for today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Active Inpatients / CCU Rounds --}}
    <div class="col-lg-5">
        <div class="hmis-card p-3 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h6 fw-bold mb-0"><i class="bi bi-hospital-fill text-danger me-2"></i>Active Inpatients &amp; CCU Rounds</h2>
                <a href="{{ route('ipd.index') }}" class="btn btn-sm btn-outline-primary">Wards &amp; IPD</a>
            </div>
            <div class="list-group list-group-flush small">
                @forelse($activeAdmissions as $adm)
                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-semibold">{{ $adm->patient->full_name }} <span class="text-muted hmis-mrn-badge">({{ $adm->patient->mrn }})</span></div>
                            <div class="text-muted">{{ $adm->ward?->name }} &middot; <span class="badge bg-danger font-monospace">{{ $adm->bed?->bed_number }}</span> &middot; {{ \Illuminate\Support\Str::limit($adm->admission_diagnosis, 36) }}</div>
                        </div>
                        <div class="text-nowrap">
                            @if($adm->encounter)
                                <a href="{{ route('encounters.rapid.show', $adm->encounter) }}" class="btn btn-sm btn-outline-primary">Round</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-muted py-4 text-center">No active inpatients currently admitted.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
