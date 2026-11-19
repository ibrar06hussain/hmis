@extends('layouts.app')

@section('title', 'MRN Card — '.$patient->mrn)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h1 class="h5 fw-bold mb-0">Printable Patient MRN Identification Card</h1>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Print Card</button>
        <a href="{{ route('patients.show', $patient) }}" class="btn btn-outline-secondary">Back to Profile</a>
    </div>
</div>

<div class="card border-dark mx-auto" style="max-width: 460px;">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <div>
            <div class="fw-bold">{{ config('hmis.facility_name') }}</div>
            <small class="text-white-50">{{ config('hmis.facility_region') }}</small>
        </div>
        <span class="badge bg-danger">CARDIAC HMIS</span>
    </div>
    <div class="card-body">
        <div class="p-2 mb-3 rounded bg-light border text-center">
            <div class="small text-muted text-uppercase">Permanent Medical Record Number</div>
            <div class="fs-4 hmis-mrn-badge text-dark">{{ $patient->mrn }}</div>
        </div>
        <dl class="row small mb-0">
            <dt class="col-4 text-muted">Patient Name</dt>
            <dd class="col-8 fw-bold">{{ $patient->full_name }}</dd>

            <dt class="col-4 text-muted">Father/Spouse</dt>
            <dd class="col-8">{{ $patient->father_or_spouse_name ?? '—' }}</dd>

            <dt class="col-4 text-muted">Age / Sex</dt>
            <dd class="col-8">{{ $patient->ageDisplay() }} / {{ ucfirst($patient->sex) }}</dd>

            <dt class="col-4 text-muted">Blood Group</dt>
            <dd class="col-8">{{ $patient->blood_group ?? 'Unknown' }}</dd>

            <dt class="col-4 text-muted">Phone</dt>
            <dd class="col-8">{{ $patient->phone_primary ?? '—' }}</dd>

            <dt class="col-4 text-muted">Issued (PKT)</dt>
            <dd class="col-8">{{ \App\Support\DateTimeHelper::formatDateTime($patient->created_at, 'd M Y') }}</dd>
        </dl>
    </div>
    <div class="card-footer small text-muted text-center">
        Please bring this card on every OPD, Emergency, Lab, or Diagnostic visit.
    </div>
</div>
@endsection
