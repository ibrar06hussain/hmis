@extends('layouts.app')

@section('title', 'Wards, Beds & IPD Admissions')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-hospital-fill me-2 text-primary"></i>Ward &amp; Bed Management / Inpatient Department (IPD)</h1>
        <small class="text-muted">Atomic bed locking prevents double allocation; discharged beds require cleaning sign-off before reuse</small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Live Ward & Bed Map --}}
<div class="row g-3 mb-4">
    @foreach($wards as $ward)
        <div class="col-md-6 col-xl-4">
            <div class="hmis-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                    <div>
                        <div class="fw-bold">{{ $ward->name }}</div>
                        <small class="text-muted">{{ $ward->floor }} &middot; {{ strtoupper($ward->ward_type) }}</small>
                    </div>
                    <span class="badge bg-light text-dark border">Cap: {{ $ward->bed_capacity }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($ward->beds as $bed)
                        @php
                            $bedClass = match($bed->status) {
                                'available' => 'border-success bg-success-subtle text-success',
                                'occupied' => 'border-danger bg-danger-subtle text-danger',
                                'cleaning' => 'border-warning bg-warning-subtle text-dark',
                                default => 'border-secondary bg-light text-muted',
                            };
                        @endphp
                        <div class="border rounded p-2 small {{ $bedClass }}" style="min-width: 118px;">
                            <div class="fw-bold font-monospace">{{ $bed->bed_number }}</div>
                            <div class="text-uppercase" style="font-size: 0.72rem;">
                                {{ $bed->status }}
                                @if($bed->has_cardiac_monitor)<i class="bi bi-activity ms-1" title="Cardiac Monitor"></i>@endif
                                @if($bed->has_ventilator)<i class="bi bi-wind ms-1" title="Ventilator"></i>@endif
                            </div>
                            @if($bed->status === 'cleaning')
                                @can('manageBed', \App\Modules\Ipd\Models\IpdAdmission::class)
                                    <form method="POST" action="{{ route('ipd.beds.mark-available', $bed) }}" class="mt-1">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-success py-0 px-1" style="font-size: 0.7rem;">Mark Clean</button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    {{-- Active Inpatients --}}
    <div class="col-xl-8">
        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Active Inpatients ({{ $activeAdmissions->count() }})</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Admission #</th>
                            <th>Patient (MRN)</th>
                            <th>Ward / Bed</th>
                            <th>Diagnosis</th>
                            <th>Admitted (PKT)</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeAdmissions as $adm)
                            <tr>
                                <td class="font-monospace fw-semibold">{{ $adm->admission_number }}</td>
                                <td>
                                    <a href="{{ route('patients.show', $adm->patient) }}" class="fw-semibold text-decoration-none">{{ $adm->patient->full_name }}</a>
                                    <div class="text-muted hmis-mrn-badge">{{ $adm->patient->mrn }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $adm->ward->name }}</div>
                                    <span class="badge bg-danger font-monospace">{{ $adm->bed?->bed_number }}</span>
                                </td>
                                <td>{{ $adm->admission_diagnosis }}</td>
                                <td>{{ \App\Support\DateTimeHelper::formatDateTime($adm->admitted_at, 'd M, H:i') }}</td>
                                <td class="text-end text-nowrap">
                                    @can('view', $adm->encounter)
                                        <a href="{{ route('encounters.show', $adm->encounter) }}" class="btn btn-sm btn-outline-primary">Chart</a>
                                    @endcan
                                    @can('transfer', $adm)
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#transferModal-{{ $adm->id }}">Transfer</button>
                                    @endcan
                                    @can('discharge', $adm)
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#dischargeModal-{{ $adm->id }}">Discharge</button>
                                    @endcan
                                </td>
                            </tr>

                            {{-- Transfer Modal --}}
                            @can('transfer', $adm)
                                <div class="modal fade" id="transferModal-{{ $adm->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ route('ipd.admissions.transfer', $adm) }}" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Transfer Bed — {{ $adm->patient->full_name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Target Available Bed</label>
                                                    <select name="target_bed_id" class="form-select" required>
                                                        @foreach($availableBeds as $ab)
                                                            <option value="{{ $ab->id }}">{{ $ab->ward->name }} — {{ $ab->bed_number }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label small fw-semibold">Transfer Reason</label>
                                                    <input type="text" name="reason" class="form-control" required minlength="5" placeholder="e.g., Step-down from CCU to Male Cardiology Ward">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-warning btn-sm fw-semibold">Confirm Transfer</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan

                            {{-- Discharge Modal --}}
                            @can('discharge', $adm)
                                <div class="modal fade" id="dischargeModal-{{ $adm->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <form method="POST" action="{{ route('ipd.admissions.discharge', $adm) }}" class="modal-content">
                                            @csrf
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">Discharge Patient — {{ $adm->patient->full_name }} ({{ $adm->admission_number }})</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label small fw-semibold">Discharge Outcome</label>
                                                        <select name="discharge_status" class="form-select" required>
                                                            <option value="discharged">Routine Discharge</option>
                                                            <option value="transferred_out">Transferred Out</option>
                                                            <option value="lama">LAMA (Left Against Medical Advice)</option>
                                                            <option value="expired">Expired</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small fw-semibold">Condition at Discharge</label>
                                                        <select name="discharge_condition" class="form-select" required>
                                                            <option value="improved">Improved</option>
                                                            <option value="stable">Stable</option>
                                                            <option value="unchanged">Unchanged</option>
                                                            <option value="critical_referred">Critical / Referred</option>
                                                            <option value="expired">Expired</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Clinical Discharge Summary <span class="text-danger">*</span></label>
                                                    <textarea name="discharge_summary" rows="3" class="form-control" required minlength="15" placeholder="Hospital course, procedures performed, key lab/echo findings, discharge medications..."></textarea>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label small fw-semibold">Follow-Up Plan</label>
                                                    <input type="text" name="follow_up_plan" class="form-control" placeholder="e.g., Cardiology OPD in 2 weeks with repeat lipid profile and ECG">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success btn-sm fw-semibold">Sign Discharge &amp; Release Bed to Cleaning</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No active inpatients currently admitted.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Admit Patient Panel --}}
    @can('admit', \App\Modules\Ipd\Models\IpdAdmission::class)
        <div class="col-xl-4">
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-plus-circle me-1 text-primary"></i>Admit Patient to Ward/CCU Bed</h2>
                <form method="POST" action="{{ route('ipd.admit') }}">
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
                        <label for="bed_id" class="form-label small fw-semibold">Available Ward / CCU Bed <span class="text-danger">*</span></label>
                        <select id="bed_id" name="bed_id" class="form-select" required>
                            <option value="">Select available bed...</option>
                            @foreach($availableBeds as $ab)
                                <option value="{{ $ab->id }}" @selected((int) old('bed_id') === $ab->id)>
                                    {{ $ab->ward->name }} — {{ $ab->bed_number }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="admission_type" class="form-label small fw-semibold">Admission Type</label>
                            <select id="admission_type" name="admission_type" class="form-select" required>
                                <option value="emergency">Emergency</option>
                                <option value="elective">Elective</option>
                                <option value="cardiac_surgery">Cardiac Surgery</option>
                                <option value="transfer_in">Transfer In</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="attending_staff_id" class="form-label small fw-semibold">Attending Doctor</label>
                            <select id="attending_staff_id" name="attending_staff_id" class="form-select">
                                <option value="">Current Clinician</option>
                                @foreach($clinicians as $c)
                                    <option value="{{ $c->id }}">{{ $c->user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="admission_diagnosis" class="form-label small fw-semibold">Provisional Admission Diagnosis <span class="text-danger">*</span></label>
                        <input type="text" id="admission_diagnosis" name="admission_diagnosis" value="{{ old('admission_diagnosis') }}" class="form-control" required placeholder="e.g., Acute Anterior STEMI for CCU monitoring">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-semibold btn-touch">Admit &amp; Lock Bed</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
@endsection
