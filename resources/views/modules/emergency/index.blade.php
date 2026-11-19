@extends('layouts.app')

@section('title', 'Cardiac Emergency & STEMI Triage')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-heart-pulse-fill me-2 text-danger"></i>Cardiac Emergency &amp; Chest Pain Triage</h1>
        <small class="text-muted">Priority sorted by Code STEMI alert &amp; ESI Triage Level (1 = Immediate Resuscitation)</small>
    </div>
    <div class="d-flex gap-2">
        @can('patient.register_emergency')
            <a href="{{ route('patients.create', ['mode' => 'emergency']) }}" class="btn btn-danger btn-touch">
                <i class="bi bi-person-plus-fill me-1"></i>Fast-Track ER Patient (No CNIC)
            </a>
        @endcan
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-8">
        <div class="d-flex gap-2 mb-3">
            <a href="{{ route('emergency.index', ['disposition' => 'pending']) }}" class="btn btn-sm {{ $filter === 'pending' ? 'btn-danger' : 'btn-outline-secondary' }}">Active / Pending ER</a>
            <a href="{{ route('emergency.index', ['disposition' => 'all']) }}" class="btn btn-sm {{ $filter === 'all' ? 'btn-dark' : 'btn-outline-secondary' }}">All ER Triages</a>
        </div>

        <div class="hmis-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Priority</th>
                            <th>Patient (MRN)</th>
                            <th>Presenting Complaint</th>
                            <th>Arrival / Time</th>
                            <th>Disposition</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($triages as $triage)
                            <tr class="{{ $triage->stemi_alert ? 'table-danger' : ($triage->triage_level === 1 ? 'table-warning' : '') }}">
                                <td>
                                    <span class="badge {{ $triage->triage_level <= 2 ? 'bg-danger' : ($triage->triage_level === 3 ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                        L{{ $triage->triage_level }}
                                    </span>
                                    @if($triage->stemi_alert)
                                        <div><span class="badge bg-danger mt-1">CODE STEMI</span></div>
                                    @elseif($triage->chest_pain_flag)
                                        <div><span class="badge bg-warning text-dark mt-1">CHEST PAIN</span></div>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('patients.show', $triage->patient) }}" class="fw-semibold text-decoration-none">{{ $triage->patient->full_name }}</a>
                                    <div class="small text-muted hmis-mrn-badge">{{ $triage->patient->mrn }}</div>
                                </td>
                                <td class="small">
                                    <div class="fw-semibold">{{ $triage->presenting_complaint }}</div>
                                    @if($triage->triage_notes)<div class="text-muted">{{ $triage->triage_notes }}</div>@endif
                                </td>
                                <td class="small">
                                    <div class="text-uppercase">{{ str_replace('_', ' ', $triage->arrival_mode) }}</div>
                                    <div class="text-muted">{{ \App\Support\DateTimeHelper::formatDateTime($triage->triaged_at, 'd M, H:i') }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-dark text-uppercase">{{ str_replace('_', ' ', $triage->disposition) }}</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @can('view', $triage->encounter)
                                        <a href="{{ route('encounters.show', $triage->encounter) }}" class="btn btn-sm btn-primary">Workspace</a>
                                    @endcan
                                    @can('admit', \App\Modules\Ipd\Models\IpdAdmission::class)
                                        <a href="{{ route('ipd.index', ['patient_id' => $triage->patient_id]) }}" class="btn btn-sm btn-outline-danger">Admit CCU/IPD</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No emergency patients in this queue.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('create', \App\Modules\Emergency\Models\EmergencyTriage::class)
        <div class="col-xl-4">
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3 text-danger"><i class="bi bi-lightning-charge-fill me-1"></i>New ER Cardiac Triage</h2>
                <form method="POST" action="{{ route('emergency.triage.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="patient_id" class="form-label small fw-semibold">Patient <span class="text-danger">*</span></label>
                        <select id="patient_id" name="patient_id" class="form-select" required>
                            <option value="">Select patient...</option>
                            @foreach($activePatients as $p)
                                <option value="{{ $p->id }}" @selected((int) old('patient_id', $preselectedPatientId) === $p->id)>
                                    {{ $p->mrn }} — {{ $p->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="triage_level" class="form-label small fw-semibold">Triage Level</label>
                            <select id="triage_level" name="triage_level" class="form-select" required>
                                <option value="1">1 — Resuscitation</option>
                                <option value="2" selected>2 — Emergent</option>
                                <option value="3">3 — Urgent</option>
                                <option value="4">4 — Less Urgent</option>
                                <option value="5">5 — Non-Urgent</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="arrival_mode" class="form-label small fw-semibold">Arrival Mode</label>
                            <select id="arrival_mode" name="arrival_mode" class="form-select" required>
                                <option value="ambulance_1122">Rescue 1122 / Ambulance</option>
                                <option value="walk_in">Walk-In</option>
                                <option value="referral">Hospital Referral</option>
                                <option value="police">Police / Medico-Legal</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2 d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="chest_pain_flag" name="chest_pain_flag" value="1">
                            <label class="form-check-label small fw-semibold" for="chest_pain_flag">Chest Pain</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="stemi_alert" name="stemi_alert" value="1">
                            <label class="form-check-label small fw-bold text-danger" for="stemi_alert">CODE STEMI Alert</label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="presenting_complaint" class="form-label small fw-semibold">Presenting Complaint <span class="text-danger">*</span></label>
                        <input type="text" id="presenting_complaint" name="presenting_complaint" value="{{ old('presenting_complaint') }}" class="form-control" required placeholder="e.g., Crushing central chest pain + diaphoresis">
                    </div>
                    <div class="mb-3">
                        <label for="triage_notes" class="form-label small fw-semibold">Triage Notes</label>
                        <textarea id="triage_notes" name="triage_notes" rows="2" class="form-control" placeholder="Initial ECG/oxygen/IV access notes...">{{ old('triage_notes') }}</textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger fw-semibold btn-touch">Start Emergency Encounter</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
@endsection
