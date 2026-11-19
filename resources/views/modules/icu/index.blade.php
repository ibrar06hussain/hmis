@extends('layouts.app')

@section('title', 'ICU / CCU Bedside Device Telemetry')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-activity me-2 text-danger"></i>ICU &amp; CCU Bedside Monitor Telemetry Staging</h1>
        <small class="text-muted">Inbound bedside monitor, ventilator, and infusion pump readings stay <strong>Unverified Raw</strong> until explicitly validated by a clinician or nurse</small>
    </div>
</div>

<div class="hmis-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Bed / Device</th>
                    <th>Patient (MRN)</th>
                    <th>Parameter &amp; Reading</th>
                    <th>Signal Quality</th>
                    <th>Verification State</th>
                    <th>Observed (PKT)</th>
                    <th class="text-end">Clinical Validation</th>
                </tr>
            </thead>
            <tbody>
                @forelse($observations as $obs)
                    <tr class="{{ $obs->is_abnormal && $obs->verification_status !== 'rejected_artifact' ? 'table-danger' : '' }}">
                        <td>
                            <div class="fw-bold font-monospace">{{ $obs->bed?->bed_number ?? 'Unassigned' }}</div>
                            <span class="text-muted font-monospace">{{ $obs->device_code }}</span>
                        </td>
                        <td>
                            <a href="{{ route('patients.show', $obs->patient) }}" class="fw-semibold text-decoration-none">{{ $obs->patient->full_name }}</a>
                            <div class="text-muted hmis-mrn-badge">{{ $obs->patient->mrn }}</div>
                        </td>
                        <td>
                            <span class="text-uppercase fw-semibold">{{ str_replace('_', ' ', $obs->observation_type) }}:</span>
                            <span class="fw-bold fs-6 ms-1">{{ $obs->value_numeric !== null ? $obs->value_numeric.' '.$obs->unit : '—' }}</span>
                            @if($obs->is_abnormal)<span class="badge bg-danger ms-1">ABNORMAL</span>@endif
                        </td>
                        <td>
                            <span class="badge {{ $obs->data_quality === 'good' ? 'bg-success-subtle text-success' : 'bg-danger' }} text-uppercase">
                                {{ str_replace('_', ' ', $obs->data_quality) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $obs->verification_status === 'clinician_verified' ? 'bg-success' : ($obs->verification_status === 'rejected_artifact' ? 'bg-dark' : 'bg-warning text-dark') }} text-uppercase">
                                {{ str_replace('_', ' ', $obs->verification_status) }}
                            </span>
                        </td>
                        <td>{{ \App\Support\DateTimeHelper::formatDateTime($obs->observed_at, 'd M, H:i:s') }}</td>
                        <td class="text-end text-nowrap">
                            @if($obs->verification_status === 'unverified_raw')
                                @can('record', \App\Modules\Nursing\Models\NursingMarRecord::class)
                                    <form method="POST" action="{{ route('icu.observations.verify', $obs) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">Verify</button>
                                    </form>
                                    <form method="POST" action="{{ route('icu.observations.reject', $obs) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject Artifact</button>
                                    </form>
                                @endcan
                            @else
                                <span class="text-muted">{{ $obs->verifiedBy?->name }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No ICU bedside monitor telemetry observations ingested yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
