@extends('layouts.app')

@section('title', 'Nursing Station & Bedside MAR')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-clipboard2-heart-fill me-2 text-danger"></i>Nursing Station — Bedside MAR &amp; Fluid I/O Charts</h1>
        <small class="text-muted">Five-Rights Medication Administration Record (MAR), shift handovers, and cardiac intake/output monitoring</small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-4">
    {{-- Forms Column --}}
    @can('record', \App\Modules\Nursing\Models\NursingMarRecord::class)
        <div class="col-xl-5">
            <div class="hmis-card p-3 mb-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-capsule me-1 text-primary"></i>Record Bedside MAR Dose</h2>
                <form method="POST" action="{{ route('nursing.mar.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="mar_encounter_id">Active Patient Encounter <span class="text-danger">*</span></label>
                        <select id="mar_encounter_id" name="encounter_id" class="form-select form-select-sm" required>
                            <option value="">Select active patient...</option>
                            @foreach($activeEncounters as $enc)
                                <option value="{{ $enc->id }}">
                                    {{ $enc->patient->mrn }} — {{ $enc->patient->full_name }} ({{ $enc->class }} / {{ $enc->department->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-7">
                            <label class="form-label small fw-semibold" for="medication_name">Medication</label>
                            <input type="text" id="medication_name" name="medication_name" class="form-control form-control-sm" required placeholder="e.g., Heparin IV / Bisoprolol">
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-semibold" for="dose_given">Dose</label>
                            <input type="text" id="dose_given" name="dose_given" class="form-control form-control-sm" required placeholder="5000 IU / 5 mg">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="route">Route</label>
                            <select id="route" name="route" class="form-select form-select-sm">
                                <option value="PO">PO (Oral)</option>
                                <option value="IV">IV</option>
                                <option value="SL">SL</option>
                                <option value="SC">SC</option>
                                <option value="IM">IM</option>
                                <option value="Nebulized">Nebulized</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="status">Administration Status</label>
                            <select id="status" name="status" class="form-select form-select-sm">
                                <option value="administered">Administered</option>
                                <option value="held">Held (Reason Required)</option>
                                <option value="refused">Patient Refused (Reason Required)</option>
                                <option value="missed">Missed</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="clinical_reason">Clinical Reason (Mandatory if Held/Refused)</label>
                        <input type="text" id="clinical_reason" name="clinical_reason" class="form-control form-control-sm" placeholder="e.g., Held beta-blocker due to HR 52 bpm">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-sm btn-primary fw-semibold">Record MAR Entry</button>
                    </div>
                </form>
            </div>

            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-droplet-half me-1 text-info"></i>Nursing Shift Note &amp; Fluid Balance (I/O)</h2>
                <form method="POST" action="{{ route('nursing.notes.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="note_encounter_id">Active Patient Encounter <span class="text-danger">*</span></label>
                        <select id="note_encounter_id" name="encounter_id" class="form-select form-select-sm" required>
                            <option value="">Select active patient...</option>
                            @foreach($activeEncounters as $enc)
                                <option value="{{ $enc->id }}">{{ $enc->patient->mrn }} — {{ $enc->patient->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="shift">Shift</label>
                            <select id="shift" name="shift" class="form-select form-select-sm">
                                <option value="morning">Morning</option>
                                <option value="evening">Evening</option>
                                <option value="night">Night</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="note_category">Category</label>
                            <select id="note_category" name="note_category" class="form-select form-select-sm">
                                <option value="assessment">Bedside Assessment</option>
                                <option value="fluid_balance">Fluid Balance (I/O)</option>
                                <option value="cardiac_monitoring">Telemetry / Cardiac Monitor</option>
                                <option value="shift_handover">Shift Handover</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="intake_ml">Fluid Intake (mL)</label>
                            <input type="number" min="0" id="intake_ml" name="intake_ml" class="form-control form-control-sm" placeholder="e.g. 1200">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="output_ml">Urine / Drain Output (mL)</label>
                            <input type="number" min="0" id="output_ml" name="output_ml" class="form-control form-control-sm" placeholder="e.g. 1450">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="content">Nursing Observation / Handover <span class="text-danger">*</span></label>
                        <textarea id="content" name="content" rows="2" class="form-control form-control-sm" required minlength="5" placeholder="Patient hemodynamically stable on telemetry, NSR..."></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-sm btn-dark fw-semibold">Save Nursing Note</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    {{-- Live MAR & Nursing Feed --}}
    <div class="col-xl-7">
        <div class="hmis-card p-3 mb-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-2">Recent MAR Administrations ({{ $recentMar->count() }})</h2>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small align-middle">
                    <thead class="table-light">
                        <tr><th>Patient</th><th>Medication / Dose</th><th>Status</th><th>Nurse &amp; Time (PKT)</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recentMar as $mar)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $mar->patient->full_name }}</div>
                                    <span class="text-muted hmis-mrn-badge">{{ $mar->patient->mrn }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $mar->medication_name }} ({{ $mar->dose_given }} {{ $mar->route }})</div>
                                    @if($mar->clinical_reason)<div class="text-danger">{{ $mar->clinical_reason }}</div>@endif
                                </td>
                                <td>
                                    <span class="badge {{ $mar->status === 'administered' ? 'bg-success' : 'bg-warning text-dark' }} text-uppercase">{{ $mar->status }}</span>
                                </td>
                                <td>
                                    <div>{{ $mar->administeredBy?->name }}</div>
                                    <span class="text-muted">{{ \App\Support\DateTimeHelper::formatDateTime($mar->administered_at, 'd M, H:i') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No MAR entries recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-2">Nursing Notes &amp; Fluid Balance ({{ $recentNotes->count() }})</h2>
            @forelse($recentNotes as $note)
                @php($netFluid = $note->netFluidBalanceMl())
                <div class="border rounded p-2 mb-2 small bg-light">
                    <div class="d-flex justify-content-between mb-1">
                        <div>
                            <strong>{{ $note->patient->full_name }}</strong>
                            <span class="badge bg-dark text-uppercase ms-1">{{ $note->shift }}</span>
                            <span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $note->note_category) }}</span>
                        </div>
                        <span class="text-muted">{{ \App\Support\DateTimeHelper::formatDateTime($note->recorded_at, 'd M, H:i') }}</span>
                    </div>
                    <div>{{ $note->content }}</div>
                    @if($netFluid !== null)
                        <div class="mt-1">
                            <span class="badge bg-info text-dark">Intake: {{ $note->intake_ml ?? 0 }} mL</span>
                            <span class="badge bg-info text-dark">Output: {{ $note->output_ml ?? 0 }} mL</span>
                            <span class="badge {{ $netFluid > 500 ? 'bg-warning text-dark' : 'bg-success' }}">Net: {{ $netFluid >= 0 ? '+'.$netFluid : $netFluid }} mL</span>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-muted small py-2">No nursing notes documented yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
