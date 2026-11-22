@extends('layouts.app')

@section('title', 'Laboratory Information System (LIS)')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-eyedropper me-2 text-primary"></i>Laboratory Information System (LIS)</h1>
        <small class="text-muted">Standard test catalog &amp; reporting options, test booking, barcode sample receiving, manual &amp; automatic HL7 v2 machine integration, and 2-step Pathologist verification</small>
    </div>
    @can('create', \App\Modules\Lis\Models\LabOrder::class)
        <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#lisBookTestPanel">
            <i class="bi bi-plus-circle-fill me-1"></i>Book Lab Test / Panel
        </button>
    @endcan
</div>

{{-- LIS Live KPI Strip --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-2">
        <div class="hmis-card p-2 px-3">
            <div class="small text-muted">Booked / Pending</div>
            <div class="fs-4 fw-bold text-warning-emphasis">{{ $counts['booked'] }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="hmis-card p-2 px-3">
            <div class="small text-muted">In Analysis</div>
            <div class="fs-4 fw-bold text-primary">{{ $counts['in_analysis'] }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="hmis-card p-2 px-3">
            <div class="small text-muted">Verified Orders</div>
            <div class="fs-4 fw-bold text-success">{{ $counts['verified_today'] }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="hmis-card p-2 px-3">
            <div class="small text-muted">Catalog Parameters</div>
            <div class="fs-4 fw-bold">{{ $counts['catalog_params'] }} <span class="small fw-normal text-muted">({{ $panels->count() }} panels)</span></div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="hmis-card p-2 px-3">
            <div class="small text-muted">LAN Analyzers Online</div>
            <div class="fs-4 fw-bold text-success">{{ $counts['devices_online'] }} / {{ $devices->count() }}</div>
        </div>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if(session('analyzer_ack'))
    <div class="alert alert-info small font-monospace mb-3">
        <strong>HL7 v2 ACK Response:</strong><br>
        {!! nl2br(e(session('analyzer_ack'))) !!}
    </div>
@endif

{{-- Direct LIS Test Booking Panel (Collapsible) --}}
@can('create', \App\Modules\Lis\Models\LabOrder::class)
    <div class="collapse mb-3" id="lisBookTestPanel">
        <div class="hmis-card p-3 border-primary">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-journal-plus text-primary me-1"></i>Book Laboratory Tests &amp; Standard Panels</h2>
            <form method="POST" action="{{ route('lis.orders.store') }}">
                @csrf
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold mb-1">Patient (MRN) <span class="text-danger">*</span></label>
                        <select name="patient_id" class="form-select form-select-sm" required>
                            <option value="">Select active patient...</option>
                            @foreach($activePatients as $p)
                                <option value="{{ $p->id }}">{{ $p->mrn }} — {{ $p->full_name }} ({{ ucfirst($p->sex) }}, {{ $p->ageDisplay() }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Priority</label>
                        <select name="priority" class="form-select form-select-sm" required>
                            <option value="routine">Routine</option>
                            <option value="urgent">Urgent</option>
                            <option value="stat">STAT (Immediate)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Clinical Notes / Indication</label>
                        <input type="text" name="clinical_notes" class="form-control form-control-sm" placeholder="e.g., Pre-cath screening / ACS workup">
                    </div>
                </div>

                {{-- 1-Click Standard Panel Selectors --}}
                <div class="mb-2">
                    <div class="small fw-semibold text-muted mb-1">1-Click Standard Hospital Panels (Selects all panel parameters):</div>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($panels as $panel)
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary py-0 px-2 lis-panel-toggle"
                                    data-test-ids='@json($panel["test_ids"])'>
                                + {{ $panel['code'] }} ({{ $panel['tests']->count() }})
                            </button>
                        @endforeach
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="lis-clear-booking">Clear All</button>
                    </div>
                </div>

                <div class="row g-2 mb-3" style="max-height: 220px; overflow-y: auto;">
                    @foreach($panels as $panel)
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-light h-100 small">
                                <div class="fw-bold text-primary border-bottom pb-1 mb-1">{{ $panel['name'] }}</div>
                                @foreach($panel['tests'] as $t)
                                    <div class="form-check mb-0">
                                        <input class="form-check-input lis-book-check" type="checkbox" name="test_ids[]" value="{{ $t->id }}" id="book_t_{{ $t->id }}">
                                        <label class="form-check-label" for="book_t_{{ $t->id }}">
                                            <strong>{{ $t->code }}</strong> — {{ $t->name }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i>Book Tests &amp; Generate Barcode Tubes
                    </button>
                </div>
            </form>
        </div>
    </div>
@endcan

{{-- LIS Navigation Tabs --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'orders' ? 'active fw-bold' : '' }}" href="{{ route('lis.index', ['tab' => 'orders', 'status' => $statusFilter]) }}">
            <i class="bi bi-list-check me-1"></i>Tests Booked &amp; Result Entry
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'receiving' ? 'active fw-bold' : '' }}" href="{{ route('lis.index', ['tab' => 'receiving']) }}">
            <i class="bi bi-upc-scan me-1"></i>Sample Receiving Desk
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'machines' ? 'active fw-bold' : '' }}" href="{{ route('lis.index', ['tab' => 'machines']) }}">
            <i class="bi bi-cpu-fill me-1"></i>Automatic Machine Integration (HL7 v2)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'catalog' ? 'active fw-bold' : '' }}" href="{{ route('lis.index', ['tab' => 'catalog']) }}">
            <i class="bi bi-sliders me-1"></i>Test Catalog, Parameters &amp; Reporting Options ({{ $counts['catalog_params'] }})
        </a>
    </li>
</ul>

{{-- ==================== TAB 1: TESTS BOOKED & RESULT ENTRY ==================== --}}
@if($tab === 'orders')
    <div class="hmis-card p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="btn-group btn-group-sm">
                <a href="{{ route('lis.index', ['tab' => 'orders', 'status' => 'open', 'q' => $search]) }}" class="btn {{ $statusFilter === 'open' ? 'btn-primary' : 'btn-outline-secondary' }}">Open Queue</a>
                <a href="{{ route('lis.index', ['tab' => 'orders', 'status' => 'booked', 'q' => $search]) }}" class="btn {{ $statusFilter === 'booked' ? 'btn-warning text-dark' : 'btn-outline-secondary' }}">Booked / Awaiting Sample</a>
                <a href="{{ route('lis.index', ['tab' => 'orders', 'status' => 'received', 'q' => $search]) }}" class="btn {{ $statusFilter === 'received' ? 'btn-info text-dark' : 'btn-outline-secondary' }}">Received / Result Entry</a>
                <a href="{{ route('lis.index', ['tab' => 'orders', 'status' => 'verified', 'q' => $search]) }}" class="btn {{ $statusFilter === 'verified' ? 'btn-success' : 'btn-outline-secondary' }}">Verified Reports</a>
                <a href="{{ route('lis.index', ['tab' => 'orders', 'status' => 'all', 'q' => $search]) }}" class="btn {{ $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' }}">All</a>
            </div>
            <form method="GET" action="{{ route('lis.index') }}" class="d-flex gap-1">
                <input type="hidden" name="tab" value="orders">
                <input type="hidden" name="status" value="{{ $statusFilter }}">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" style="min-width: 220px;" placeholder="Search Order #, Barcode, MRN...">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            </form>
        </div>
    </div>

    @forelse($orders as $order)
        <div class="hmis-card p-3 mb-3 {{ $order->priority === 'stat' ? 'border-danger' : '' }}">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-3">
                <div>
                    <span class="badge {{ $order->priority === 'stat' ? 'bg-danger' : ($order->priority === 'urgent' ? 'bg-warning text-dark' : 'bg-secondary') }} text-uppercase">{{ $order->priority }}</span>
                    <span class="fw-bold font-monospace ms-1">{{ $order->order_number }}</span>
                    <span class="mx-2">&middot;</span>
                    <span class="fw-semibold">{{ $order->patient->full_name }}</span>
                    <span class="badge bg-light text-dark border hmis-mrn-badge ms-1">{{ $order->patient->mrn }}</span>
                    <span class="small text-muted ms-1">({{ ucfirst($order->patient->sex) }}, {{ $order->patient->ageDisplay() }})</span>
                </div>
                <div class="d-flex align-items-center gap-2 small">
                    <span class="text-muted">Booked: {{ \App\Support\DateTimeHelper::formatDateTime($order->ordered_at, 'd M, H:i') }}</span>
                    <span class="badge {{ $order->status === 'verified' ? 'bg-success' : 'bg-info text-dark' }} text-uppercase">{{ str_replace('_', ' ', $order->status) }}</span>
                    <a href="{{ route('lis.orders.report', $order) }}" class="btn btn-sm btn-outline-dark py-0 px-2">
                        <i class="bi bi-printer me-1"></i>Report
                    </a>
                </div>
            </div>

            {{-- Specimens & Sample Receiving Strip --}}
            <div class="mb-3">
                <div class="small fw-semibold text-muted mb-1">Barcode Specimens (Collection, Receiving &amp; Rejection)</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($order->specimens as $specimen)
                        <div class="border rounded p-2 small bg-light d-flex flex-wrap align-items-center gap-2">
                            <span class="font-monospace fw-bold">{{ $specimen->barcode }}</span>
                            <span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $specimen->specimen_type) }}</span>
                            <span class="badge {{ $specimen->status === 'rejected' ? 'bg-danger' : ($specimen->status === 'received_in_lab' ? 'bg-success' : 'bg-warning text-dark') }}">
                                {{ str_replace('_', ' ', $specimen->status) }}
                            </span>

                            @if($specimen->status === 'pending_collection')
                                @can('collectSpecimen', \App\Modules\Lis\Models\LabOrder::class)
                                    <form method="POST" action="{{ route('lis.specimens.collect', $specimen) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-primary py-0 px-2">Collect</button>
                                    </form>
                                @endcan
                            @endif

                            @if(in_array($specimen->status, ['pending_collection', 'collected'], true))
                                @can('enterResult', \App\Modules\Lis\Models\LabOrder::class)
                                    <form method="POST" action="{{ route('lis.specimens.receive', $specimen) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-success py-0 px-2">Receive Sample</button>
                                    </form>
                                @endcan
                            @endif

                            @if($specimen->status !== 'rejected')
                                @can('enterResult', \App\Modules\Lis\Models\LabOrder::class)
                                    <form method="POST" action="{{ route('lis.specimens.reject', $specimen) }}" class="d-inline-flex gap-1 align-items-center">
                                        @csrf
                                        <input type="text" name="rejection_reason" class="form-control form-control-sm py-0" style="width: 130px; font-size: 0.75rem;" placeholder="Reject reason..." required minlength="5">
                                        <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1">Reject</button>
                                    </form>
                                @endcan
                            @elseif($specimen->rejection_reason)
                                <span class="text-danger">({{ $specimen->rejection_reason }})</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Bulk Manual Result Entry + Individual Tech Validate / Pathologist Verify --}}
            @php
                $hasEditableItems = $order->items->contains(fn ($i) => $i->status !== 'verified');
                $hasVerifiableItems = $order->items->contains(fn ($i) => in_array($i->status, ['result_entered', 'tech_validated'], true));
            @endphp

            <form method="POST" action="{{ route('lis.orders.results-bulk', $order) }}" id="bulk-form-{{ $order->id }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2 small">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 24%;">Parameter (Panel / LOINC)</th>
                                <th style="width: 15%;">Reference Range</th>
                                <th style="width: 22%;">Manual / Analyzer Result</th>
                                <th style="width: 15%;">Remarks</th>
                                <th style="width: 10%;">Flags &amp; Source</th>
                                <th style="width: 14%;" class="text-end">Status &amp; Gate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr class="{{ $item->is_critical ? 'table-danger' : ($item->abnormal_flag === 'abnormal' ? 'table-warning' : '') }}">
                                    <td>
                                        <div class="fw-semibold">{{ $item->test->name }}</div>
                                        <span class="text-muted font-monospace">{{ $item->test->code }}</span>
                                        @if($item->test->panel_code)<span class="badge bg-light text-dark border ms-1">{{ $item->test->panel_code }}</span>@endif
                                    </td>
                                    <td class="font-monospace">
                                        {{ $item->test->referenceDisplayForSex($order->patient->sex) }}
                                    </td>
                                    <td>
                                        @if($item->status !== 'verified' && auth()->user()?->can('enterResult', \App\Modules\Lis\Models\LabOrder::class))
                                            @if($item->test->isQualitative() && ! empty($item->test->reporting_options))
                                                <select name="results[{{ $item->id }}][text]" class="form-select form-select-sm">
                                                    <option value="">Select reporting option...</option>
                                                    @foreach($item->test->reporting_options as $opt)
                                                        <option value="{{ $opt }}" @selected($item->result_value_text === $opt)>{{ $opt }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($item->test->isQualitative())
                                                <input type="text" name="results[{{ $item->id }}][text]" value="{{ $item->result_value_text }}" class="form-control form-control-sm" placeholder="Enter qualitative result">
                                            @else
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.001" name="results[{{ $item->id }}][numeric]" value="{{ $item->result_value_numeric }}" class="form-control" placeholder="Value">
                                                    @if($item->unit)<span class="input-group-text">{{ $item->unit }}</span>@endif
                                                </div>
                                            @endif
                                        @else
                                            <span class="fw-bold">{{ $item->result_value_text ?? 'Pending' }} {{ $item->unit }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->status !== 'verified' && auth()->user()?->can('enterResult', \App\Modules\Lis\Models\LabOrder::class))
                                            <input type="text" name="results[{{ $item->id }}][remarks]" value="{{ $item->remarks }}" class="form-control form-control-sm" placeholder="Optional note">
                                        @else
                                            <span class="text-muted">{{ $item->remarks ?: '—' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->is_critical)
                                            <span class="badge bg-danger">CRITICAL</span>
                                        @elseif($item->abnormal_flag && $item->abnormal_flag !== 'normal')
                                            <span class="badge bg-warning text-dark text-uppercase">{{ $item->abnormal_flag }}</span>
                                        @elseif($item->result_value_text !== null)
                                            <span class="badge bg-success-subtle text-success">NORMAL</span>
                                        @endif
                                        @if($item->result_value_text !== null)
                                            <div>
                                                <span class="badge {{ $item->result_source === 'analyzer' ? 'bg-info text-dark' : 'bg-light text-muted border' }}" style="font-size: 0.68rem;">
                                                    {{ strtoupper($item->result_source) }}@if($item->analyzer_device_code): {{ $item->analyzer_device_code }}@endif
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <span class="badge {{ $item->status === 'verified' ? 'bg-success' : ($item->status === 'tech_validated' ? 'bg-primary' : 'bg-secondary') }} text-uppercase mb-1">
                                            {{ str_replace('_', ' ', $item->status) }}
                                        </span>
                                        <div class="d-flex justify-content-end gap-1">
                                            @if($item->status === 'result_entered')
                                                @can('enterResult', \App\Modules\Lis\Models\LabOrder::class)
                                                    <button type="submit" form="tech-val-{{ $item->id }}" class="btn btn-xs btn-warning py-0 px-1">Tech Val</button>
                                                @endcan
                                            @endif
                                            @if(in_array($item->status, ['result_entered', 'tech_validated'], true))
                                                @can('verifyResult', \App\Modules\Lis\Models\LabOrder::class)
                                                    <button type="submit" form="verify-item-{{ $item->id }}" class="btn btn-xs btn-success py-0 px-1">Verify</button>
                                                @endcan
                                            @endif
                                            @if($item->is_critical && $item->critical_acknowledged_at === null)
                                                @can('acknowledgeCritical', \App\Modules\Lis\Models\LabOrder::class)
                                                    <button type="submit" form="ack-crit-{{ $item->id }}" class="btn btn-xs btn-danger py-0 px-1">Ack Crit</button>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            {{-- Individual item hidden action forms (avoid nested forms in HTML) --}}
            @foreach($order->items as $item)
                @if($item->status === 'result_entered')
                    @can('enterResult', \App\Modules\Lis\Models\LabOrder::class)
                        <form id="tech-val-{{ $item->id }}" method="POST" action="{{ route('lis.items.tech-validate', $item) }}" class="d-none">@csrf</form>
                    @endcan
                @endif
                @if(in_array($item->status, ['result_entered', 'tech_validated'], true))
                    @can('verifyResult', \App\Modules\Lis\Models\LabOrder::class)
                        <form id="verify-item-{{ $item->id }}" method="POST" action="{{ route('lis.items.verify', $item) }}" class="d-none">@csrf</form>
                    @endcan
                @endif
                @if($item->is_critical && $item->critical_acknowledged_at === null)
                    @can('acknowledgeCritical', \App\Modules\Lis\Models\LabOrder::class)
                        <form id="ack-crit-{{ $item->id }}" method="POST" action="{{ route('lis.items.acknowledge-critical', $item) }}" class="d-none">@csrf</form>
                    @endcan
                @endif
            @endforeach

            <div class="d-flex flex-wrap justify-content-end gap-2 pt-2 border-top">
                @if($hasEditableItems)
                    @can('enterResult', \App\Modules\Lis\Models\LabOrder::class)
                        <button type="submit" form="bulk-form-{{ $order->id }}" class="btn btn-sm btn-primary fw-semibold">
                            <i class="bi bi-save2 me-1"></i>Save All Manual Results
                        </button>
                    @endcan
                @endif
                @if($hasVerifiableItems)
                    @can('verifyResult', \App\Modules\Lis\Models\LabOrder::class)
                        <form method="POST" action="{{ route('lis.orders.verify-all', $order) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success fw-semibold">
                                <i class="bi bi-patch-check-fill me-1"></i>Verify &amp; Release Entire Order (Pathologist)
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    @empty
        <div class="hmis-card p-4 text-center text-muted">No laboratory orders match this filter. Click <strong>Book Lab Test / Panel</strong> above to create a new order.</div>
    @endforelse

{{-- ==================== TAB 2: SAMPLE RECEIVING DESK ==================== --}}
@elseif($tab === 'receiving')
    <div class="hmis-card p-3 mb-3 border-success">
        <h2 class="h6 fw-bold mb-2"><i class="bi bi-upc-scan text-success me-2"></i>Barcode Scanner Sample Receiving</h2>
        <form method="POST" action="{{ route('lis.specimens.scan-receive') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-6">
                <label for="scan_barcode" class="form-label small fw-semibold mb-1">Scan or Enter Specimen Barcode (e.g., SMP-2026-000001)</label>
                <input type="text" id="scan_barcode" name="barcode" class="form-control font-monospace" required autofocus placeholder="Scan tube barcode & press Enter...">
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-success fw-semibold"><i class="bi bi-check2-all me-1"></i>Receive Sample Now</button>
            </div>
        </form>
    </div>

    <div class="hmis-card p-3">
        <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Specimen Collection &amp; Receiving Queue ({{ $pendingSpecimens->count() }})</h2>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Barcode</th>
                        <th>Order # &amp; Patient</th>
                        <th>Tube / Specimen</th>
                        <th>Status</th>
                        <th>Collected / Received (PKT)</th>
                        <th class="text-end">Sample Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingSpecimens as $sp)
                        <tr>
                            <td class="font-monospace fw-bold">{{ $sp->barcode }}</td>
                            <td>
                                <span class="font-monospace fw-semibold">{{ $sp->labOrder?->order_number }}</span>
                                <div class="text-muted">{{ $sp->labOrder?->patient?->full_name }} ({{ $sp->labOrder?->patient?->mrn }})</div>
                            </td>
                            <td><span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $sp->specimen_type) }}</span></td>
                            <td>
                                <span class="badge {{ $sp->status === 'rejected' ? 'bg-danger' : ($sp->status === 'received_in_lab' ? 'bg-success' : 'bg-warning text-dark') }} text-uppercase">
                                    {{ str_replace('_', ' ', $sp->status) }}
                                </span>
                                @if($sp->rejection_reason)<div class="text-danger">{{ $sp->rejection_reason }}</div>@endif
                            </td>
                            <td class="text-muted">
                                @if($sp->collected_at)Col: {{ \App\Support\DateTimeHelper::formatDateTime($sp->collected_at, 'd M H:i') }}<br>@endif
                                @if($sp->received_at)Rec: {{ \App\Support\DateTimeHelper::formatDateTime($sp->received_at, 'd M H:i') }}@endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($sp->status === 'pending_collection')
                                    <form method="POST" action="{{ route('lis.specimens.collect', $sp) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary py-0 px-2">Collect</button>
                                    </form>
                                @endif
                                @if(in_array($sp->status, ['pending_collection', 'collected'], true))
                                    <form method="POST" action="{{ route('lis.specimens.receive', $sp) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success py-0 px-2">Receive</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No specimens queued yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

{{-- ==================== TAB 3: AUTOMATIC MACHINE INTEGRATION ==================== --}}
@elseif($tab === 'machines')
    <div class="row g-3 mb-3">
        @foreach($devices as $dev)
            <div class="col-md-6 col-xl-3">
                <div class="hmis-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold font-monospace">{{ $dev->device_code }}</span>
                        <span class="badge {{ $dev->connection_status === 'online' ? 'bg-success' : 'bg-secondary' }} text-uppercase">{{ $dev->connection_status }}</span>
                    </div>
                    <div class="small fw-semibold">{{ $dev->manufacturer }} — {{ $dev->model }}</div>
                    <div class="small text-muted font-monospace mt-1">{{ strtoupper($dev->protocol) }} &middot; {{ $dev->host_or_port }}</div>
                    <div class="small text-muted mt-1">Mapped codes: <strong>{{ count((array) $dev->test_code_map) }}</strong></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-2"><i class="bi bi-broadcast text-primary me-1"></i>Automatic Analyzer HL7 v2 ORU^R01 Ingest Console</h2>
                <p class="small text-muted mb-2">
                    External LAN analyzer middleware posts frames directly to <code>POST /api/v1/lis/analyzer/ingest</code> (Sanctum token + SHA-256 duplicate protection). You can also test/push a live HL7 v2 machine frame below — all machine results stage into <strong>Result Entered (ANALYZER)</strong> and <strong>NEVER</strong> auto-verify without Pathologist release.
                </p>

                <form method="POST" action="{{ route('lis.analyzers.ingest-ui') }}">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold mb-1">Analyzer Machine</label>
                            <select name="device_code" id="hl7_device_code" class="form-select form-select-sm" required>
                                @foreach($devices as $dev)
                                    <option value="{{ $dev->device_code }}">{{ $dev->device_code }} — {{ $dev->model }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold mb-1">Received Specimen Barcode (Helper)</label>
                            <div class="input-group input-group-sm">
                                <select id="hl7_specimen_select" class="form-select">
                                    <option value="">Select received tube...</option>
                                    @foreach($receivedSpecimensForMachine as $rs)
                                        <option value="{{ $rs->barcode }}">{{ $rs->barcode }} — {{ $rs->labOrder?->patient?->full_name }} ({{ $rs->specimen_type }})</option>
                                    @endforeach
                                </select>
                                <button type="button" id="btn-build-hl7" class="btn btn-outline-primary">Build HL7 Frame</button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">Raw HL7 v2 ORU^R01 Payload</label>
                        <textarea name="raw_payload" id="hl7_raw_payload" rows="7" class="form-control form-control-sm font-monospace" required placeholder="MSH|^~\&|ARCH-I1000|LIS|CHG-HMIS|CHG|20261122043000||ORU^R01|MSG-1001|P|2.5.1&#10;OBR|1|SMP-2026-000001||CARDIAC&#10;OBX|1|NM|TROP-I^Troponin I||85.4|ng/L"></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                            <i class="bi bi-cpu me-1"></i>Ingest Machine Frame &amp; Auto-Stage Results
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-2"><i class="bi bi-journal-code text-success me-1"></i>Inbound Analyzer Message Log (SHA-256 Deduplicated)</h2>
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time (PKT)</th>
                                <th>Machine</th>
                                <th>Control ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentRawMessages as $msg)
                                <tr>
                                    <td class="text-nowrap">{{ \App\Support\DateTimeHelper::formatDateTime($msg->received_at, 'd M, H:i:s') }}</td>
                                    <td class="font-monospace">{{ $msg->device?->device_code }}</td>
                                    <td class="font-monospace">{{ $msg->control_id ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $msg->parse_status === 'staged' ? 'bg-success' : ($msg->parse_status === 'duplicate' ? 'bg-warning text-dark' : 'bg-danger') }} text-uppercase">
                                            {{ $msg->parse_status }}
                                        </span>
                                        @if($msg->error_message)<div class="text-danger" style="font-size: 0.72rem;">{{ $msg->error_message }}</div>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No machine frames received yet. Receive a specimen and push an HL7 v2 frame on the left.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

{{-- ==================== TAB 4: TEST CATALOG, PARAMETERS & REPORTING OPTIONS ==================== --}}
@elseif($tab === 'catalog')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="hmis-card p-3">
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Standard Hospital Test Catalog, Parameters &amp; Reporting Options ({{ $catalogTests->count() }})</h2>
                @foreach($panels as $panel)
                    <div class="mb-3">
                        <div class="fw-bold small text-primary bg-light border rounded px-2 py-1 mb-2 d-flex justify-content-between">
                            <span>{{ $panel['name'] }} ({{ $panel['code'] }})</span>
                            <span class="text-muted text-uppercase">Specimen: {{ str_replace('_', ' ', $panel['specimen_type']) }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle small mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code / LOINC</th>
                                        <th>Parameter</th>
                                        <th>Type &amp; Reporting Options</th>
                                        <th>Male / Female Ref Range</th>
                                        <th>Critical Limits</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($panel['tests'] as $t)
                                        <tr>
                                            <td class="font-monospace">
                                                <strong>{{ $t->code }}</strong>
                                                @if($t->loinc_code)<div class="text-muted" style="font-size: 0.7rem;">{{ $t->loinc_code }}</div>@endif
                                            </td>
                                            <td class="fw-semibold">{{ $t->name }}</td>
                                            <td>
                                                <span class="badge {{ $t->isQualitative() ? 'bg-info text-dark' : 'bg-secondary' }} text-uppercase">{{ $t->result_type }}</span>
                                                @if(! empty($t->reporting_options))
                                                    <div class="mt-1">
                                                        @foreach($t->reporting_options as $opt)
                                                            <span class="badge bg-light text-dark border" style="font-size: 0.68rem;">{{ $opt }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="font-monospace">
                                                @if($t->isQualitative())
                                                    Normal: {{ $t->normal_text ?? '—' }}
                                                @else
                                                    M: {{ $t->ref_low_male ?? '—' }}–{{ $t->ref_high_male ?? '—' }} {{ $t->unit }}<br>
                                                    F: {{ $t->ref_low_female ?? $t->ref_low_male ?? '—' }}–{{ $t->ref_high_female ?? $t->ref_high_male ?? '—' }} {{ $t->unit }}
                                                @endif
                                            </td>
                                            <td class="font-monospace text-danger">
                                                @if($t->critical_low !== null || $t->critical_high !== null)
                                                    &le;{{ $t->critical_low ?? '—' }} / &ge;{{ $t->critical_high ?? '—' }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-muted">{{ $t->method_name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-lg-4">
            @can('enterResult', \App\Modules\Lis\Models\LabOrder::class)
                <div class="hmis-card p-3">
                    <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-plus-circle text-primary me-1"></i>Add New Test Parameter / Reporting Options</h2>
                    <form method="POST" action="{{ route('lis.catalog.store') }}">
                        @csrf
                        <div class="row g-2 mb-2">
                            <div class="col-5">
                                <label class="form-label small fw-semibold mb-1">Code *</label>
                                <input type="text" name="code" class="form-control form-control-sm" required placeholder="e.g. FERRITIN">
                            </div>
                            <div class="col-7">
                                <label class="form-label small fw-semibold mb-1">LOINC Code</label>
                                <input type="text" name="loinc_code" class="form-control form-control-sm" placeholder="e.g. 2276-4">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Parameter Name *</label>
                            <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Serum Ferritin">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Section *</label>
                                <select name="section" class="form-select form-select-sm" required>
                                    <option value="cardiac_markers">Cardiac Markers</option>
                                    <option value="hematology">Hematology</option>
                                    <option value="biochemistry">Biochemistry</option>
                                    <option value="coagulation">Coagulation</option>
                                    <option value="serology">Serology</option>
                                    <option value="urinalysis">Urinalysis</option>
                                    <option value="blood_bank">Blood Bank</option>
                                    <option value="microbiology">Microbiology</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Specimen *</label>
                                <select name="specimen_type" class="form-select form-select-sm" required>
                                    <option value="serum">Serum</option>
                                    <option value="edta_blood">EDTA Blood</option>
                                    <option value="citrate_plasma">Citrate Plasma</option>
                                    <option value="urine">Urine</option>
                                    <option value="whole_blood">Whole Blood</option>
                                    <option value="swab">Swab</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-5"><input type="text" name="panel_code" class="form-control form-control-sm" placeholder="Panel Code (e.g. IRON)"></div>
                            <div class="col-7"><input type="text" name="panel_name" class="form-control form-control-sm" placeholder="Panel Name (e.g. Iron Studies)"></div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Result Type *</label>
                                <select name="result_type" class="form-select form-select-sm" required>
                                    <option value="numeric">Numeric (Quantitative)</option>
                                    <option value="select">Select Dropdown Options</option>
                                    <option value="positive_negative">Positive / Negative</option>
                                    <option value="text">Free Text</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Unit (UCUM)</label>
                                <input type="text" name="unit" class="form-control form-control-sm" placeholder="e.g. ng/mL">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Reporting Options (Comma-separated for Select/Qualitative)</label>
                            <textarea name="reporting_options_raw" rows="2" class="form-control form-control-sm" placeholder="e.g. Negative, Positive, Borderline"></textarea>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><input type="text" name="normal_text" class="form-control form-control-sm" placeholder="Normal Text (e.g. Negative)"></div>
                            <div class="col-6"><input type="text" name="method_name" class="form-control form-control-sm" placeholder="Method (e.g. CMIA)"></div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-3"><input type="number" step="0.01" name="ref_low_male" class="form-control form-control-sm" placeholder="M Low"></div>
                            <div class="col-3"><input type="number" step="0.01" name="ref_high_male" class="form-control form-control-sm" placeholder="M High"></div>
                            <div class="col-3"><input type="number" step="0.01" name="ref_low_female" class="form-control form-control-sm" placeholder="F Low"></div>
                            <div class="col-3"><input type="number" step="0.01" name="ref_high_female" class="form-control form-control-sm" placeholder="F High"></div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-4"><input type="number" step="0.01" name="critical_low" class="form-control form-control-sm" placeholder="Crit Low"></div>
                            <div class="col-4"><input type="number" step="0.01" name="critical_high" class="form-control form-control-sm" placeholder="Crit High"></div>
                            <div class="col-4"><input type="number" name="price_pkr" class="form-control form-control-sm" placeholder="PKR"></div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-sm fw-semibold">Add Parameter to LIS Catalog</button>
                        </div>
                    </form>
                </div>
            @endcan
        </div>
    </div>
@endif

<script>
(function () {
    // 1-Click Standard Panel toggles in Direct Booking Panel
    document.querySelectorAll('.lis-panel-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const ids = JSON.parse(btn.dataset.testIds || '[]');
            ids.forEach(id => {
                const cb = document.getElementById('book_t_' + id);
                if (cb) cb.checked = true;
            });
        });
    });

    const clearBtn = document.getElementById('lis-clear-booking');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            document.querySelectorAll('.lis-book-check').forEach(cb => cb.checked = false);
        });
    }

    // Automatic HL7 v2 ORU^R01 Frame Builder for Machine Integration Tab
    const specimenMap = @json($specimenMapForJs);
    const buildBtn = document.getElementById('btn-build-hl7');
    if (buildBtn) {
        buildBtn.addEventListener('click', () => {
            const barcode = document.getElementById('hl7_specimen_select').value;
            const devCode = document.getElementById('hl7_device_code').value;
            const entry = specimenMap.find(s => s.barcode === barcode);
            if (!entry) return;

            const ts = new Date().toISOString().replace(/[-:T.Z]/g, '').slice(0, 14);
            const ctrlId = 'MSG-' + Date.now();
            const lines = [
                `MSH|^~\\&|${devCode}|LIS|CHG-HMIS|CHG|${ts}||ORU^R01|${ctrlId}|P|2.5.1`,
                `OBR|1|${entry.barcode}||PANEL`,
            ];
            entry.test_codes.forEach((tc, idx) => {
                const valType = tc.result_type === 'numeric' ? 'NM' : 'ST';
                lines.push(`OBX|${idx + 1}|${valType}|${tc.code}^${tc.code}||${tc.sample_value}|${tc.unit}`);
            });
            document.getElementById('hl7_raw_payload').value = lines.join('\n');
        });
    }
})();
</script>
@endsection
