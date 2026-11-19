@extends('layouts.app')

@section('title', 'Authorized Patient Merge')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-intersect me-2 text-warning"></i>Authorized Master Patient Index Merge</h1>
        <small class="text-muted">Reassigns all encounters, allergies, imaging studies, and identifiers to the canonical surviving MRN with full audit history.</small>
    </div>
    <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary">Back to MPI</a>
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

<form method="POST" action="{{ route('patients.merge.execute') }}" class="hmis-card p-3 p-md-4" onsubmit="return confirm('Confirm permanent merge of the selected source patient into the canonical target patient?');">
    @csrf

    <div class="alert alert-warning small">
        <strong>Clinical Safety Rule:</strong> Patient records are never deleted. The source MRN is retired as <code>merged</code> and stored as a resolvable historical alias on the target patient.
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label for="source_patient_id" class="form-label fw-semibold">Source Patient (Duplicate / Temporary Record to Retire)</label>
            <select id="source_patient_id" name="source_patient_id" class="form-select" required>
                <option value="">Select duplicate patient...</option>
                @foreach($patients as $p)
                    <option value="{{ $p->id }}" @selected((int) old('source_patient_id', $preselectedSourceId) === $p->id)>
                        {{ $p->mrn }} — {{ $p->full_name }} ({{ $p->formattedCnic() ?? 'No CNIC' }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label for="target_patient_id" class="form-label fw-semibold">Target Canonical Patient (Surviving MRN)</label>
            <select id="target_patient_id" name="target_patient_id" class="form-select" required>
                <option value="">Select surviving canonical patient...</option>
                @foreach($patients as $p)
                    <option value="{{ $p->id }}" @selected((int) old('target_patient_id') === $p->id)>
                        {{ $p->mrn }} — {{ $p->full_name }} ({{ $p->formattedCnic() ?? 'No CNIC' }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mb-4">
        <label for="reason" class="form-label fw-semibold">Merge Reason / Identity Verification Evidence <span class="text-danger">*</span></label>
        <textarea id="reason" name="reason" rows="3" class="form-control" required minlength="10" placeholder="e.g., Confirmed same patient via original NADRA CNIC after emergency admission; merging temporary ER MRN into permanent OPD MRN.">{{ old('reason') }}</textarea>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-warning fw-semibold">Execute Audited Merge</button>
    </div>
</form>
@endsection
