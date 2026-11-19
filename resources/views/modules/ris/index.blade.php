@extends('layouts.app')

@section('title', 'Radiology Information System (RIS)')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-image-fill me-2 text-primary"></i>Radiology &amp; Diagnostic Imaging (RIS / PACS-Ready)</h1>
        <small class="text-muted">Accession number tracking, contrast allergy checks, radiology reporting, and DICOM StudyInstanceUID linkage</small>
    </div>
    <span class="badge {{ $pacsGateway->isEnabled() ? 'bg-success' : 'bg-secondary' }}">
        PACS Gateway: {{ $pacsGateway->isEnabled() ? 'Orthanc / OHIF Connected' : 'PACS-Ready (Phase 6 Orthanc Pending)' }}
    </span>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="hmis-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Accession #</th>
                    <th>Patient (MRN)</th>
                    <th>Modality / Procedure</th>
                    <th>Indication / Safety</th>
                    <th>Status</th>
                    <th>Report &amp; PACS UID</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($studies as $study)
                    @php
                        $contrastAllergy = $study->catalog?->requires_contrast && $study->patient->activeAllergies->where('category', 'contrast_dye')->isNotEmpty();
                        $viewerUrl = $pacsGateway->buildViewerUrl($study, auth()->id());
                    @endphp
                    <tr class="{{ $contrastAllergy ? 'table-warning' : '' }}">
                        <td class="font-monospace fw-semibold">{{ $study->accession_number }}</td>
                        <td>
                            <a href="{{ route('patients.show', $study->patient) }}" class="fw-semibold text-decoration-none">{{ $study->patient->full_name }}</a>
                            <div class="text-muted hmis-mrn-badge">{{ $study->patient->mrn }}</div>
                        </td>
                        <td>
                            <span class="badge bg-dark me-1">{{ $study->modality }}</span>
                            <span class="fw-semibold">{{ $study->procedure_name }}</span>
                        </td>
                        <td>
                            <div>{{ $study->clinical_indication ?? '—' }}</div>
                            @if($contrastAllergy)
                                <span class="badge bg-danger mt-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>IV CONTRAST ALLERGY</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $study->status === 'verified' ? 'bg-success' : ($study->status === 'reported' ? 'bg-primary' : 'bg-info text-dark') }} text-uppercase">
                                {{ str_replace('_', ' ', $study->status) }}
                            </span>
                        </td>
                        <td>
                            <div>{{ \Illuminate\Support\Str::limit($study->report_summary ?? 'Pending radiology report', 80) }}</div>
                            @if($study->study_instance_uid)
                                <div class="text-muted font-monospace" style="font-size: 0.72rem;">UID: {{ $study->study_instance_uid }}</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if($viewerUrl)
                                <a href="{{ $viewerUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">OHIF</a>
                            @elseif($pacsGateway->isEnabled() && empty($study->study_instance_uid))
                                @can('report', \App\Modules\Pacs\Models\ImagingStudy::class)
                                    <form method="POST" action="{{ route('ris.studies.sync-pacs', $study) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">Sync PACS</button>
                                    </form>
                                @endcan
                            @endif
                            @if($study->status !== 'verified')
                                @can('report', \App\Modules\Pacs\Models\ImagingStudy::class)
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#risReportModal-{{ $study->id }}">Report</button>
                                @endcan
                            @endif
                        </td>
                    </tr>

                    @if($study->status !== 'verified')
                        @can('report', \App\Modules\Pacs\Models\ImagingStudy::class)
                            <div class="modal fade" id="risReportModal-{{ $study->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <form method="POST" action="{{ route('ris.studies.report', $study) }}" class="modal-content">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title">Radiology Report — {{ $study->accession_number }} ({{ $study->procedure_name }})</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">DICOM StudyInstanceUID (Optional until PACS sync)</label>
                                                <input type="text" name="study_instance_uid" value="{{ $study->study_instance_uid }}" class="form-control form-control-sm font-monospace" placeholder="1.2.826.0.1.3680043...">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Findings &amp; Impression <span class="text-danger">*</span></label>
                                                <textarea name="report_summary" rows="4" class="form-control" required minlength="10">{{ $study->report_summary }}</textarea>
                                            </div>
                                            @can('verify', \App\Modules\Pacs\Models\ImagingStudy::class)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="verify_now" value="1" id="verifyNow-{{ $study->id }}" checked>
                                                    <label class="form-check-label small fw-semibold" for="verifyNow-{{ $study->id }}">Digitally Sign &amp; Verify Final Radiology Report</label>
                                                </div>
                                            @endcan
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Radiology Report</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcan
                    @endif
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No diagnostic imaging orders recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
