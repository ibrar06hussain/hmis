@extends('layouts.app')

@section('title', 'Consultant 1-Click Co-Sign Tray')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-pen-fill me-2 text-primary"></i>Consultant 1-Click Co-Sign Tray (MO / Scribe Drafts)</h1>
        <small class="text-muted">Review clinical drafts entered by Medical Officers or Nurses during examination and digitally sign &amp; lock in 1 click</small>
    </div>
    <span class="badge bg-primary fs-6">{{ $pendingDrafts->count() }} Pending Sign-Off</span>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@forelse($pendingDrafts as $note)
    @php
        $enc = $note->encounter;
        $latestVital = $enc?->vitalSigns?->first();
    @endphp
    <div class="hmis-card p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 border-bottom pb-2 mb-2">
            <div>
                <span class="badge bg-info text-dark text-uppercase">{{ str_replace('_', ' ', $note->note_type) }} DRAFT</span>
                <strong class="ms-2">{{ $note->patient->full_name }}</strong>
                <span class="hmis-mrn-badge text-primary small ms-1">{{ $note->patient->mrn }}</span>
                @if($latestVital)
                    <span class="badge {{ $latestVital->is_abnormal ? 'bg-danger' : 'bg-light text-dark border' }} ms-1">
                        BP {{ $latestVital->systolic_bp }}/{{ $latestVital->diastolic_bp }} &middot; HR {{ $latestVital->heart_rate }}
                    </span>
                @endif
            </div>
            <small class="text-muted">
                Drafted by <strong>{{ $note->authorUser?->name ?? 'Staff' }}</strong> &middot; {{ \App\Support\DateTimeHelper::formatDateTime($note->created_at, 'd M Y, H:i') }} PKT
            </small>
        </div>

        @if($note->patient->activeAllergies->isNotEmpty())
            <div class="hmis-allergy-strip p-1 px-2 rounded mb-2 small">
                <strong>ALLERGIES:</strong>
                @foreach($note->patient->activeAllergies as $a)
                    <span class="badge bg-danger ms-1">{{ $a->substance }}</span>
                @endforeach
            </div>
        @endif

        <div class="row g-2 small mb-3">
            <div class="col-md-6">
                <div><strong>Chief Complaint:</strong> {{ $note->chief_complaint }}</div>
                @if($note->past_medical_history)<div><strong>PMH / Risk Factors:</strong> {{ $note->past_medical_history }}</div>@endif
                @if($note->physical_examination)<div><strong>Exam:</strong> {{ $note->physical_examination }}</div>@endif
            </div>
            <div class="col-md-6">
                @if($note->assessment_and_diagnosis)<div><strong>Assessment:</strong> {{ $note->assessment_and_diagnosis }}</div>@endif
                @if($note->treatment_plan)<div><strong>Plan:</strong> {{ $note->treatment_plan }}</div>@endif
                @if($enc && $enc->prescriptions->isNotEmpty())
                    <div class="mt-1">
                        <strong>Prescriptions:</strong>
                        @foreach($enc->prescriptions->flatMap(fn ($rx) => $rx->items) as $item)
                            <span class="badge bg-light text-dark border">{{ $item->medication?->generic_name }} {{ $item->frequency }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
            <div class="d-flex gap-2">
                @if($enc)
                    <a href="{{ route('encounters.rapid.show', $enc) }}" class="btn btn-sm btn-outline-primary">Edit / Expand in Rapid Consult</a>
                    <a href="{{ route('encounters.show', $enc) }}" class="btn btn-sm btn-outline-secondary">Open Full Chart</a>
                @endif
            </div>
            <form method="POST" action="{{ route('emr.cosign.approve', $note) }}" class="d-inline-flex flex-wrap align-items-center gap-2">
                @csrf
                <div class="form-check form-check-inline small m-0">
                    <input class="form-check-input" type="checkbox" name="complete_encounter" value="1" id="ce_{{ $note->id }}" checked>
                    <label class="form-check-label" for="ce_{{ $note->id }}">Complete Visit</label>
                </div>
                <button type="submit" name="print_after" value="0" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i>Verify &amp; Co-Sign (1 Click)
                </button>
                <button type="submit" name="print_after" value="1" class="btn btn-sm btn-primary fw-semibold">
                    <i class="bi bi-printer-fill me-1"></i>Sign &amp; Print Rx
                </button>
            </form>
        </div>
    </div>
@empty
    <div class="hmis-card p-5 text-center text-muted">
        <i class="bi bi-check-circle text-success fs-2 d-block mb-2"></i>
        All clinical draft notes are signed. Zero pending co-signatures.
    </div>
@endforelse
@endsection
