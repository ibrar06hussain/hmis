@extends('layouts.app')

@section('title', 'Encounter '.$encounter->encounter_number.' — '.$patient->mrn)

@section('content')
{{-- Persistent Patient + Encounter Header --}}
<div class="hmis-patient-banner p-3 p-md-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge bg-light text-dark hmis-mrn-badge">{{ $patient->mrn }}</span>
                <span class="badge bg-info text-dark font-monospace">{{ $encounter->encounter_number }}</span>
                <span class="badge bg-warning text-dark text-uppercase">{{ $encounter->class }} &middot; {{ str_replace('_', ' ', $encounter->status) }}</span>
            </div>
            <h1 class="h4 fw-bold mb-1">
                <a href="{{ route('patients.show', $patient) }}" class="text-white text-decoration-none">{{ $patient->full_name }}</a>
            </h1>
            <div class="small text-white-50 d-flex flex-wrap gap-3">
                <span><strong>Age/Sex:</strong> {{ $patient->ageDisplay() }} / {{ ucfirst($patient->sex) }}</span>
                <span><strong>Department:</strong> {{ $encounter->department->name }}</span>
                <span><strong>Attending:</strong> {{ $encounter->attendingStaff?->user?->name ?? 'Unassigned' }}</span>
                <span><strong>Started:</strong> {{ \App\Support\DateTimeHelper::formatDateTime($encounter->started_at) }}</span>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 no-print">
            <a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-outline-light">Unified Patient Profile</a>
            <a href="{{ route('opd.index') }}" class="btn btn-sm btn-outline-light">OPD Queue</a>
            @if($encounter->status !== 'finished')
                @can('complete', $encounter)
                    <form method="POST" action="{{ route('encounters.complete', $encounter) }}" onsubmit="return confirm('Mark this consultation encounter as finished?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success fw-semibold">Complete Consultation</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>
</div>

{{-- Critical Allergy Strip --}}
@if($patient->activeAllergies->isNotEmpty())
    <div class="hmis-allergy-strip rounded p-3 mb-3">
        <strong><i class="bi bi-exclamation-octagon-fill me-2"></i>ALLERGIES:</strong>
        @foreach($patient->activeAllergies as $allergy)
            <span class="badge bg-danger ms-1">{{ $allergy->substance }} ({{ strtoupper($allergy->severity) }})</span>
        @endforeach
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    {{-- Left Column: Vitals Entry & History --}}
    <div class="col-lg-5">
        @can('recordVitals', $encounter)
            <div class="hmis-card p-3 mb-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-activity me-2 text-danger"></i>Record Vital Signs</h2>
                <form method="POST" action="{{ route('encounters.vitals.store', $encounter) }}">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small mb-1" for="systolic_bp">Systolic BP (mmHg)</label>
                            <input type="number" id="systolic_bp" name="systolic_bp" value="{{ old('systolic_bp') }}" class="form-control form-control-sm" placeholder="120">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="diastolic_bp">Diastolic BP (mmHg)</label>
                            <input type="number" id="diastolic_bp" name="diastolic_bp" value="{{ old('diastolic_bp') }}" class="form-control form-control-sm" placeholder="80">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1" for="heart_rate">HR (bpm)</label>
                            <input type="number" id="heart_rate" name="heart_rate" value="{{ old('heart_rate') }}" class="form-control form-control-sm" placeholder="76">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1" for="spo2_percent">SpO2 (%)</label>
                            <input type="number" id="spo2_percent" name="spo2_percent" value="{{ old('spo2_percent') }}" class="form-control form-control-sm" placeholder="98">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1" for="respiratory_rate">RR (/min)</label>
                            <input type="number" id="respiratory_rate" name="respiratory_rate" value="{{ old('respiratory_rate') }}" class="form-control form-control-sm" placeholder="16">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1" for="temperature_c">Temp (&deg;C)</label>
                            <input type="number" step="0.1" id="temperature_c" name="temperature_c" value="{{ old('temperature_c') }}" class="form-control form-control-sm" placeholder="36.8">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1" for="weight_kg">Weight (kg)</label>
                            <input type="number" step="0.1" id="weight_kg" name="weight_kg" value="{{ old('weight_kg') }}" class="form-control form-control-sm" placeholder="72.5">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1" for="height_cm">Height (cm)</label>
                            <input type="number" step="0.1" id="height_cm" name="height_cm" value="{{ old('height_cm') }}" class="form-control form-control-sm" placeholder="170">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="blood_glucose_mg_dl">RBS (mg/dL)</label>
                            <input type="number" id="blood_glucose_mg_dl" name="blood_glucose_mg_dl" value="{{ old('blood_glucose_mg_dl') }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="rhythm_note">Pulse / Rhythm</label>
                            <input type="text" id="rhythm_note" name="rhythm_note" value="{{ old('rhythm_note') }}" class="form-control form-control-sm" placeholder="Regular / AFib">
                        </div>
                    </div>
                    <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-sm btn-danger fw-semibold">Save Vital Signs</button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="hmis-card p-3 mb-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-2">Recorded Vitals ({{ $encounter->vitalSigns->count() }})</h2>
            @forelse($encounter->vitalSigns as $v)
                <div class="p-2 mb-2 rounded border {{ $v->is_abnormal ? 'border-danger bg-danger-subtle' : 'bg-light' }} small">
                    <div class="d-flex justify-content-between fw-semibold">
                        <span>
                            BP: {{ $v->systolic_bp ?? '—' }}/{{ $v->diastolic_bp ?? '—' }} &middot;
                            HR: {{ $v->heart_rate ?? '—' }} &middot;
                            SpO2: {{ $v->spo2_percent !== null ? $v->spo2_percent.'%' : '—' }}
                        </span>
                        <span class="text-muted">{{ \App\Support\DateTimeHelper::formatDateTime($v->recorded_at, 'H:i') }}</span>
                    </div>
                    <div class="text-muted">
                        Temp: {{ $v->temperature_c ?? '—' }}&deg;C &middot;
                        RR: {{ $v->respiratory_rate ?? '—' }} &middot;
                        BMI: {{ $v->bmi ?? '—' }}
                        @if($v->rhythm_note) &middot; {{ $v->rhythm_note }} @endif
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
                <div class="text-muted small py-2">No vital signs recorded in this encounter yet.</div>
            @endforelse
        </div>

        {{-- ICD-10 / ICD-11 Coded Diagnoses (Version-Locked Offline Terminology) --}}
        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-2"><i class="bi bi-tags-fill me-2 text-primary"></i>ICD-10 / ICD-11 Diagnoses ({{ $encounter->diagnoses->count() }})</h2>

            @can('recordDiagnosis', $encounter)
                <form method="POST" action="{{ route('encounters.diagnoses.store', $encounter) }}" class="mb-3 pb-3 border-bottom">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="terminology_code_id">Diagnosis Code (Offline ICD-10 / ICD-11)</label>
                        <select id="terminology_code_id" name="terminology_code_id" class="form-select form-select-sm" required>
                            <option value="">Select coded diagnosis...</option>
                            @foreach($terminologyCodes as $tc)
                                <option value="{{ $tc->id }}">
                                    [{{ $tc->coding_system }} {{ $tc->code }}] {{ $tc->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small mb-1" for="diagnosis_rank">Rank</label>
                            <select id="diagnosis_rank" name="diagnosis_rank" class="form-select form-select-sm" required>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="clinical_status">Status</label>
                            <select id="clinical_status" name="clinical_status" class="form-select form-select-sm" required>
                                <option value="confirmed">Confirmed</option>
                                <option value="provisional">Provisional</option>
                                <option value="active">Active</option>
                                <option value="ruled_out">Ruled Out</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="clinical_context" class="form-control form-control-sm" placeholder="Optional clinical context (e.g., Anterior wall, Killip II)">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-sm btn-primary fw-semibold">Add Coded Diagnosis</button>
                    </div>
                </form>
            @endcan

            @forelse($encounter->diagnoses as $diag)
                <div class="border rounded p-2 mb-2 small bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge {{ $diag->diagnosis_rank === 'primary' ? 'bg-primary' : 'bg-secondary' }} text-uppercase">{{ $diag->diagnosis_rank }}</span>
                            <span class="badge bg-dark font-monospace">{{ $diag->coding_system }} {{ $diag->code }}</span>
                            <span class="badge bg-light text-muted border">v{{ $diag->terminology_version }}</span>
                        </div>
                        <span class="text-uppercase small fw-semibold">{{ $diag->clinical_status }}</span>
                    </div>
                    <div class="fw-semibold mt-1">{{ $diag->display_name }}</div>
                    @if($diag->clinical_context)<div class="text-muted">{{ $diag->clinical_context }}</div>@endif
                </div>
            @empty
                <div class="text-muted small py-1">No coded diagnoses recorded yet.</div>
            @endforelse
        </div>
    </div>

    {{-- Right Column: Structured Cardiology Clinical Note & Immutable Revision History --}}
    <div class="col-lg-7">
        @can('createNote', $encounter)
            <div class="hmis-card p-3 mb-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-medical me-2 text-primary"></i>New Structured Cardiology / OPD Note (Draft)</h2>
                <form method="POST" action="{{ route('encounters.notes.store', $encounter) }}">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold" for="note_type">Template</label>
                            <select id="note_type" name="note_type" class="form-select form-select-sm">
                                <option value="cardiology_eval">Cardiology Evaluation</option>
                                <option value="opd_consult">General OPD Consultation</option>
                                <option value="follow_up">Cardiology Follow-Up</option>
                                <option value="er_triage">ER Triage / Stabilization</option>
                                <option value="nursing_note">Nursing Clinical Note</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold" for="nyha_class">NYHA Class</label>
                            <select id="nyha_class" name="nyha_class" class="form-select form-select-sm">
                                <option value="">Not Applicable</option>
                                <option value="I">Class I (No limitation)</option>
                                <option value="II">Class II (Mild limitation)</option>
                                <option value="III">Class III (Marked limitation)</option>
                                <option value="IV">Class IV (Symptoms at rest)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold" for="chest_pain_type">Chest Pain Profile</label>
                            <select id="chest_pain_type" name="chest_pain_type" class="form-select form-select-sm">
                                <option value="">Not Assessed</option>
                                <option value="none">No Chest Pain</option>
                                <option value="typical_angina">Typical Angina</option>
                                <option value="atypical_angina">Atypical Angina</option>
                                <option value="non_cardiac">Non-Cardiac</option>
                                <option value="acute_acs">Suspected Acute ACS</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="chief_complaint">Chief Complaint <span class="text-danger">*</span></label>
                        <input type="text" id="chief_complaint" name="chief_complaint" value="{{ old('chief_complaint', $encounter->chief_complaint) }}" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="history_of_present_illness">History of Present Illness (HPI)</label>
                        <textarea id="history_of_present_illness" name="history_of_present_illness" rows="2" class="form-control form-control-sm">{{ old('history_of_present_illness') }}</textarea>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="past_medical_history">Past Medical History</label>
                            <textarea id="past_medical_history" name="past_medical_history" rows="2" class="form-control form-control-sm" placeholder="HTN, DM, Prior MI/PCI/CABG...">{{ old('past_medical_history') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="medication_history">Current Medications</label>
                            <textarea id="medication_history" name="medication_history" rows="2" class="form-control form-control-sm" placeholder="Aspirin, Clopidogrel, Statin, Beta-blocker...">{{ old('medication_history') }}</textarea>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="physical_examination">Cardiovascular &amp; Physical Examination</label>
                        <textarea id="physical_examination" name="physical_examination" rows="2" class="form-control form-control-sm" placeholder="S1+S2+0, JVP not raised, chest clear bilaterally...">{{ old('physical_examination') }}</textarea>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="assessment_and_diagnosis">Clinical Assessment / Provisional Diagnosis</label>
                            <textarea id="assessment_and_diagnosis" name="assessment_and_diagnosis" rows="2" class="form-control form-control-sm">{{ old('assessment_and_diagnosis') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="treatment_plan">Treatment Plan &amp; Investigations</label>
                            <textarea id="treatment_plan" name="treatment_plan" rows="2" class="form-control form-control-sm">{{ old('treatment_plan') }}</textarea>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="follow_up_instructions">Follow-Up Instructions</label>
                            <input type="text" id="follow_up_instructions" name="follow_up_instructions" value="{{ old('follow_up_instructions') }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="ecg_summary">ECG / Echo Summary</label>
                            <input type="text" id="ecg_summary" name="ecg_summary" value="{{ old('ecg_summary') }}" class="form-control form-control-sm" placeholder="NSR, no acute ST-T changes">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Draft Clinical Note</button>
                    </div>
                </form>
            </div>
        @endcan

        {{-- Clinical Notes List with Immutability, Sign-Off & Amendment Workflow --}}
        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Encounter Clinical Notes &amp; Revision History ({{ $encounter->clinicalNotes->count() }})</h2>
            @forelse($encounter->clinicalNotes as $note)
                <div class="border rounded p-3 mb-3 {{ $note->status === 'amended' ? 'bg-light opacity-75' : '' }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <span class="badge bg-dark text-uppercase">{{ str_replace('_', ' ', $note->note_type) }}</span>
                            <span class="badge bg-secondary">Rev {{ $note->revision_number }}</span>
                            @if($note->status === 'signed')
                                <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i>SIGNED &amp; LOCKED</span>
                            @elseif($note->status === 'amended')
                                <span class="badge bg-warning text-dark">SUPERSEDED BY AMENDMENT</span>
                            @else
                                <span class="badge bg-info text-dark">DRAFT</span>
                            @endif
                        </div>
                        <small class="text-muted">
                            Author: {{ $note->authorUser?->name }} &middot; {{ \App\Support\DateTimeHelper::formatDateTime($note->created_at) }}
                        </small>
                    </div>

                    @if($note->amendment_reason)
                        <div class="alert alert-warning py-1 px-2 small mb-2">
                            <strong>Amendment Reason (supersedes Rev {{ $note->supersedesNote?->revision_number }}):</strong> {{ $note->amendment_reason }}
                        </div>
                    @endif

                    <dl class="row small mb-2">
                        <dt class="col-sm-3 text-muted">Chief Complaint</dt>
                        <dd class="col-sm-9">{{ $note->chief_complaint }}</dd>

                        @if($note->history_of_present_illness)
                            <dt class="col-sm-3 text-muted">HPI</dt>
                            <dd class="col-sm-9">{{ $note->history_of_present_illness }}</dd>
                        @endif

                        @if($note->physical_examination)
                            <dt class="col-sm-3 text-muted">Examination</dt>
                            <dd class="col-sm-9">{{ $note->physical_examination }}</dd>
                        @endif

                        @if($note->assessment_and_diagnosis)
                            <dt class="col-sm-3 text-muted">Assessment</dt>
                            <dd class="col-sm-9 fw-semibold">{{ $note->assessment_and_diagnosis }}</dd>
                        @endif

                        @if($note->treatment_plan)
                            <dt class="col-sm-3 text-muted">Plan</dt>
                            <dd class="col-sm-9">{{ $note->treatment_plan }}</dd>
                        @endif

                        @if(!empty($note->structured_payload))
                            <dt class="col-sm-3 text-muted">Cardiology Data</dt>
                            <dd class="col-sm-9">
                                @if(!empty($note->structured_payload['nyha_class']))
                                    <span class="badge bg-light text-dark border">NYHA {{ $note->structured_payload['nyha_class'] }}</span>
                                @endif
                                @if(!empty($note->structured_payload['chest_pain_type']))
                                    <span class="badge bg-light text-dark border">{{ str_replace('_', ' ', $note->structured_payload['chest_pain_type']) }}</span>
                                @endif
                                @if(!empty($note->structured_payload['ecg_summary']))
                                    <div class="mt-1"><strong>ECG:</strong> {{ $note->structured_payload['ecg_summary'] }}</div>
                                @endif
                            </dd>
                        @endif
                    </dl>

                    @if($note->signed_at)
                        <div class="small text-success border-top pt-2">
                            <i class="bi bi-patch-check-fill me-1"></i>
                            Digitally signed by <strong>{{ $note->signedBy?->name }}</strong> at {{ \App\Support\DateTimeHelper::formatDateTime($note->signed_at) }}
                        </div>
                    @endif

                    <div class="d-flex justify-content-end gap-2 mt-2 no-print">
                        @if($note->status === 'draft')
                            @can('signNote', $encounter)
                                <form method="POST" action="{{ route('encounters.notes.sign', [$encounter, $note]) }}" onsubmit="return confirm('Sign and lock this clinical note? Once signed, changes require a formal audited amendment.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success fw-semibold">
                                        <i class="bi bi-pen-fill me-1"></i>Sign &amp; Lock Note
                                    </button>
                                </form>
                            @endcan
                        @elseif($note->status === 'signed')
                            @can('amendNote', $encounter)
                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#amendNoteModal-{{ $note->id }}">
                                    <i class="bi bi-file-diff me-1"></i>Create Audited Amendment
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>

                @if($note->status === 'signed')
                    @can('amendNote', $encounter)
                        <div class="modal fade" id="amendNoteModal-{{ $note->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <form method="POST" action="{{ route('encounters.notes.amend', [$encounter, $note]) }}" class="modal-content">
                                    @csrf
                                    <div class="modal-header bg-warning">
                                        <h5 class="modal-title text-dark">Amend Signed Note (Creates Rev {{ $note->revision_number + 1 }})</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="small text-muted">Original Rev {{ $note->revision_number }} remains permanently preserved in the clinical audit trail.</p>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Amendment Reason (Mandatory) <span class="text-danger">*</span></label>
                                            <input type="text" name="amendment_reason" class="form-control form-control-sm" required minlength="10" placeholder="e.g., Added updated Troponin/ECG interpretation">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Chief Complaint</label>
                                            <input type="text" name="chief_complaint" value="{{ $note->chief_complaint }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Assessment &amp; Diagnosis</label>
                                            <textarea name="assessment_and_diagnosis" rows="2" class="form-control form-control-sm">{{ $note->assessment_and_diagnosis }}</textarea>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Treatment Plan</label>
                                            <textarea name="treatment_plan" rows="3" class="form-control form-control-sm">{{ $note->treatment_plan }}</textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning btn-sm fw-semibold">Sign &amp; Save Amendment</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endcan
                @endif
            @empty
                <div class="text-muted small py-2">No clinical notes documented for this encounter yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
