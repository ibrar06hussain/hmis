@extends('layouts.app')

@section('title', 'Laboratory Information System (LIS)')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-eyedropper me-2 text-primary"></i>Laboratory Information System (LIS) Workbench</h1>
        <small class="text-muted">Barcode specimen tracking, staged analyzer/manual results, critical value alerts, and 2-step Pathologist verification gate</small>
    </div>
    <div class="btn-group btn-group-sm">
        <a href="{{ route('lis.index', ['status' => 'open']) }}" class="btn {{ $statusFilter === 'open' ? 'btn-primary' : 'btn-outline-secondary' }}">Pending / In-Process</a>
        <a href="{{ route('lis.index', ['status' => 'verified']) }}" class="btn {{ $statusFilter === 'verified' ? 'btn-success' : 'btn-outline-secondary' }}">Verified Reports</a>
        <a href="{{ route('lis.index', ['status' => 'all']) }}" class="btn {{ $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' }}">All Orders</a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Analyzer Device Registry Summary --}}
<div class="hmis-card p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="small fw-bold text-uppercase text-muted"><i class="bi bi-cpu me-1"></i>Registered Laboratory Analyzers (LAN Gateway)</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach($devices as $device)
                <span class="badge bg-light text-dark border">
                    <span class="badge {{ $device->connection_status === 'online' ? 'bg-success' : 'bg-secondary' }} me-1">{{ strtoupper($device->connection_status) }}</span>
                    {{ $device->device_code }} ({{ $device->model }} &middot; {{ strtoupper($device->protocol) }})
                </span>
            @endforeach
        </div>
    </div>
</div>

@forelse($orders as $order)
    <div class="hmis-card p-3 mb-3 {{ $order->priority === 'stat' ? 'border-danger' : '' }}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-3">
            <div>
                <span class="badge {{ $order->priority === 'stat' ? 'bg-danger' : ($order->priority === 'urgent' ? 'bg-warning text-dark' : 'bg-secondary') }} text-uppercase">{{ $order->priority }}</span>
                <span class="fw-bold font-monospace ms-1">{{ $order->order_number }}</span>
                <span class="mx-2">&middot;</span>
                <a href="{{ route('patients.show', $order->patient) }}" class="fw-semibold text-decoration-none">{{ $order->patient->full_name }}</a>
                <span class="badge bg-light text-dark border hmis-mrn-badge ms-1">{{ $order->patient->mrn }}</span>
            </div>
            <div class="small text-muted">
                Ordered: {{ \App\Support\DateTimeHelper::formatDateTime($order->ordered_at) }}
                <span class="badge {{ $order->status === 'verified' ? 'bg-success' : 'bg-info text-dark' }} ms-2 text-uppercase">{{ str_replace('_', ' ', $order->status) }}</span>
            </div>
        </div>

        {{-- Barcode Specimens Row --}}
        <div class="mb-3">
            <div class="small fw-semibold text-muted mb-1">Specimens &amp; Barcodes</div>
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
                                    <button type="submit" class="btn btn-xs btn-success py-0 px-2">Receive in Lab</button>
                                </form>
                            @endcan
                        @endif

                        @if($specimen->rejection_reason)
                            <span class="text-danger">({{ $specimen->rejection_reason }})</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Test Items & 2-Step Verification Table --}}
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Test (LOINC)</th>
                        <th>Reference Range</th>
                        <th>Result &amp; Source</th>
                        <th>Flags</th>
                        <th>Workflow Status</th>
                        <th class="text-end">Validation / Release</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr class="{{ $item->is_critical ? 'table-danger' : '' }}">
                            <td>
                                <div class="fw-semibold">{{ $item->test->name }}</div>
                                <span class="text-muted font-monospace">{{ $item->test->code }}</span>
                                @if($item->test->loinc_code)<span class="text-muted"> &middot; LOINC {{ $item->test->loinc_code }}</span>@endif
                            </td>
                            <td class="font-monospace">
                                {{ $item->ref_low ?? '—' }} – {{ $item->ref_high ?? '—' }} {{ $item->unit }}
                            </td>
                            <td>
                                @if($item->result_value_text !== null)
                                    <span class="fw-bold">{{ $item->result_value_text }} {{ $item->unit }}</span>
                                    <span class="badge bg-light text-muted border ms-1">{{ strtoupper($item->result_source) }}</span>
                                @else
                                    <span class="text-muted">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if($item->is_critical)
                                    <span class="badge bg-danger">CRITICAL ({{ strtoupper($item->abnormal_flag) }})</span>
                                    @if($item->critical_acknowledged_at)
                                        <div class="text-success" style="font-size: 0.72rem;">Ack by {{ $item->criticalAcknowledgedBy?->name }}</div>
                                    @endif
                                @elseif($item->abnormal_flag && $item->abnormal_flag !== 'normal')
                                    <span class="badge bg-warning text-dark">{{ strtoupper($item->abnormal_flag) }}</span>
                                @elseif($item->result_value_text !== null)
                                    <span class="badge bg-success-subtle text-success">NORMAL</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $item->status === 'verified' ? 'bg-success' : ($item->status === 'tech_validated' ? 'bg-primary' : 'bg-secondary') }} text-uppercase">
                                    {{ str_replace('_', ' ', $item->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if($item->status !== 'verified')
                                    @can('enterResult', \App\Modules\Lis\Models\LabOrder::class)
                                        <form method="POST" action="{{ route('lis.items.result', $item) }}" class="d-inline-flex gap-1 align-items-center me-1">
                                            @csrf
                                            <input type="number" step="0.001" name="result_value_numeric" value="{{ $item->result_value_numeric }}" class="form-control form-control-sm" style="width: 95px;" placeholder="Value">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                        </form>
                                    @endcan
                                @endif

                                @if($item->status === 'result_entered')
                                    @can('enterResult', \App\Modules\Lis\Models\LabOrder::class)
                                        <form method="POST" action="{{ route('lis.items.tech-validate', $item) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-warning">Tech Validate</button>
                                        </form>
                                    @endcan
                                @endif

                                @if(in_array($item->status, ['result_entered', 'tech_validated'], true))
                                    @can('verifyResult', \App\Modules\Lis\Models\LabOrder::class)
                                        <form method="POST" action="{{ route('lis.items.verify', $item) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success fw-semibold">Verify &amp; Release</button>
                                        </form>
                                    @endcan
                                @endif

                                @if($item->is_critical && $item->critical_acknowledged_at === null)
                                    @can('acknowledgeCritical', \App\Modules\Lis\Models\LabOrder::class)
                                        <form method="POST" action="{{ route('lis.items.acknowledge-critical', $item) }}" class="d-inline ms-1">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-danger">Ack Critical</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="hmis-card p-4 text-center text-muted">No laboratory orders match this filter.</div>
@endforelse
@endsection
