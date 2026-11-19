@extends('layouts.app')

@section('title', 'Staff Duty Rosters')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-calendar-week-fill me-2 text-primary"></i>Hospital Staff Duty Rosters</h1>
        <small class="text-muted">Shift coverage across Cardiology OPD, ER, CCU, Cardiac Surgery, LIS &amp; Pharmacy with double-booking prevention</small>
    </div>
    <form method="GET" action="{{ route('rosters.index') }}" class="d-flex gap-2">
        <input type="date" name="date" value="{{ $selectedDate }}" class="form-control form-control-sm">
        <button type="submit" class="btn btn-sm btn-primary">View Date</button>
    </form>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-8">
        <div class="hmis-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>Shift</th><th>Staff Member</th><th>Designation</th><th>Department / Ward</th><th>Status</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                        @forelse($rosters as $r)
                            <tr>
                                <td><span class="badge bg-primary text-uppercase">{{ str_replace('_', ' ', $r->shift) }}</span></td>
                                <td class="fw-semibold">{{ $r->staffProfile->user->name }}</td>
                                <td>{{ $r->staffProfile->designation }}</td>
                                <td>
                                    {{ $r->department->name }}
                                    @if($r->ward)<div class="text-muted">{{ $r->ward->name }}</div>@endif
                                </td>
                                <td><span class="badge bg-success-subtle text-success text-uppercase">{{ $r->status }}</span></td>
                                <td>{{ $r->notes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No staff rostered for {{ $selectedDate }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('manage', \App\Modules\Hr\Models\DutyRoster::class)
        <div class="col-xl-4">
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Assign Duty Shift</h2>
                <form method="POST" action="{{ route('rosters.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="staff_profile_id">Staff Member <span class="text-danger">*</span></label>
                        <select id="staff_profile_id" name="staff_profile_id" class="form-select form-select-sm" required>
                            @foreach($staffProfiles as $sp)
                                <option value="{{ $sp->id }}">{{ $sp->user->name }} — {{ $sp->designation }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="department_id">Department <span class="text-danger">*</span></label>
                        <select id="department_id" name="department_id" class="form-select form-select-sm" required>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="ward_id">Ward Assignment (Optional)</label>
                        <select id="ward_id" name="ward_id" class="form-select form-select-sm">
                            <option value="">None / General Department</option>
                            @foreach($wards as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="duty_date">Date</label>
                            <input type="date" id="duty_date" name="duty_date" value="{{ $selectedDate }}" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="shift">Shift</label>
                            <select id="shift" name="shift" class="form-select form-select-sm" required>
                                <option value="morning">Morning (08:00–14:00)</option>
                                <option value="evening">Evening (14:00–20:00)</option>
                                <option value="night">Night (20:00–08:00)</option>
                                <option value="on_call">On-Call Cardiac</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="notes">Roster Notes</label>
                        <input type="text" id="notes" name="notes" class="form-control form-control-sm" placeholder="e.g., Primary Cath Lab On-Call">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-sm btn-primary fw-semibold">Assign Shift</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
@endsection
