@extends('layouts.app')

@section('title', $patient->full_name.' ('.$patient->mrn.')')

@section('content')
{{-- Persistent Unified Patient Identity Banner --}}
<div class="hmis-patient-banner p-3 p-md-4 mb-3 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge bg-light text-dark hmis-mrn-badge fs-6">{{ $patient->mrn }}</span>
                <span class="badge bg-info text-dark text-uppercase">{{ $patient->registration_type }}</span>
                @if($patient->identity_verification_status === 'unverified')
                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill me-1"></i>UNVERIFIED EMERGENCY IDENTITY</span>
                @elseif($patient->identity_verification_status === 'pending_cnic')
                    <span class="badge bg-secondary">CNIC Pending</span>
                @else
                    <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Identity Verified</span>
                @endif
                @if($hasActiveBreakGlass)
                    <span class="badge bg-danger"><i class="bi bi-shield-exclamation me-1"></i>BREAK-GLASS SESSION ACTIVE</span>
                @endif
            </div>
            <h1 class="h3 fw-bold mb-1">{{ $patient->full_name }}</h1>
            <div class="text-white-50 small d-flex flex-wrap gap-3">
                @if($patient->father_or_spouse_name)
                    <span><strong>S/O, D/O, W/O:</strong> {{ $patient->father_or_spouse_name }}</span>
                @endif
                <span><strong>Age / Sex:</strong> {{ $patient->ageDisplay() }} / {{ ucfirst($patient->sex) }}</span>
                <span><strong>Blood Group:</strong> {{ $patient->blood_group ?? 'Unknown' }}</span>
                <span><strong>CNIC:</strong> {{ $patient->formattedCnic() ?? 'Not recorded' }}</span>
                <span><strong>Phone:</strong> {{ $patient->phone_primary ?? 'Not recorded' }}</span>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 no-print">
            @can('create', \App\Modules\Appointments\Models\Appointment::class)
                <a href="{{ route('opd.index', ['patient_id' => $patient->id]) }}" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-calendar-plus me-1"></i>Book OPD Visit
                </a>
            @endcan
            <a href="{{ route('patients.card', $patient) }}" class="btn btn-sm btn-light">
                <i class="bi bi-printer me-1"></i>MRN Card
            </a>
            @can('update', $patient)
                <a href="{{ route('patients.edit', $patient) }}" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-pencil-square me-1"></i>Edit Demographics
                </a>
            @endcan
            @can('merge', \App\Modules\Patient\Models\Patient::class)
                <a href="{{ route('patients.merge.form', ['source_id' => $patient->id]) }}" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-intersect me-1"></i>Merge Record
                </a>
            @endcan
        </div>
    </div>
</div>

{{-- Clinical Safety Strip: Allergies (Visible only to authorized clinical viewers) --}}
@if($canViewClinical)
    @if($patient->activeAllergies->isNotEmpty())
        <div class="hmis-allergy-strip rounded p-3 mb-3">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-2"></i>CRITICAL ALLERGY ALERT ({{ $patient->activeAllergies->count() }})</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($patient->activeAllergies as $allergy)
                    <span class="badge bg-danger">
                        {{ $allergy->substance }} ({{ strtoupper($allergy->severity) }})
                        @if($allergy->reaction) — {{ $allergy->reaction }} @endif
                    </span>
                @endforeach
            </div>
        </div>
    @else
        <div class="hmis-allergy-strip-safe rounded px-3 py-2 mb-3 small">
            <i class="bi bi-check-circle-fill me-2"></i>No active allergies currently recorded in HMIS. Verify directly with patient before prescribing or administering contrast/medications.
        </div>
    @endif
@else
    <div class="alert alert-secondary d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <i class="bi bi-lock-fill me-2"></i>
            <strong>Clinical Chart Restricted:</strong> Your current role or department assignment grants demographic view only.
        </div>
        @if($canBreakGlass)
            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#breakGlassModal">
                <i class="bi bi-shield-exclamation me-1"></i>Emergency Break-Glass Access
            </button>
        @endif
    </div>
@endif

{{-- Unified Patient Profile Tabbed Workspace --}}
<ul class="nav nav-tabs mb-3 no-print" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'overview' ? 'active fw-semibold' : '' }}" href="{{ route('patients.show', [$patient, 'tab' => 'overview']) }}">
            <i class="bi bi-person-vcard me-1"></i>Overview &amp; Identity
        </a>
    </li>
    @if($canViewClinical)
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'encounters' ? 'active fw-semibold' : '' }}" href="{{ route('patients.show', [$patient, 'tab' => 'encounters']) }}">
                <i class="bi bi-clipboard2-pulse me-1"></i>Visits &amp; Admissions
                <span class="badge bg-secondary ms-1">{{ $patient->encounters->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'vitals' ? 'active fw-semibold' : '' }}" href="{{ route('patients.show', [$patient, 'tab' => 'vitals']) }}">
                <i class="bi bi-activity me-1"></i>Vitals &amp; Notes
                <span class="badge bg-secondary ms-1">{{ $patient->vitalSigns->count() + $patient->clinicalNotes->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'diagnostics' ? 'active fw-semibold' : '' }}" href="{{ route('patients.show', [$patient, 'tab' => 'diagnostics']) }}">
                <i class="bi bi-heart-pulse me-1"></i>Labs, Imaging &amp; PACS
                <span class="badge bg-secondary ms-1">{{ $patient->imagingStudies->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'allergies' ? 'active fw-semibold' : '' }}" href="{{ route('patients.show', [$patient, 'tab' => 'allergies']) }}">
                <i class="bi bi-shield-plus me-1"></i>Allergies &amp; Conditions
            </a>
        </li>
    @endif
</ul>

@if($activeTab === 'overview' || ! $canViewClinical)
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="hmis-card p-3 h-100">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Demographics &amp; Registration Metadata</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-5 text-muted">Permanent MRN</dt>
                    <dd class="col-sm-7 hmis-mrn-badge">{{ $patient->mrn }}</dd>

                    <dt class="col-sm-5 text-muted">FHIR Resource UUID</dt>
                    <dd class="col-sm-7 font-monospace small">{{ $patient->uuid }}</dd>

                    <dt class="col-sm-5 text-muted">Date of Birth</dt>
                    <dd class="col-sm-7">
                        {{ \App\Support\DateTimeHelper::formatDate($patient->date_of_birth) }}
                        @if($patient->dob_estimated)<span class="badge bg-light text-dark border ms-1">Estimated</span>@endif
                    </dd>

                    <dt class="col-sm-5 text-muted">Address</dt>
                    <dd class="col-sm-7">
                        {{ collect([$patient->address_line, $patient->city, $patient->tehsil, $patient->district, $patient->province])->filter()->implode(', ') ?: 'Not recorded' }}
                    </dd>

                    <dt class="col-sm-5 text-muted">Registered At (PKT)</dt>
                    <dd class="col-sm-7">{{ \App\Support\DateTimeHelper::formatDateTime($patient->created_at) }}</dd>

                    <dt class="col-sm-5 text-muted">Registered By</dt>
                    <dd class="col-sm-7">{{ $patient->registeredBy?->name ?? 'System' }}</dd>

                    @if($patient->registration_notes)
                        <dt class="col-sm-5 text-muted">Registration Notes</dt>
                        <dd class="col-sm-7">{{ $patient->registration_notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="hmis-card p-3 mb-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">External Identifiers &amp; Historical MRNs</h2>
                @forelse($patient->identifiers as $id)
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
                        <div>
                            <span class="badge bg-light text-dark border text-uppercase me-2">{{ str_replace('_', ' ', $id->system) }}</span>
                            <span class="font-monospace fw-semibold">{{ $id->value }}</span>
                        </div>
                        <span class="text-muted">{{ $id->issuer ?? '—' }}</span>
                    </div>
                @empty
                    <div class="text-muted small">No additional identifiers recorded.</div>
                @endforelse
            </div>

            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Emergency Contacts &amp; Guardians</h2>
                @forelse($patient->contacts as $contact)
                    <div class="py-1 border-bottom small">
                        <div class="fw-semibold">
                            {{ $contact->full_name }}
                            <span class="badge bg-secondary-subtle text-dark ms-1">{{ ucfirst(str_replace('_', ' ', $contact->contact_type)) }}</span>
                        </div>
                        <div class="text-muted">{{ $contact->relationship }} &middot; {{ $contact->phone ?? 'No phone' }}</div>
                    </div>
                @empty
                    <div class="text-muted small">No emergency or guardian contact recorded.</div>
                @endforelse
            </div>
        </div>

        @if($patient->mergesAsTarget->isNotEmpty())
            <div class="col-12">
                <div class="hmis-card p-3">
                    <h2 class="h6 fw-bold border-bottom pb-2 mb-2">Identity Merge Audit History</h2>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 small">
                            <thead><tr><th>Merged Source MRN</th><th>Merged By</th><th>Reason</th><th>Merged At (PKT)</th></tr></thead>
                            <tbody>
                                @foreach($patient->mergesAsTarget as $merge)
                                    <tr>
                                        <td class="hmis-mrn-badge">{{ $merge->sourcePatient?->mrn }}</td>
                                        <td>{{ $merge->mergedBy?->name }}</td>
                                        <td>{{ $merge->reason }}</td>
                                        <td>{{ \App\Support\DateTimeHelper::formatDateTime($merge->merged_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@elseif($activeTab === 'encounters')
    <div class="hmis-card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0">OPD Consultations, Emergency Visits &amp; IPD Admissions</h2>
            @can('create', \App\Modules\Appointments\Models\Appointment::class)
                <a href="{{ route('opd.index', ['patient_id' => $patient->id]) }}" class="btn btn-sm btn-primary">Book OPD Visit</a>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Encounter #</th><th>Class</th><th>Department</th><th>Attending Clinician</th><th>Chief Complaint</th><th>Started (PKT)</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($patient->encounters as $encounter)
                        <tr>
                            <td class="font-monospace fw-semibold">{{ $encounter->encounter_number }}</td>
                            <td><span class="badge bg-primary-subtle text-primary">{{ $encounter->class }}</span></td>
                            <td>{{ $encounter->department?->name }}</td>
                            <td>{{ $encounter->attendingStaff?->user?->name ?? 'Unassigned' }}</td>
                            <td>{{ $encounter->chief_complaint ?? '—' }}</td>
                            <td>{{ \App\Support\DateTimeHelper::formatDateTime($encounter->started_at) }}</td>
                            <td><span class="badge bg-info text-dark text-uppercase">{{ $encounter->status }}</span></td>
                            <td class="text-end">
                                @can('view', $encounter)
                                    <a href="{{ route('encounters.show', $encounter) }}" class="btn btn-sm btn-outline-primary">Workspace</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No encounters recorded for this patient yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@elseif($activeTab === 'vitals')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="hmis-card p-3 h-100">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Vital Signs History ({{ $patient->vitalSigns->count() }})</h2>
                @forelse($patient->vitalSigns as $v)
                    <div class="p-2 mb-2 rounded border {{ $v->is_abnormal ? 'border-danger bg-danger-subtle' : 'bg-light' }} small">
                        <div class="d-flex justify-content-between fw-semibold">
                            <span>BP {{ $v->systolic_bp ?? '—' }}/{{ $v->diastolic_bp ?? '—' }} &middot; HR {{ $v->heart_rate ?? '—' }} &middot; SpO2 {{ $v->spo2_percent ?? '—' }}%</span>
                            <span class="text-muted">{{ \App\Support\DateTimeHelper::formatDateTime($v->recorded_at, 'd M, H:i') }}</span>
                        </div>
                        @if($v->is_abnormal && !empty($v->abnormal_flags))
                            <div class="mt-1">
                                @foreach($v->abnormal_flags as $flag)
                                    <span class="badge bg-danger">{{ str_replace('_', ' ', $flag) }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-muted small">No vital signs recorded yet.</div>
                @endforelse
            </div>
        </div>
        <div class="col-lg-7">
            <div class="hmis-card p-3 h-100">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Clinical Notes &amp; Amendments ({{ $patient->clinicalNotes->count() }})</h2>
                @forelse($patient->clinicalNotes as $note)
                    <div class="border rounded p-2 mb-2 small {{ $note->status === 'amended' ? 'bg-light opacity-75' : '' }}">
                        <div class="d-flex justify-content-between mb-1">
                            <div>
                                <span class="badge bg-dark text-uppercase">{{ str_replace('_', ' ', $note->note_type) }}</span>
                                <span class="badge bg-secondary">Rev {{ $note->revision_number }}</span>
                                <span class="badge {{ $note->status === 'signed' ? 'bg-success' : ($note->status === 'amended' ? 'bg-warning text-dark' : 'bg-info text-dark') }}">{{ strtoupper($note->status) }}</span>
                            </div>
                            <span class="text-muted">{{ \App\Support\DateTimeHelper::formatDateTime($note->created_at, 'd M Y, H:i') }}</span>
                        </div>
                        <div><strong>CC:</strong> {{ $note->chief_complaint }}</div>
                        @if($note->assessment_and_diagnosis)
                            <div><strong>Assessment:</strong> {{ $note->assessment_and_diagnosis }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-muted small">No structured clinical notes documented yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@elseif($activeTab === 'diagnostics')
    <div class="row g-3">
        <div class="col-12">
            <div class="hmis-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h2 class="h6 fw-bold mb-0">Diagnostic Imaging, Echo/ECG &amp; PACS Studies</h2>
                        <small class="text-muted">PACS-ready architecture using DICOM Accession Number &amp; StudyInstanceUID (zero pixel data in HMIS DB)</small>
                    </div>
                    <span class="badge {{ $pacsGateway->isEnabled() ? 'bg-success' : 'bg-secondary' }}">
                        PACS Gateway: {{ $pacsGateway->isEnabled() ? 'Connected (Orthanc/OHIF)' : 'Deferred (Phase 6 Ready)' }}
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Accession #</th><th>Modality</th><th>Procedure</th><th>Status</th><th>Report Summary</th><th>PACS Study</th></tr>
                        </thead>
                        <tbody>
                            @forelse($patient->imagingStudies as $study)
                                @php($viewerUrl = $pacsGateway->buildViewerUrl($study, auth()->id()))
                                <tr>
                                    <td class="font-monospace">{{ $study->accession_number }}</td>
                                    <td><span class="badge bg-dark">{{ $study->modality }}</span></td>
                                    <td>{{ $study->procedure_name }}</td>
                                    <td><span class="badge bg-info text-dark">{{ ucfirst($study->status) }}</span></td>
                                    <td class="small">{{ $study->report_summary ?? 'Pending report' }}</td>
                                    <td>
                                        @if($viewerUrl)
                                            <a href="{{ $viewerUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">Open in OHIF</a>
                                        @else
                                            <span class="badge bg-light text-muted border" title="StudyInstanceUID: {{ $study->study_instance_uid ?? 'Pending' }}">
                                                UID Linked (Viewer Pending PACS)
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">No imaging studies recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@elseif($activeTab === 'allergies')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Recorded Allergies &amp; Adverse Reactions</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Substance</th><th>Category</th><th>Severity</th><th>Reaction</th><th>Status</th><th>Recorded (PKT)</th></tr>
                        </thead>
                        <tbody>
                            @forelse($patient->allergies as $allergy)
                                <tr>
                                    <td class="fw-semibold">{{ $allergy->substance }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $allergy->category)) }}</td>
                                    <td><span class="badge {{ in_array($allergy->severity, ['severe', 'life_threatening'], true) ? 'bg-danger' : 'bg-warning text-dark' }}">{{ strtoupper($allergy->severity) }}</span></td>
                                    <td>{{ $allergy->reaction ?? '—' }}</td>
                                    <td>{{ ucfirst($allergy->clinical_status) }}</td>
                                    <td>{{ \App\Support\DateTimeHelper::formatDateTime($allergy->recorded_at) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">No allergies recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="hmis-card p-3 mt-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Coded Diagnoses History (ICD-10 / ICD-11)</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Code</th><th>Version</th><th>Diagnosis</th><th>Rank</th><th>Status</th><th>Recorded (PKT)</th></tr>
                        </thead>
                        <tbody>
                            @forelse($patient->diagnoses as $diag)
                                <tr>
                                    <td class="font-monospace fw-semibold">{{ $diag->coding_system }} {{ $diag->code }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $diag->terminology_version }}</span></td>
                                    <td>{{ $diag->display_name }}</td>
                                    <td><span class="badge {{ $diag->diagnosis_rank === 'primary' ? 'bg-primary' : 'bg-secondary' }} text-uppercase">{{ $diag->diagnosis_rank }}</span></td>
                                    <td class="text-uppercase small">{{ $diag->clinical_status }}</td>
                                    <td>{{ \App\Support\DateTimeHelper::formatDateTime($diag->recorded_at) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">No coded diagnoses recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @if(auth()->user()->can('emr.note.create') || auth()->user()->can('emr.vitals.record'))
            <div class="col-lg-4">
                <div class="hmis-card p-3">
                    <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Record New Allergy</h2>
                    <form method="POST" action="{{ route('patients.allergies.store', $patient) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-semibold" for="substance">Substance <span class="text-danger">*</span></label>
                            <input type="text" id="substance" name="substance" class="form-control form-control-sm" required placeholder="e.g., Aspirin, IV Contrast, Penicillin">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold" for="category">Category</label>
                                <select id="category" name="category" class="form-select form-select-sm">
                                    <option value="medication">Medication</option>
                                    <option value="contrast_dye">Contrast Dye</option>
                                    <option value="food">Food</option>
                                    <option value="latex">Latex</option>
                                    <option value="environment">Environment</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold" for="severity">Severity</label>
                                <select id="severity" name="severity" class="form-select form-select-sm">
                                    <option value="mild">Mild</option>
                                    <option value="moderate" selected>Moderate</option>
                                    <option value="severe">Severe</option>
                                    <option value="life_threatening">Life-Threatening</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="reaction">Clinical Reaction</label>
                            <input type="text" id="reaction" name="reaction" class="form-control form-control-sm" placeholder="e.g., Bronchospasm, anaphylaxis, rash">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-sm btn-danger fw-semibold">Add Allergy Alert</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endif

{{-- Break-Glass Emergency Clinical Access Modal --}}
@if($canBreakGlass)
    <div class="modal fade" id="breakGlassModal" tabindex="-1" aria-labelledby="breakGlassModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('patients.break-glass', $patient) }}" class="modal-content">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="breakGlassModalLabel"><i class="bi bi-shield-exclamation me-2"></i>Emergency Break-Glass Access</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Break-glass grants temporary ({{ config('hmis.break_glass_ttl_hours', 4) }} hour) clinical access to <strong>{{ $patient->full_name }} ({{ $patient->mrn }})</strong>.
                        This event is permanently audited and visible to the Medical Superintendent &amp; IT Security.
                    </p>
                    <div class="mb-3">
                        <label for="reason_code" class="form-label fw-semibold">Emergency Reason Category</label>
                        <select id="reason_code" name="reason_code" class="form-select" required>
                            <option value="emergency_cardiac_event">Acute Cardiac Emergency / Code STEMI</option>
                            <option value="unconscious_patient">Unconscious / Unresponsive Patient</option>
                            <option value="urgent_consult_handoff">Urgent Consult / Transfer Handoff</option>
                            <option value="on_call_coverage">On-Call Clinical Coverage</option>
                            <option value="clinical_safety_verification">Medication / Allergy Safety Verification</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="justification" class="form-label fw-semibold">Clinical Justification (Mandatory, min 12 chars)</label>
                        <textarea id="justification" name="justification" rows="3" class="form-control" required minlength="12" placeholder="State the clinical reason requiring emergency access..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-semibold">Confirm Break-Glass &amp; Log Access</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
