@extends('layouts.app')

@section('title', 'Rapid 60s Consult — '.$patient->mrn)

@section('content')
{{-- Compact High-Contrast Patient + Vitals Header --}}
<div class="hmis-patient-banner p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge bg-warning text-dark fw-bold"><i class="bi bi-lightning-charge-fill me-1"></i>RAPID 60s CONSULT</span>
                <span class="badge bg-light text-dark hmis-mrn-badge">{{ $patient->mrn }}</span>
                <span class="badge bg-info text-dark font-monospace">{{ $encounter->encounter_number }}</span>
                @if($latestVital)
                    <span class="badge {{ $latestVital->is_abnormal ? 'bg-danger' : 'bg-success' }}">
                        BP {{ $latestVital->systolic_bp ?? '—' }}/{{ $latestVital->diastolic_bp ?? '—' }} &middot; HR {{ $latestVital->heart_rate ?? '—' }} &middot; SpO2 {{ $latestVital->spo2_percent ?? '—' }}%
                    </span>
                @else
                    <span class="badge bg-secondary">Vitals Not Recorded Yet</span>
                @endif
            </div>
            <h1 class="h5 fw-bold mb-0">
                {{ $patient->full_name }}
                <span class="fw-normal small text-white-50 ms-2">{{ $patient->ageDisplay() }} / {{ ucfirst($patient->sex) }} &middot; {{ $encounter->department->name }}</span>
            </h1>
        </div>

        <div class="d-flex flex-wrap gap-2 no-print">
            @if($copyForward)
                <button type="button" id="btn-copy-forward" class="btn btn-sm btn-warning fw-semibold">
                    <i class="bi bi-arrow-repeat me-1"></i>Repeat Last Visit ({{ $copyForward['encounter_date'] }})
                </button>
            @endif
            @if($encounter->prescriptions->isNotEmpty() || $encounter->clinicalNotes->isNotEmpty())
                <a href="{{ route('encounters.prescription.print', $encounter) }}" class="btn btn-sm btn-light fw-semibold">
                    <i class="bi bi-printer-fill me-1"></i>Print Bilingual Rx
                </a>
            @endif
            <a href="{{ route('encounters.show', $encounter) }}" class="btn btn-sm btn-outline-light">Full Chart</a>
            @if($nextWaiting)
                @can('consult', $nextWaiting)
                    <form method="POST" action="{{ route('opd.appointments.start-consult', $nextWaiting) }}" class="d-inline" id="next-patient-form">
                        @csrf
                        <input type="hidden" name="mode" value="rapid">
                        <button type="submit" class="btn btn-sm btn-success fw-semibold" title="Shortcut: Alt+N">
                            Next Token {{ $nextWaiting->formattedToken() }} <i class="bi bi-arrow-right-circle ms-1"></i>
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>
</div>

{{-- Persistent Critical Allergy Banner --}}
@if($patient->activeAllergies->isNotEmpty())
    <div class="hmis-allergy-strip rounded p-2 px-3 mb-3 small">
        <strong><i class="bi bi-exclamation-octagon-fill me-1"></i>ACTIVE ALLERGIES:</strong>
        @foreach($patient->activeAllergies as $allergy)
            <span class="badge bg-danger ms-1">{{ $allergy->substance }} ({{ strtoupper($allergy->category) }} / {{ strtoupper($allergy->severity) }})</span>
        @endforeach
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger small mb-3">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- 1-Click Cardiology Protocol Bundles & Doctor Favorites --}}
<div class="hmis-card p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="small fw-bold text-uppercase text-muted">
            <i class="bi bi-stars text-warning me-1"></i>1-Click Cardiology Protocols &amp; Favorites (Pre-fills Dx, Exam, Plan, Multi-Drug Rx &amp; Labs)
        </div>
        <small class="text-muted">Dot-phrases in notes: <code>.cvs</code> <code>.dapt</code> <code>.htn</code> <code>.hf</code> <code>.fu2w</code> <code>.erwarn</code></small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @foreach($orderSets as $index => $set)
            <button type="button"
                    class="btn btn-sm {{ $set['is_personal'] ? 'btn-outline-success' : 'btn-outline-primary' }} fw-semibold rapid-bundle-btn"
                    data-bundle-index="{{ $index }}"
                    data-bundle-code="{{ $set['code'] ?? '' }}"
                    title="{{ $set['description'] }}">
                @if($set['is_personal'])<i class="bi bi-bookmark-star-fill me-1"></i>@endif
                {{ $set['name'] }}
            </button>
        @endforeach
    </div>
</div>

{{-- Phase 3: Governance-Gated Offline Voice / Shorthand Dictation -> Structured [DRAFT] --}}
<div class="hmis-card p-3 mb-3 {{ config('hmis.ai.enabled') ? 'border-info' : '' }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small fw-bold">
            <i class="bi bi-mic-fill text-primary me-1"></i>Offline Voice / Shorthand Dictation &rarr; Structured [DRAFT]
            @if(config('hmis.ai.enabled'))
                <span class="badge bg-info text-dark ms-1">DRAFT ASSISTANT ACTIVE (HUMAN REVIEW REQUIRED)</span>
            @else
                <span class="badge bg-secondary ms-1">OFF BY POLICY (HMIS_AI_ENABLED=false)</span>
            @endif
        </div>
        @if(config('hmis.ai.enabled'))
            <div class="d-flex gap-2">
                <button type="button" id="btn-voice-mic" class="btn btn-sm btn-outline-danger py-0 px-2">
                    <i class="bi bi-mic me-1"></i>Speak (Local Mic)
                </button>
                <button type="button" id="btn-parse-dictation" class="btn btn-sm btn-primary py-0 px-2">
                    <i class="bi bi-magic me-1"></i>Parse into [DRAFT] Fields
                </button>
            </div>
        @endif
    </div>
    @if(config('hmis.ai.enabled'))
        <div class="mt-2">
            <textarea id="dictation-transcript" rows="2" class="form-control form-control-sm" placeholder="Speak or dictate shorthand (e.g., Known HTN DM, exertional chest pain NYHA II, BP 154/92, HR 86, SpO2 97, S1+S2+0 chest clear, NSR, advise DAPT and follow up in 4 weeks)..."></textarea>
            <div id="dictation-status" class="small mt-1 d-none"></div>
        </div>
    @endif
</div>

<form method="POST" action="{{ route('encounters.rapid.store', $encounter) }}" id="rapid-consult-form">
    @csrf

    <div class="row g-3">
        {{-- Left Column (6 cols): Clinical Assessment, Tappable Chips & Investigations --}}
        <div class="col-lg-6">
            {{-- Inline Vitals (collapsed if nurse already entered vitals) --}}
            <div class="hmis-card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="h6 fw-bold mb-0"><i class="bi bi-activity text-danger me-1"></i>Vitals Snapshot</h2>
                    <button class="btn btn-link btn-sm p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#inlineVitalsBox">
                        {{ $latestVital ? 'Update Vitals' : 'Enter Vitals' }}
                    </button>
                </div>
                <div class="collapse {{ $latestVital ? '' : 'show' }} mt-2" id="inlineVitalsBox">
                    <div class="row g-2">
                        <div class="col-4 col-md-2"><input type="number" name="systolic_bp" value="{{ old('systolic_bp') }}" class="form-control form-control-sm" placeholder="Sys BP"></div>
                        <div class="col-4 col-md-2"><input type="number" name="diastolic_bp" value="{{ old('diastolic_bp') }}" class="form-control form-control-sm" placeholder="Dia BP"></div>
                        <div class="col-4 col-md-2"><input type="number" name="heart_rate" value="{{ old('heart_rate') }}" class="form-control form-control-sm" placeholder="HR"></div>
                        <div class="col-4 col-md-2"><input type="number" name="spo2_percent" value="{{ old('spo2_percent') }}" class="form-control form-control-sm" placeholder="SpO2%"></div>
                        <div class="col-4 col-md-2"><input type="number" step="0.1" name="weight_kg" value="{{ old('weight_kg') }}" class="form-control form-control-sm" placeholder="Wt kg"></div>
                        <div class="col-4 col-md-2"><input type="text" name="rhythm_note" value="{{ old('rhythm_note') }}" class="form-control form-control-sm" placeholder="Rhythm"></div>
                    </div>
                </div>
            </div>

            {{-- Fast Structured Cardiology Note with Tappable Chips --}}
            <div class="hmis-card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                    <h2 class="h6 fw-bold mb-0"><i class="bi bi-clipboard2-pulse text-primary me-1"></i>Clinical Assessment (Tap Chips — Minimal Typing)</h2>
                    <select name="note_type" id="rapid_note_type" class="form-select form-select-sm w-auto">
                        <option value="cardiology_eval">Cardiology Eval</option>
                        <option value="follow_up">Follow-Up</option>
                        <option value="opd_consult">OPD Consult</option>
                    </select>
                </div>

                {{-- Symptom Quick Chips (Per-Doctor Customizable) --}}
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">Chief Complaint <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small toggle-add-chip" data-box="add-chip-symptom">+ Add My Chip</button>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mb-1" id="chips-symptom"></div>
                    <div class="input-group input-group-sm mb-1 d-none" id="add-chip-symptom">
                        <input type="text" class="form-control add-chip-input" data-category="symptom" placeholder="Add your own symptom chip (saved for your account)...">
                        <button type="button" class="btn btn-primary btn-save-chip" data-category="symptom">Save</button>
                    </div>
                    <input type="text" id="rapid_cc" name="chief_complaint" value="{{ old('chief_complaint', $encounter->chief_complaint ?: 'Routine cardiac follow-up') }}" class="form-control form-control-sm" required>
                </div>

                {{-- NYHA & Chest Pain Instant Toggle Pills --}}
                <div class="row g-2 mb-2">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold mb-1">NYHA Class</label>
                        <div class="btn-group btn-group-sm w-100" role="group">
                            @foreach(['I', 'II', 'III', 'IV'] as $nyha)
                                <input type="radio" class="btn-check" name="nyha_class" id="nyha_{{ $nyha }}" value="{{ $nyha }}" @checked(old('nyha_class') === $nyha)>
                                <label class="btn btn-outline-primary" for="nyha_{{ $nyha }}">{{ $nyha }}</label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold mb-1">Chest Pain Profile</label>
                        <select name="chest_pain_type" id="rapid_cp_type" class="form-select form-select-sm">
                            <option value="">Not Assessed</option>
                            <option value="none">No Chest Pain</option>
                            <option value="typical_angina">Typical Angina</option>
                            <option value="atypical_angina">Atypical Angina</option>
                            <option value="non_cardiac">Non-Cardiac</option>
                            <option value="acute_acs">Suspected Acute ACS</option>
                        </select>
                    </div>
                </div>

                {{-- 1-Tap Exam Presets + Per-Doctor Exception Chips --}}
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">Cardiovascular &amp; Chest Exam</label>
                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small toggle-add-chip" data-box="add-chip-exam">+ Add Exam Chip</button>
                            <button type="button" class="btn btn-xs btn-success py-0 px-2 small" id="btn-normal-cvs">
                                <i class="bi bi-check2-all me-1"></i>1-Tap Normal CVS
                            </button>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mb-1" id="chips-exam"></div>
                    <div class="input-group input-group-sm mb-1 d-none" id="add-chip-exam">
                        <input type="text" class="form-control add-chip-input" data-category="exam" placeholder="Add your own exam finding chip...">
                        <button type="button" class="btn btn-primary btn-save-chip" data-category="exam">Save</button>
                    </div>
                    <textarea id="rapid_exam" name="physical_examination" rows="2" class="form-control form-control-sm macro-enabled" placeholder="Type .cvs or tap Normal CVS above...">{{ old('physical_examination') }}</textarea>
                </div>

                {{-- ECG Quick Pills (Per-Doctor Customizable) --}}
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">ECG / Echo Summary</label>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small toggle-add-chip" data-box="add-chip-ecg">+ Add ECG Chip</button>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mb-1" id="chips-ecg"></div>
                    <div class="input-group input-group-sm mb-1 d-none" id="add-chip-ecg">
                        <input type="text" class="form-control add-chip-input" data-category="ecg" placeholder="Add your own ECG/Echo chip...">
                        <button type="button" class="btn btn-primary btn-save-chip" data-category="ecg">Save</button>
                    </div>
                    <input type="text" id="rapid_ecg" name="ecg_summary" value="{{ old('ecg_summary') }}" class="form-control form-control-sm">
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Assessment / Impression</label>
                        <textarea id="rapid_assessment" name="assessment_and_diagnosis" rows="2" class="form-control form-control-sm macro-enabled">{{ old('assessment_and_diagnosis') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Plan &amp; Advice</label>
                        <textarea id="rapid_plan" name="treatment_plan" rows="2" class="form-control form-control-sm macro-enabled">{{ old('treatment_plan') }}</textarea>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Past Medical History (Auto-carried on Repeat)</label>
                        <input type="text" id="rapid_pmh" name="past_medical_history" value="{{ old('past_medical_history') }}" class="form-control form-control-sm" placeholder="HTN, DM, Prior PCI/CABG...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Follow-Up</label>
                        <input type="text" id="rapid_followup" name="follow_up_instructions" value="{{ old('follow_up_instructions', 'Cardiology OPD in 4 weeks') }}" class="form-control form-control-sm macro-enabled">
                    </div>
                </div>
            </div>

            {{-- Diagnosis Shortcuts + Live Offline ICD-10 / ICD-11 Search --}}
            <div class="hmis-card p-3">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                    <h2 class="h6 fw-bold mb-0"><i class="bi bi-tags-fill text-primary me-1"></i>Diagnosis Shortcuts &amp; ICD-10 / ICD-11</h2>
                    <small class="text-muted">Click shortcut to toggle &middot; &starf; pins your own</small>
                </div>

                {{-- 1-Click Personal Diagnosis Shortcuts --}}
                <div class="d-flex flex-wrap gap-1 mb-2" id="chips-diagnosis_shortcut"></div>

                {{-- Instant Offline Diagnosis Filter --}}
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="search" id="dx-filter-input" class="form-control" placeholder="Filter ICD-10 / ICD-11 by code, name or alias (e.g. stemi, htn, afib, I50)...">
                </div>

                <div class="row g-1" id="dx-checkbox-list" style="max-height: 155px; overflow-y: auto;">
                    @foreach($terminologyCodes as $tc)
                        @php
                            $dxSearchBlob = strtolower(trim($tc->code.' '.$tc->display_name.' '.implode(' ', (array) ($tc->aliases ?? []))));
                        @endphp
                        <div class="col-md-6 dx-list-item" data-search="{{ $dxSearchBlob }}">
                            <div class="d-flex align-items-center justify-content-between gap-1 small">
                                <div class="form-check mb-0 text-truncate">
                                    <input class="form-check-input rapid-dx-check" type="checkbox" name="diagnosis_ids[]" value="{{ $tc->id }}" id="dx_{{ $tc->id }}">
                                    <label class="form-check-label" for="dx_{{ $tc->id }}" title="{{ $tc->display_name }}">
                                        <span class="badge bg-dark font-monospace">{{ $tc->code }}</span> {{ \Illuminate\Support\Str::limit($tc->display_name, 28) }}
                                    </label>
                                </div>
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 text-warning text-decoration-none btn-pin-dx"
                                        data-term-id="{{ $tc->id }}"
                                        data-label="{{ $tc->code }} — {{ \Illuminate\Support\Str::limit($tc->display_name, 22) }}"
                                        title="Pin to my Diagnosis Shortcuts">
                                    <i class="bi bi-star"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Right Column (6 cols): Multi-Drug Prescription Builder + Medicine Filter + Custom Medicines --}}
        <div class="col-lg-6">
            {{-- Multi-Drug Prescription Builder --}}
            <div class="hmis-card p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-2">
                    <h2 class="h6 fw-bold mb-0"><i class="bi bi-capsule-pill text-primary me-1"></i>Multi-Drug Prescription (Single Consolidated Rx)</h2>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" data-bs-toggle="collapse" data-bs-target="#customMedBox">
                            <i class="bi bi-plus-circle me-1"></i>Add My Medicine
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="btn-add-med-row">
                            <i class="bi bi-plus-lg"></i> Empty Row
                        </button>
                    </div>
                </div>

                {{-- Doctor's 1-Tap Quick Favorite Medicines --}}
                <div class="mb-2">
                    <div class="small fw-semibold text-muted mb-1">My Quick Medicines (1-Tap to Prescribe):</div>
                    <div class="d-flex flex-wrap gap-1" id="favorite-meds-bar"></div>
                </div>

                {{-- Instant Offline Medicine Search / Filter --}}
                <div class="mb-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="search" id="med-filter-input" class="form-control" placeholder="Filter any medicine by generic, brand, strength, or class (e.g. loprin, concor, statin)...">
                    </div>
                    <div id="med-filter-results" class="border rounded bg-light p-2 mt-1 d-none" style="max-height: 150px; overflow-y: auto;"></div>
                </div>

                {{-- Inline Add Doctor's Own Custom Medicine Panel --}}
                <div class="collapse mb-2" id="customMedBox">
                    <div class="p-2 border border-success rounded bg-success bg-opacity-10 small">
                        <div class="fw-bold text-success mb-2"><i class="bi bi-capsule me-1"></i>Add Your Own Medicine (Saved for Your Account &amp; Pinned)</div>
                        <div class="row g-1 mb-1">
                            <div class="col-md-4"><input type="text" id="cm_generic" class="form-control form-control-sm" placeholder="Generic name * (e.g. Rosuvastatin)"></div>
                            <div class="col-md-4"><input type="text" id="cm_brand" class="form-control form-control-sm" placeholder="Brand (e.g. Rovista)"></div>
                            <div class="col-md-4"><input type="text" id="cm_strength" class="form-control form-control-sm" placeholder="Strength * (e.g. 20 mg)"></div>
                        </div>
                        <div class="row g-1 mb-2">
                            <div class="col-md-3">
                                <select id="cm_form" class="form-select form-select-sm">
                                    <option value="tablet">Tablet</option>
                                    <option value="capsule">Capsule</option>
                                    <option value="syrup">Syrup</option>
                                    <option value="sublingual">Sublingual</option>
                                    <option value="injection">Injection</option>
                                    <option value="sachet">Sachet</option>
                                    <option value="inhaler">Inhaler</option>
                                </select>
                            </div>
                            <div class="col-md-3"><input type="text" id="cm_class" class="form-control form-control-sm" placeholder="Class (e.g. Statin)"></div>
                            <div class="col-md-2">
                                <select id="cm_freq" class="form-select form-select-sm">
                                    <option value="OD">OD</option>
                                    <option value="BD">BD</option>
                                    <option value="TDS">TDS</option>
                                    <option value="HS">HS</option>
                                    <option value="PRN">PRN</option>
                                </select>
                            </div>
                            <div class="col-md-2"><input type="number" id="cm_days" value="30" min="1" class="form-control form-control-sm" placeholder="Days"></div>
                            <div class="col-md-2 d-grid"><button type="button" id="btn-save-custom-med" class="btn btn-sm btn-success fw-semibold">Save &amp; Add</button></div>
                        </div>
                        <div id="cm_feedback" class="small d-none"></div>
                    </div>
                </div>

                @if(session('allergy_conflict'))
                    <div class="alert alert-danger py-2 px-3 small mb-2">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Active Drug Allergy Conflict Detected</div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirm_allergy_override" value="1" id="rapid_override">
                            <label class="form-check-label fw-semibold" for="rapid_override">Override Active Drug Allergy (Audited)</label>
                        </div>
                        <input type="text" name="allergy_override_reason" value="{{ old('allergy_override_reason') }}" class="form-control form-control-sm mt-1" placeholder="Mandatory clinical reason for override">
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small" id="rapid-rx-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30%;">Medication</th>
                                <th style="width: 14%;">Dose</th>
                                <th style="width: 13%;">Freq</th>
                                <th style="width: 11%;">Days</th>
                                <th style="width: 11%;">Qty</th>
                                <th style="width: 17%;">Meal</th>
                                <th style="width: 4%;"></th>
                            </tr>
                        </thead>
                        <tbody id="rapid-rx-tbody"></tbody>
                    </table>
                </div>
                <div class="text-muted small mt-2" id="rapid-rx-empty">Tap any Protocol, pick from <strong>My Quick Medicines</strong>, or type above to filter any medicine.</div>
            </div>

            {{-- Rapid Lab & Diagnostic Imaging Orders --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="hmis-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                            <h2 class="h6 fw-bold mb-0"><i class="bi bi-eyedropper text-primary me-1"></i>Labs</h2>
                            <select name="lab_priority" class="form-select form-select-sm w-auto py-0">
                                <option value="routine">Routine</option>
                                <option value="urgent">Urgent</option>
                                <option value="stat">STAT</option>
                            </select>
                        </div>
                        <div style="max-height: 155px; overflow-y: auto;">
                            @foreach($labTests as $test)
                                <div class="form-check small">
                                    <input class="form-check-input rapid-lab-check" type="checkbox" name="lab_test_ids[]" value="{{ $test->id }}" id="rlab_{{ $test->id }}">
                                    <label class="form-check-label" for="rlab_{{ $test->id }}"><strong>{{ $test->code }}</strong> {{ $test->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="hmis-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                            <h2 class="h6 fw-bold mb-0"><i class="bi bi-image text-primary me-1"></i>ECG / Echo / RIS</h2>
                            <select name="imaging_priority" class="form-select form-select-sm w-auto py-0">
                                <option value="routine">Routine</option>
                                <option value="urgent">Urgent</option>
                                <option value="stat">STAT</option>
                            </select>
                        </div>
                        <div style="max-height: 155px; overflow-y: auto;">
                            @foreach($imagingCatalog as $img)
                                <div class="form-check small">
                                    <input class="form-check-input rapid-img-check" type="checkbox" name="imaging_catalog_ids[]" value="{{ $img->id }}" id="rimg_{{ $img->id }}">
                                    <label class="form-check-label" for="rimg_{{ $img->id }}">
                                        [{{ $img->modality }}] {{ $img->name }}
                                        @if($img->requires_contrast)<span class="badge bg-warning text-dark">Contrast</span>@endif
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Single Atomic Commit Action Bar --}}
            <div class="hmis-card p-3 border-primary bg-primary bg-opacity-10">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 small">
                    @can('signNote', $encounter)
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="sign_now" value="1" id="rapid_sign_now" checked>
                            <label class="form-check-label fw-bold" for="rapid_sign_now">Digitally Sign &amp; Lock Note Immediately</label>
                        </div>
                    @else
                        <span class="badge bg-info text-dark">MO / Scribe Draft Mode — Queued for 1-Click Consultant Co-Sign</span>
                    @endcan

                    @can('complete', $encounter)
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="complete_encounter" value="1" id="rapid_complete" checked>
                            <label class="form-check-label fw-semibold" for="rapid_complete">Complete OPD Visit</label>
                        </div>
                    @endcan
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="post_action" value="print_rx" class="btn btn-primary fw-bold flex-grow-1 btn-touch">
                        <i class="bi bi-check2-circle me-1"></i>
                        @can('signNote', $encounter)
                            Sign, Issue Rx &amp; Print Bilingual Slip (Ctrl+Enter)
                        @else
                            Save Draft &amp; Queue for Consultant Sign-Off (Ctrl+Enter)
                        @endcan
                    </button>
                    <button type="submit" name="post_action" value="workspace" class="btn btn-outline-dark btn-touch">
                        Save &amp; Stay
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const orderSets = @json($orderSets);
    const copyForward = @json($copyForward);
    let clinicianChips = @json($clinicianChips);
    let favoriteMeds = @json($favoriteMedications);
    const medications = @json($medicationsJson);

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const prefEndpoints = {
        storeChip: @json(route('emr.preferences.chips.store')),
        destroyChip: @json(route('emr.preferences.chips.destroy')),
        storeMed: @json(route('emr.preferences.medications.store')),
        pinMed: @json(route('emr.preferences.medications.pin')),
        unpinMedBase: @json(url('/emr/preferences/medications')),
    };

    const macros = {
        '.cvs': 'S1+S2 audible, no murmur, JVP not raised, chest clear bilaterally, no pedal edema.',
        '.dapt': 'Counseled strictly not to stop Aspirin/Clopidogrel without cardiologist approval.',
        '.htn': 'Low-salt DASH diet (<5g/day), daily home BP log, avoid NSAIDs.',
        '.hf': 'Fluid restriction 1.5 L/day, low sodium diet, daily morning weight chart.',
        '.fu2w': 'Follow up in Cardiology OPD in 2 weeks with RFT, Serum K+, and 12-lead ECG.',
        '.fu4w': 'Follow up in Cardiology OPD in 4 weeks with Fasting Lipid Profile and ECG.',
        '.erwarn': 'Report immediately to Cardiac Emergency if chest pain >15 min, severe dyspnea, or syncope.'
    };

    const tbody = document.getElementById('rapid-rx-tbody');
    const emptyMsg = document.getElementById('rapid-rx-empty');
    let rowCounter = 0;

    function freqMultiplier(freq) {
        return { OD: 1, HS: 1, BD: 2, TDS: 3, QID: 4, STAT: 1, PRN: 0.5 }[freq] || 1;
    }

    function addMedicationRow(preset = {}) {
        const idx = rowCounter++;
        const tr = document.createElement('tr');
        const options = ['<option value="">Select...</option>']
            .concat(medications.map(m => `<option value="${m.id}" ${Number(preset.medication_id) === m.id ? 'selected' : ''}>${m.label}</option>`))
            .join('');

        const freq = preset.frequency || 'OD';
        const days = preset.duration_days || 14;
        const qty = preset.quantity_prescribed || Math.max(1, Math.round(days * freqMultiplier(freq)));
        const meal = preset.meal_timing || 'after_meal';

        tr.innerHTML = `
            <td>
                <select name="medications[${idx}][medication_id]" class="form-select form-select-sm">${options}</select>
                <input type="hidden" name="medications[${idx}][route]" value="${preset.route || 'PO'}">
                <input type="hidden" name="medications[${idx}][instructions]" value="${(preset.instructions || '').replace(/"/g, '&quot;')}">
            </td>
            <td><input type="text" name="medications[${idx}][dose]" value="${preset.dose || '1 tab'}" class="form-control form-control-sm"></td>
            <td>
                <select name="medications[${idx}][frequency]" class="form-select form-select-sm rx-freq">
                    ${['OD','BD','TDS','QID','HS','PRN','STAT'].map(f => `<option value="${f}" ${freq === f ? 'selected' : ''}>${f}</option>`).join('')}
                </select>
            </td>
            <td><input type="number" min="1" name="medications[${idx}][duration_days]" value="${days}" class="form-control form-control-sm rx-days"></td>
            <td><input type="number" min="1" name="medications[${idx}][quantity_prescribed]" value="${qty}" class="form-control form-control-sm rx-qty"></td>
            <td>
                <select name="medications[${idx}][meal_timing]" class="form-select form-select-sm">
                    <option value="after_meal" ${meal === 'after_meal' ? 'selected' : ''}>After Meal</option>
                    <option value="before_meal" ${meal === 'before_meal' ? 'selected' : ''}>Before Meal</option>
                    <option value="with_meal" ${meal === 'with_meal' ? 'selected' : ''}>With Meal</option>
                    <option value="empty_stomach" ${meal === 'empty_stomach' ? 'selected' : ''}>Empty Stomach</option>
                    <option value="anytime" ${meal === 'anytime' ? 'selected' : ''}>Anytime</option>
                </select>
            </td>
            <td><button type="button" class="btn btn-sm btn-link text-danger p-0 rx-remove" title="Remove">&times;</button></td>
        `;

        const freqEl = tr.querySelector('.rx-freq');
        const daysEl = tr.querySelector('.rx-days');
        const qtyEl = tr.querySelector('.rx-qty');
        function recalc() {
            const d = Math.max(1, parseInt(daysEl.value || '1', 10));
            qtyEl.value = Math.max(1, Math.round(d * freqMultiplier(freqEl.value)));
        }
        freqEl.addEventListener('change', recalc);
        daysEl.addEventListener('change', recalc);
        tr.querySelector('.rx-remove').addEventListener('click', () => {
            tr.remove();
            emptyMsg.style.display = tbody.children.length ? 'none' : 'block';
        });

        tbody.appendChild(tr);
        emptyMsg.style.display = 'none';
    }

    document.getElementById('btn-add-med-row').addEventListener('click', () => addMedicationRow());

    document.getElementById('btn-normal-cvs').addEventListener('click', () => {
        document.getElementById('rapid_exam').value = macros['.cvs'];
    });

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    }

    function appendChipText(targetId, text) {
        const target = document.getElementById(targetId);
        if (!target) return;
        const current = target.value.trim();
        target.value = current ? `${current}; ${text}` : text;
    }

    async function apiJson(url, method, body = null) {
        const resp = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: body ? JSON.stringify(body) : null
        });
        return { ok: resp.ok, data: await resp.json() };
    }

    // 1. Render Per-Doctor Editable Assessment Chips (Symptoms, Exam, ECG)
    const chipConfig = {
        symptom: { container: 'chips-symptom', target: 'rapid_cc', btnClass: 'btn-outline-secondary' },
        exam: { container: 'chips-exam', target: 'rapid_exam', btnClass: 'btn-outline-warning text-dark' },
        ecg: { container: 'chips-ecg', target: 'rapid_ecg', btnClass: 'btn-outline-secondary' },
    };

    function renderAllChips() {
        for (const [category, cfg] of Object.entries(chipConfig)) {
            const box = document.getElementById(cfg.container);
            if (!box) continue;
            box.innerHTML = '';
            (clinicianChips[category] || []).forEach(chip => {
                const grp = document.createElement('div');
                grp.className = 'btn-group btn-group-sm';
                grp.innerHTML = `
                    <button type="button" class="btn btn-xs ${cfg.btnClass} py-0 px-2 small">${chip.is_custom ? '&#9733; ' : '+ '}${escapeHtml(chip.label)}</button>
                    <button type="button" class="btn btn-xs ${cfg.btnClass} py-0 px-1 small" title="Remove chip from my list">&times;</button>
                `;
                grp.children[0].addEventListener('click', () => appendChipText(cfg.target, chip.value));
                grp.children[1].addEventListener('click', async () => {
                    const res = await apiJson(prefEndpoints.destroyChip, 'DELETE', { category, label: chip.label });
                    if (res.ok && res.data.chips) {
                        clinicianChips = res.data.chips;
                        renderAllChips();
                    }
                });
                box.appendChild(grp);
            });
        }

        // Render 1-Click Personal Diagnosis Shortcuts
        const dxBox = document.getElementById('chips-diagnosis_shortcut');
        if (dxBox) {
            dxBox.innerHTML = '';
            (clinicianChips.diagnosis_shortcut || []).forEach(dx => {
                const cb = document.getElementById('dx_' + dx.terminology_code_id);
                const isChecked = cb ? cb.checked : false;
                const grp = document.createElement('div');
                grp.className = 'btn-group btn-group-sm';
                const activeCls = isChecked ? 'btn-primary' : 'btn-outline-primary';
                grp.innerHTML = `
                    <button type="button" class="btn btn-xs ${activeCls} py-0 px-2 small fw-semibold">${escapeHtml(dx.label)}</button>
                    <button type="button" class="btn btn-xs ${activeCls} py-0 px-1 small" title="Remove diagnosis shortcut">&times;</button>
                `;
                grp.children[0].addEventListener('click', () => {
                    if (cb) {
                        cb.checked = !cb.checked;
                        renderAllChips();
                    }
                });
                grp.children[1].addEventListener('click', async () => {
                    const res = await apiJson(prefEndpoints.destroyChip, 'DELETE', {
                        category: 'diagnosis_shortcut',
                        label: dx.label,
                        terminology_code_id: dx.terminology_code_id
                    });
                    if (res.ok && res.data.chips) {
                        clinicianChips = res.data.chips;
                        renderAllChips();
                    }
                });
                dxBox.appendChild(grp);
            });
        }
    }

    document.querySelectorAll('.toggle-add-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            const box = document.getElementById(btn.dataset.box);
            if (!box) return;
            box.classList.toggle('d-none');
            const inp = box.querySelector('input');
            if (inp && !box.classList.contains('d-none')) inp.focus();
        });
    });

    async function saveNewChip(category) {
        const input = document.querySelector(`.add-chip-input[data-category="${category}"]`);
        if (!input) return;
        const label = input.value.trim();
        if (!label) return;
        const res = await apiJson(prefEndpoints.storeChip, 'POST', { category, label, value: label });
        if (res.ok && res.data.chips) {
            clinicianChips = res.data.chips;
            input.value = '';
            renderAllChips();
        }
    }

    document.querySelectorAll('.btn-save-chip').forEach(btn => {
        btn.addEventListener('click', () => saveNewChip(btn.dataset.category));
    });
    document.querySelectorAll('.add-chip-input').forEach(inp => {
        inp.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveNewChip(inp.dataset.category);
            }
        });
    });

    // 2. Live Offline Diagnosis Filter + Pin Diagnosis Shortcut
    const dxFilter = document.getElementById('dx-filter-input');
    if (dxFilter) {
        dxFilter.addEventListener('input', () => {
            const q = dxFilter.value.trim().toLowerCase();
            document.querySelectorAll('.dx-list-item').forEach(row => {
                row.style.display = (!q || (row.dataset.search || '').includes(q)) ? '' : 'none';
            });
        });
    }
    document.querySelectorAll('.rapid-dx-check').forEach(cb => cb.addEventListener('change', renderAllChips));
    document.querySelectorAll('.btn-pin-dx').forEach(btn => {
        btn.addEventListener('click', async () => {
            const res = await apiJson(prefEndpoints.storeChip, 'POST', {
                category: 'diagnosis_shortcut',
                terminology_code_id: Number(btn.dataset.termId),
                label: btn.dataset.label
            });
            if (res.ok && res.data.chips) {
                clinicianChips = res.data.chips;
                renderAllChips();
            }
        });
    });

    // 3. Per-Doctor Favorite Quick Medicines Bar + Instant Medicine Filter + Add Own Medicine
    function renderFavoriteMeds() {
        const bar = document.getElementById('favorite-meds-bar');
        if (!bar) return;
        bar.innerHTML = '';
        if (!favoriteMeds.length) {
            bar.innerHTML = '<span class="small text-muted">Star (&starf;) any medicine below or click <strong>+ Add My Medicine</strong> to pin 1-tap favorites.</span>';
            return;
        }
        favoriteMeds.forEach(fav => {
            const grp = document.createElement('div');
            grp.className = 'btn-group btn-group-sm';
            grp.innerHTML = `
                <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 small fw-semibold">+ ${escapeHtml(fav.label)} (${escapeHtml(fav.frequency)})</button>
                <button type="button" class="btn btn-xs btn-outline-success py-0 px-1 small" title="Unpin from my favorites">&times;</button>
            `;
            grp.children[0].addEventListener('click', () => addMedicationRow(fav));
            grp.children[1].addEventListener('click', async () => {
                const res = await apiJson(`${prefEndpoints.unpinMedBase}/${fav.medication_id}/pin`, 'DELETE');
                if (res.ok && res.data.favorites) {
                    favoriteMeds = res.data.favorites;
                    renderFavoriteMeds();
                }
            });
            bar.appendChild(grp);
        });
    }

    const medFilterInput = document.getElementById('med-filter-input');
    const medFilterResults = document.getElementById('med-filter-results');
    function renderMedFilterResults() {
        if (!medFilterInput || !medFilterResults) return;
        const q = medFilterInput.value.trim().toLowerCase();
        if (!q) {
            medFilterResults.classList.add('d-none');
            medFilterResults.innerHTML = '';
            return;
        }
        const matches = medications.filter(m => m.search_text.includes(q)).slice(0, 12);
        medFilterResults.classList.remove('d-none');
        if (!matches.length) {
            medFilterResults.innerHTML = `<div class="small text-muted">No medicine matching "<strong>${escapeHtml(q)}</strong>". Click <strong>+ Add My Medicine</strong> above to add it now.</div>`;
            return;
        }
        medFilterResults.innerHTML = '';
        matches.forEach(m => {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center py-1 border-bottom small';
            row.innerHTML = `
                <div>
                    <strong>${escapeHtml(m.label)}</strong>
                    <span class="text-muted ms-1">${escapeHtml(m.drug_class || '')}</span>
                    ${m.is_hospital_formulary ? '' : '<span class="badge bg-success-subtle text-success ms-1">My Med</span>'}
                </div>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-xs btn-primary py-0 px-2 small">+ Rx</button>
                    <button type="button" class="btn btn-xs btn-outline-warning text-dark py-0 px-2 small" title="Pin to My Quick Medicines">&#9733;</button>
                </div>
            `;
            row.querySelector('.btn-primary').addEventListener('click', () => {
                addMedicationRow({ medication_id: m.id, dose: '1 tab', frequency: 'OD', duration_days: 14, meal_timing: 'after_meal' });
                medFilterInput.value = '';
                renderMedFilterResults();
            });
            row.querySelector('.btn-outline-warning').addEventListener('click', async () => {
                const res = await apiJson(prefEndpoints.pinMed, 'POST', { medication_id: m.id });
                if (res.ok && res.data.favorites) {
                    favoriteMeds = res.data.favorites;
                    renderFavoriteMeds();
                }
            });
            medFilterResults.appendChild(row);
        });
    }
    if (medFilterInput) medFilterInput.addEventListener('input', renderMedFilterResults);

    const saveCustomMedBtn = document.getElementById('btn-save-custom-med');
    if (saveCustomMedBtn) {
        saveCustomMedBtn.addEventListener('click', async () => {
            const fb = document.getElementById('cm_feedback');
            const payload = {
                generic_name: document.getElementById('cm_generic').value.trim(),
                brand_name: document.getElementById('cm_brand').value.trim(),
                strength: document.getElementById('cm_strength').value.trim(),
                dosage_form: document.getElementById('cm_form').value,
                drug_class: document.getElementById('cm_class').value.trim(),
                default_frequency: document.getElementById('cm_freq').value,
                default_duration_days: Number(document.getElementById('cm_days').value || 30),
            };
            if (!payload.generic_name || !payload.strength) {
                fb.className = 'small text-danger';
                fb.textContent = 'Generic name and strength are required.';
                return;
            }
            const res = await apiJson(prefEndpoints.storeMed, 'POST', payload);
            if (!res.ok || !res.data.medication) {
                fb.className = 'small text-danger';
                fb.textContent = res.data.message || 'Could not save medicine.';
                return;
            }
            medications.unshift(res.data.medication);
            favoriteMeds = res.data.favorites || favoriteMeds;
            renderFavoriteMeds();
            addMedicationRow({
                medication_id: res.data.medication.id,
                dose: '1 tab',
                frequency: payload.default_frequency,
                duration_days: payload.default_duration_days,
                meal_timing: 'after_meal',
            });
            document.getElementById('cm_generic').value = '';
            document.getElementById('cm_brand').value = '';
            document.getElementById('cm_strength').value = '';
            document.getElementById('cm_class').value = '';
            fb.className = 'small text-success fw-semibold';
            fb.textContent = `Saved "${res.data.medication.label}" to your personal quick medicines & added to Rx!`;
        });
    }

    renderAllChips();
    renderFavoriteMeds();

    // Apply 1-Click Cardiology Protocol Bundle
    document.querySelectorAll('.rapid-bundle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const set = orderSets[Number(btn.dataset.bundleIndex)];
            if (!set) return;

            if (set.default_note_type) document.getElementById('rapid_note_type').value = set.default_note_type;
            if (set.default_nyha_class) {
                const r = document.getElementById('nyha_' + set.default_nyha_class);
                if (r) r.checked = true;
            }
            if (set.default_chest_pain_type) document.getElementById('rapid_cp_type').value = set.default_chest_pain_type;
            if (set.default_exam_text) document.getElementById('rapid_exam').value = set.default_exam_text;
            if (set.default_assessment_text) document.getElementById('rapid_assessment').value = set.default_assessment_text;
            if (set.default_plan_text) document.getElementById('rapid_plan').value = set.default_plan_text;
            if (set.default_follow_up) document.getElementById('rapid_followup').value = set.default_follow_up;

            document.querySelectorAll('.rapid-dx-check').forEach(cb => cb.checked = (set.diagnosis_ids || []).includes(Number(cb.value)));
            renderAllChips();
            document.querySelectorAll('.rapid-lab-check').forEach(cb => cb.checked = (set.lab_test_ids || []).includes(Number(cb.value)));
            document.querySelectorAll('.rapid-img-check').forEach(cb => cb.checked = (set.imaging_catalog_ids || []).includes(Number(cb.value)));

            tbody.innerHTML = '';
            (set.medications || []).forEach(m => addMedicationRow(m));
        });
    });

    // 1-Click Repeat Last Visit (Copy-Forward)
    const copyBtn = document.getElementById('btn-copy-forward');
    if (copyBtn && copyForward) {
        copyBtn.addEventListener('click', () => {
            if (copyForward.past_medical_history) document.getElementById('rapid_pmh').value = copyForward.past_medical_history;
            if (copyForward.nyha_class) {
                const r = document.getElementById('nyha_' + copyForward.nyha_class);
                if (r) r.checked = true;
            }
            if (copyForward.chest_pain_type) document.getElementById('rapid_cp_type').value = copyForward.chest_pain_type;
            if (copyForward.assessment_and_diagnosis) document.getElementById('rapid_assessment').value = copyForward.assessment_and_diagnosis;
            if (copyForward.treatment_plan) document.getElementById('rapid_plan').value = copyForward.treatment_plan;
            if (copyForward.follow_up_instructions) document.getElementById('rapid_followup').value = copyForward.follow_up_instructions;
            document.querySelectorAll('.rapid-dx-check').forEach(cb => cb.checked = (copyForward.diagnosis_ids || []).includes(Number(cb.value)));
            renderAllChips();

            tbody.innerHTML = '';
            (copyForward.medications || []).forEach(m => addMedicationRow(m));
        });
    }

    // Offline Dot-Phrase Macro Expander
    document.querySelectorAll('.macro-enabled').forEach(el => {
        el.addEventListener('keyup', (e) => {
            if (e.key !== ' ' && e.key !== 'Enter') return;
            for (const [trigger, expansion] of Object.entries(macros)) {
                if (el.value.includes(trigger)) {
                    el.value = el.value.replace(trigger, expansion);
                }
            }
        });
    });

    // Phase 3: Governance-Gated Voice / Dictation -> Structured [DRAFT] (never auto-submits!)
    const micBtn = document.getElementById('btn-voice-mic');
    const parseBtn = document.getElementById('btn-parse-dictation');
    const transcriptEl = document.getElementById('dictation-transcript');
    const statusEl = document.getElementById('dictation-status');

    if (micBtn && transcriptEl) {
        const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRec) {
            micBtn.disabled = true;
            micBtn.title = 'Browser SpeechRecognition unavailable — type/paste transcript or configure LAN Whisper';
        } else {
            const rec = new SpeechRec();
            rec.lang = 'en-US';
            rec.interimResults = false;
            rec.onresult = (event) => {
                const spoken = event.results[0][0].transcript;
                transcriptEl.value = (transcriptEl.value ? transcriptEl.value + ' ' : '') + spoken;
            };
            micBtn.addEventListener('click', () => rec.start());
        }
    }

    if (parseBtn && transcriptEl) {
        parseBtn.addEventListener('click', async () => {
            const text = transcriptEl.value.trim();
            if (text.length < 5) return;
            statusEl.className = 'small mt-1 text-muted';
            statusEl.textContent = 'Parsing offline dictation into [DRAFT] fields...';
            try {
                const resp = await fetch(@json(route('encounters.rapid.dictate-draft', $encounter)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ transcript: text })
                });
                const data = await resp.json();
                if (!resp.ok || !data.allowed || !data.fields) {
                    statusEl.className = 'small mt-1 text-danger fw-semibold';
                    statusEl.textContent = data.message || 'Dictation parsing blocked by governance policy.';
                    return;
                }
                const f = data.fields;
                if (f.chief_complaint) document.getElementById('rapid_cc').value = f.chief_complaint;
                if (f.past_medical_history) document.getElementById('rapid_pmh').value = f.past_medical_history;
                if (f.physical_examination) document.getElementById('rapid_exam').value = f.physical_examination;
                if (f.ecg_summary) document.getElementById('rapid_ecg').value = f.ecg_summary;
                if (f.assessment_and_diagnosis) document.getElementById('rapid_assessment').value = f.assessment_and_diagnosis;
                if (f.follow_up_instructions) document.getElementById('rapid_followup').value = f.follow_up_instructions;
                if (f.nyha_class) {
                    const r = document.getElementById('nyha_' + f.nyha_class);
                    if (r) r.checked = true;
                }
                if (f.chest_pain_type) document.getElementById('rapid_cp_type').value = f.chest_pain_type;
                if (f.systolic_bp) document.querySelector('input[name="systolic_bp"]').value = f.systolic_bp;
                if (f.diastolic_bp) document.querySelector('input[name="diastolic_bp"]').value = f.diastolic_bp;
                if (f.heart_rate) document.querySelector('input[name="heart_rate"]').value = f.heart_rate;
                if (f.spo2_percent) document.querySelector('input[name="spo2_percent"]').value = f.spo2_percent;

                let hint = data.message;
                if (f.suggested_order_set_code) {
                    const matchBtn = document.querySelector(`.rapid-bundle-btn[data-bundle-code="${f.suggested_order_set_code}"]`);
                    if (matchBtn) {
                        matchBtn.classList.add('btn-warning', 'text-dark');
                        hint += ` Suggested protocol highlighted: ${matchBtn.textContent.trim()} (click manually if clinically appropriate).`;
                    }
                }
                statusEl.className = 'small mt-1 alert alert-warning py-1 px-2 mb-0';
                statusEl.textContent = hint;
            } catch (err) {
                statusEl.className = 'small mt-1 text-danger';
                statusEl.textContent = 'Offline dictation parser error.';
            }
        });
    }

    // Keyboard shortcuts: Ctrl+Enter -> Submit & Print, Alt+N -> Next waiting patient
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('rapid-consult-form').requestSubmit();
        } else if (e.altKey && e.key.toLowerCase() === 'n') {
            const nextForm = document.getElementById('next-patient-form');
            if (nextForm) {
                e.preventDefault();
                nextForm.submit();
            }
        }
    });
})();
</script>
@endsection
