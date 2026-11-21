@extends('layouts.app')

@section('title', 'Pre-Consult Vitals & Risk Triage')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-heart-pulse me-2 text-danger"></i>OPD Pre-Consult Assessment Station (Nurse / Junior MO)</h1>
        <small class="text-muted">Capture vitals, cardiac risk factors, and allergies outside the room so the Consultant starts with a pre-filled chart ({{ $todayDate }})</small>
    </div>
    <a href="{{ route('opd.index') }}" class="btn btn-sm btn-outline-secondary">Full OPD Queue</a>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@forelse($queue as $appt)
    @php
        $enc = $appt->encounter;
        $latestVital = $enc?->vitalSigns?->first();
    @endphp
    <div class="hmis-card p-3 mb-3 {{ $latestVital ? 'border-success' : 'border-warning' }}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-dark fs-6 font-monospace">{{ $appt->formattedToken() }}</span>
                <div>
                    <span class="fw-bold">{{ $appt->patient->full_name }}</span>
                    <span class="text-muted small ms-1 hmis-mrn-badge">{{ $appt->patient->mrn }}</span>
                    <span class="text-muted small ms-1">&middot; {{ $appt->patient->ageDisplay() }} / {{ ucfirst($appt->patient->sex) }}</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($latestVital)
                    <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Vitals Ready (BP {{ $latestVital->systolic_bp }}/{{ $latestVital->diastolic_bp }}, HR {{ $latestVital->heart_rate }})</span>
                @else
                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending Pre-Assessment</span>
                @endif
                @can('view', $enc)
                    <a href="{{ route('encounters.rapid.show', $enc) }}" class="btn btn-sm btn-warning fw-semibold">
                        <i class="bi bi-lightning-charge-fill"></i> Rapid Consult
                    </a>
                @endcan
            </div>
        </div>

        <form method="POST" action="{{ route('opd.pre-consult.store', $enc) }}">
            @csrf
            <div class="row g-2 mb-2">
                <div class="col-4 col-md-2">
                    <label class="form-label small mb-1 fw-semibold">Sys BP *</label>
                    <input type="number" name="systolic_bp" class="form-control form-control-sm" value="{{ $latestVital?->systolic_bp }}" required placeholder="130">
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label small mb-1 fw-semibold">Dia BP *</label>
                    <input type="number" name="diastolic_bp" class="form-control form-control-sm" value="{{ $latestVital?->diastolic_bp }}" required placeholder="80">
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label small mb-1 fw-semibold">HR (bpm) *</label>
                    <input type="number" name="heart_rate" class="form-control form-control-sm" value="{{ $latestVital?->heart_rate }}" required placeholder="78">
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label small mb-1 fw-semibold">SpO2 (%) *</label>
                    <input type="number" name="spo2_percent" class="form-control form-control-sm" value="{{ $latestVital?->spo2_percent ?? 98 }}" required placeholder="98">
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label small mb-1">Weight (kg)</label>
                    <input type="number" step="0.1" name="weight_kg" class="form-control form-control-sm" value="{{ $latestVital?->weight_kg }}" placeholder="72">
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label small mb-1">RBS (mg/dL)</label>
                    <input type="number" name="blood_glucose_mg_dl" class="form-control form-control-sm" value="{{ $latestVital?->blood_glucose_mg_dl }}" placeholder="110">
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label small mb-1 fw-semibold">Chief Complaint *</label>
                    <input type="text" name="chief_complaint" class="form-control form-control-sm" value="{{ $enc->chief_complaint ?: $appt->chief_complaint ?: 'Cardiology OPD review' }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Report New Allergy (Optional)</label>
                    <input type="text" name="new_allergy_substance" class="form-control form-control-sm" placeholder="e.g. Aspirin / Contrast">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Allergy Category</label>
                    <select name="new_allergy_category" class="form-select form-select-sm">
                        <option value="medication">Medication</option>
                        <option value="contrast_dye">IV Contrast Dye</option>
                        <option value="food">Food</option>
                    </select>
                </div>
            </div>

            <div class="mb-2">
                <div class="small fw-semibold mb-1">Cardiac Risk Factors (Tap applicable):</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($riskFactors as $rf)
                        <div class="form-check form-check-inline small m-0">
                            <input class="form-check-input" type="checkbox" name="risk_factors[]" value="{{ $rf }}" id="rf_{{ $enc->id }}_{{ $loop->index }}">
                            <label class="form-check-label" for="rf_{{ $enc->id }}_{{ $loop->index }}">{{ $rf }}</label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-sm btn-primary fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i>Save Pre-Consult &amp; Mark Ready for Consultant
                </button>
            </div>
        </form>
    </div>
@empty
    <div class="hmis-card p-5 text-center text-muted">
        No checked-in OPD patients awaiting pre-consult vitals right now. Check in patients from the <a href="{{ route('opd.index') }}">OPD Queue</a>.
    </div>
@endforelse
@endsection
