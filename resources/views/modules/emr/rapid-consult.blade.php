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
                    title="{{ $set['description'] }}">
                @if($set['is_personal'])<i class="bi bi-bookmark-star-fill me-1"></i>@endif
                {{ $set['name'] }}
            </button>
        @endforeach
    </div>
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

                {{-- Symptom Quick Chips --}}
                <div class="mb-2">
                    <label class="form-label small fw-semibold mb-1">Chief Complaint <span class="text-danger">*</span></label>
                    <div class="d-flex flex-wrap gap-1 mb-1">
                        @foreach(['Exertional chest pain', 'Shortness of breath', 'Palpitations', 'Orthopnea / PND', 'Pedal edema', 'Dizziness / Syncope', 'Routine cardiac follow-up (asymptomatic)'] as $sym)
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 small chip-append" data-target="rapid_cc" data-text="{{ $sym }}">+ {{ $sym }}</button>
                        @endforeach
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

                {{-- 1-Tap Exam Presets + Exception Chips --}}
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">Cardiovascular &amp; Chest Exam</label>
                        <button type="button" class="btn btn-xs btn-success py-0 px-2 small" id="btn-normal-cvs">
                            <i class="bi bi-check2-all me-1"></i>1-Tap Normal CVS
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mb-1">
                        @foreach(['Bilateral basal crepitations', 'Raised JVP', 'Bilateral pedal edema', 'Irregularly irregular pulse', 'Ejection systolic murmur', 'Pansystolic murmur'] as $ex)
                            <button type="button" class="btn btn-xs btn-outline-warning text-dark py-0 px-2 small chip-append" data-target="rapid_exam" data-text="{{ $ex }}">+ {{ $ex }}</button>
                        @endforeach
                    </div>
                    <textarea id="rapid_exam" name="physical_examination" rows="2" class="form-control form-control-sm macro-enabled" placeholder="Type .cvs or tap Normal CVS above...">{{ old('physical_examination') }}</textarea>
                </div>

                {{-- ECG Quick Pills --}}
                <div class="mb-2">
                    <label class="form-label small fw-semibold mb-1">ECG / Echo Summary</label>
                    <div class="d-flex flex-wrap gap-1 mb-1">
                        @foreach(['NSR, no acute ST-T changes', 'LVH by voltage criteria', 'Atrial Fibrillation', 'Old Anterior MI (Q waves V1-V4)', 'ST depression lateral leads'] as $ecg)
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 small chip-append" data-target="rapid_ecg" data-text="{{ $ecg }}">+ {{ $ecg }}</button>
                        @endforeach
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

            {{-- Coded ICD-10 / ICD-11 Diagnosis Multi-Select --}}
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-2"><i class="bi bi-tags-fill text-primary me-1"></i>Coded ICD-10 / ICD-11 Diagnoses (Check One or More)</h2>
                <div class="row g-1" style="max-height: 150px; overflow-y: auto;">
                    @foreach($terminologyCodes as $tc)
                        <div class="col-md-6">
                            <div class="form-check small">
                                <input class="form-check-input rapid-dx-check" type="checkbox" name="diagnosis_ids[]" value="{{ $tc->id }}" id="dx_{{ $tc->id }}">
                                <label class="form-check-label" for="dx_{{ $tc->id }}">
                                    <span class="badge bg-dark font-monospace">{{ $tc->code }}</span> {{ \Illuminate\Support\Str::limit($tc->display_name, 34) }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Right Column (6 cols): Multi-Drug Prescription Builder + Investigations + Atomic Save --}}
        <div class="col-lg-6">
            {{-- Multi-Drug Prescription Builder --}}
            <div class="hmis-card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                    <h2 class="h6 fw-bold mb-0"><i class="bi bi-capsule-pill text-primary me-1"></i>Multi-Drug Prescription (Single Consolidated Rx)</h2>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="btn-add-med-row">
                        <i class="bi bi-plus-lg"></i> Add Medicine
                    </button>
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
                <div class="text-muted small mt-2" id="rapid-rx-empty">Tap any Cardiology Protocol above or click <strong>+ Add Medicine</strong> to build a multi-drug prescription.</div>
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
    const medications = @json($medications->map(fn ($m) => ['id' => $m->id, 'label' => "{$m->generic_name} {$m->strength} ({$m->brand_name})"])->values());

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

    document.querySelectorAll('.chip-append').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            const current = target.value.trim();
            target.value = current ? `${current}; ${btn.dataset.text}` : btn.dataset.text;
        });
    });

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
