@extends('layouts.app')

@section('title', 'Cath Lab & Cardiac Surgery OT')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-heart-fill me-2 text-danger"></i>Catheterization Laboratory (PCI) &amp; Cardiac Surgery OT</h1>
        <small class="text-muted">WHO Safety Checklist mandatory before procedure completion &amp; Consultant sign-off; structured stent/graft &amp; contrast documentation</small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-8">
        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Scheduled &amp; Completed Cardiac Procedures ({{ $procedures->count() }})</h2>
            @forelse($procedures as $proc)
                <div class="border rounded p-3 mb-3 {{ $proc->status === 'completed' ? 'bg-light' : '' }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <span class="font-monospace fw-bold">{{ $proc->procedure_number }}</span>
                            <span class="badge bg-dark text-uppercase ms-1">{{ str_replace('_', ' ', $proc->category) }}</span>
                            <span class="badge {{ $proc->status === 'completed' ? 'bg-success' : 'bg-info text-dark' }} text-uppercase ms-1">{{ $proc->status }}</span>
                            @if($proc->who_checklist_completed)
                                <span class="badge bg-success-subtle text-success ms-1"><i class="bi bi-check2-all"></i> WHO Checklist</span>
                            @endif
                        </div>
                        <small class="text-muted">{{ $proc->theatre_or_lab }} &middot; {{ \App\Support\DateTimeHelper::formatDateTime($proc->scheduled_at) }}</small>
                    </div>

                    <div class="fw-semibold mb-1">{{ $proc->procedure_name }}</div>
                    <div class="small text-muted mb-2">
                        Patient: <a href="{{ route('patients.show', $proc->patient) }}" class="fw-semibold text-decoration-none">{{ $proc->patient->full_name }} ({{ $proc->patient->mrn }})</a>
                        &middot; Lead: {{ $proc->leadOperator?->user?->name ?? 'Unassigned' }}
                        @if($proc->access_site) &middot; Access: {{ str_replace('_', ' ', $proc->access_site) }} @endif
                        @if($proc->contrast_volume_ml !== null) &middot; Contrast: {{ $proc->contrast_volume_ml }} mL @endif
                        @if($proc->fluoroscopy_minutes !== null) &middot; Fluoro: {{ $proc->fluoroscopy_minutes }} min @endif
                    </div>

                    @if($proc->operative_findings)
                        <div class="small mb-1"><strong>Findings:</strong> {{ $proc->operative_findings }}</div>
                    @endif
                    @if(!empty($proc->stents_implanted))
                        <div class="small mb-1"><strong>Stents:</strong> {{ implode('; ', $proc->stents_implanted) }}</div>
                    @endif
                    @if(!empty($proc->grafts_performed))
                        <div class="small mb-1"><strong>CABG Grafts:</strong> {{ implode('; ', $proc->grafts_performed) }}</div>
                    @endif

                    @if($proc->signed_at)
                        <div class="small text-success border-top pt-2 mt-2">
                            <i class="bi bi-patch-check-fill me-1"></i>Signed by <strong>{{ $proc->signedBy?->name }}</strong> at {{ \App\Support\DateTimeHelper::formatDateTime($proc->signed_at) }}
                        </div>
                    @elseif($proc->status !== 'completed')
                        @can('sign', \App\Modules\Cardiology\Models\CardiacProcedure::class)
                            <div class="text-end mt-2">
                                <button type="button" class="btn btn-sm btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#completeProcModal-{{ $proc->id }}">
                                    Complete &amp; Sign Operative Record
                                </button>
                            </div>

                            <div class="modal fade" id="completeProcModal-{{ $proc->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <form method="POST" action="{{ route('cardiology.procedures.complete', $proc) }}" class="modal-content">
                                        @csrf
                                        <div class="modal-header bg-dark text-white">
                                            <h5 class="modal-title">Sign Operative / Cath Lab Record — {{ $proc->procedure_number }}</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="form-check p-3 mb-3 rounded bg-warning-subtle border border-warning">
                                                <input class="form-check-input ms-0 me-2" type="checkbox" name="who_checklist_completed" value="1" id="who-{{ $proc->id }}" required>
                                                <label class="form-check-label fw-bold small" for="who-{{ $proc->id }}">
                                                    Confirm WHO Surgical / Cath Lab Safety Checklist Completed (Time-Out, Allergies, Anticoagulation, Equipment Count)
                                                </label>
                                            </div>
                                            <div class="row g-2 mb-2">
                                                <div class="col-4">
                                                    <label class="form-label small fw-semibold">Contrast (mL)</label>
                                                    <input type="number" min="0" name="contrast_volume_ml" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label small fw-semibold">Fluoroscopy (min)</label>
                                                    <input type="number" step="0.1" min="0" name="fluoroscopy_minutes" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label small fw-semibold">Access Site</label>
                                                    <select name="access_site" class="form-select form-select-sm">
                                                        <option value="radial_right">Right Radial</option>
                                                        <option value="radial_left">Left Radial</option>
                                                        <option value="femoral">Femoral</option>
                                                        <option value="sternotomy">Median Sternotomy</option>
                                                        <option value="thoracotomy">Thoracotomy</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small fw-semibold">Stents Implanted (1 per line: Vessel — Type — Size — Lot#)</label>
                                                <textarea name="stent_summary" rows="2" class="form-control form-control-sm" placeholder="Proximal LAD — DES 3.0x24mm — Lot#ST2026"></textarea>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small fw-semibold">CABG Grafts / Valve Implants (Optional)</label>
                                                <textarea name="graft_summary" rows="2" class="form-control form-control-sm" placeholder="LIMA to LAD; SVG to OM1"></textarea>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small fw-semibold">Operative / Angiographic Findings <span class="text-danger">*</span></label>
                                                <textarea name="operative_findings" rows="3" class="form-control form-control-sm" required minlength="10"></textarea>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small fw-semibold">Post-Op / CCU Orders</label>
                                                <input type="text" name="post_op_orders" class="form-control form-control-sm" placeholder="TR band removal protocol, DAPT, CCU telemetry x24h">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success btn-sm fw-semibold">Sign &amp; Lock Procedure</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcan
                    @endif
                </div>
            @empty
                <div class="text-center text-muted py-4">No Cath Lab or Cardiac Surgery procedures scheduled yet.</div>
            @endforelse
        </div>
    </div>

    @can('schedule', \App\Modules\Cardiology\Models\CardiacProcedure::class)
        <div class="col-xl-4">
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Schedule Cath Lab / Cardiac OT</h2>
                <form method="POST" action="{{ route('cardiology.procedures.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="encounter_id">Active Patient Encounter <span class="text-danger">*</span></label>
                        <select id="encounter_id" name="encounter_id" class="form-select form-select-sm" required>
                            @foreach($activeEncounters as $enc)
                                <option value="{{ $enc->id }}">{{ $enc->patient->mrn }} — {{ $enc->patient->full_name }} ({{ $enc->encounter_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="category">Procedure Category</label>
                        <select id="category" name="category" class="form-select form-select-sm" required>
                            <option value="cath_lab_pci">Cath Lab — Primary / Elective PCI &amp; Stenting</option>
                            <option value="coronary_angiography">Cath Lab — Coronary Angiography</option>
                            <option value="cardiac_surgery">Cardiac Surgery OT (CABG / Valve)</option>
                            <option value="pacemaker_icd">Pacemaker / ICD Implantation</option>
                            <option value="electrophysiology">Electrophysiology Study / Ablation</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="procedure_name">Procedure Name <span class="text-danger">*</span></label>
                        <input type="text" id="procedure_name" name="procedure_name" class="form-control form-control-sm" required placeholder="e.g., Primary PCI to Proximal LAD">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="theatre_or_lab">Lab / OT Suite</label>
                            <input type="text" id="theatre_or_lab" name="theatre_or_lab" value="Cath Lab 1" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="lead_operator_staff_id">Lead Operator</label>
                            <select id="lead_operator_staff_id" name="lead_operator_staff_id" class="form-select form-select-sm">
                                @foreach($operators as $op)
                                    <option value="{{ $op->id }}">{{ $op->user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-sm btn-danger fw-semibold btn-touch">Schedule Cardiac Procedure</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
@endsection
