@extends('layouts.app')

@section('title', 'Executive Operational Reporting Dashboard')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>Executive Operational &amp; Clinical Governance Report</h1>
        <small class="text-muted">{{ config('hmis.facility_name') }} &middot; Snapshot generated {{ \App\Support\DateTimeHelper::formatDateTime($summary['generated_at_utc']) }}</small>
    </div>
    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-dark no-print">
        <i class="bi bi-printer me-1"></i>Print Executive Summary
    </button>
</div>

{{-- Top-Line Executive KPI Row --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Overall Bed Occupancy</div>
            <div class="fs-3 fw-bold text-primary">{{ $summary['beds']['occupancy_percent'] }}%</div>
            <small class="text-muted">{{ $summary['beds']['occupied'] }} occupied / {{ $summary['beds']['total'] }} beds ({{ $summary['beds']['cleaning'] }} cleaning)</small>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Clinical Volume</div>
            <div class="fs-3 fw-bold">{{ $summary['clinical_volume']['active_inpatients'] }} IPD</div>
            <small class="text-muted">{{ $summary['clinical_volume']['opd_appointments_today'] }} OPD today &middot; {{ $summary['clinical_volume']['code_stemi_total'] }} Code STEMI</small>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">LIS Turnaround &amp; Criticals</div>
            <div class="fs-3 fw-bold text-success">{{ $summary['diagnostics']['lab_avg_tat_minutes'] !== null ? $summary['diagnostics']['lab_avg_tat_minutes'].' min' : 'N/A' }}</div>
            <small class="{{ $summary['diagnostics']['lab_unacknowledged_critical'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                {{ $summary['diagnostics']['lab_unacknowledged_critical'] }} unacknowledged critical(s)
            </small>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="hmis-card p-3 h-100">
            <div class="text-muted small">Collected Revenue (PKR)</div>
            <div class="fs-3 fw-bold text-dark">{{ number_format($summary['financial']['total_collected_pkr'], 0) }}</div>
            <small class="text-muted">Exemptions: PKR {{ number_format($summary['financial']['total_exemptions_pkr'], 0) }}</small>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Ward Bed Occupancy Breakdown --}}
    <div class="col-lg-7">
        <div class="hmis-card p-3 h-100">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Ward-by-Ward Bed Occupancy</h2>
            @foreach($summary['beds']['wards'] as $ward)
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">{{ $ward['name'] }} <span class="text-muted">({{ strtoupper($ward['ward_type']) }})</span></span>
                        <span>{{ $ward['occupied_beds'] }} / {{ $ward['total_beds'] }} ({{ $ward['occupancy_percent'] }}%)</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar {{ $ward['occupancy_percent'] >= 85 ? 'bg-danger' : ($ward['occupancy_percent'] >= 60 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $ward['occupancy_percent'] }}%;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Financial, Diagnostics & Governance Summary --}}
    <div class="col-lg-5">
        <div class="hmis-card p-3 mb-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-2">Financial &amp; Government Waiver Ledger</h2>
            <dl class="row small mb-0">
                <dt class="col-7 text-muted">Total Gross Billed</dt>
                <dd class="col-5 text-end font-monospace">PKR {{ number_format($summary['financial']['total_billed_pkr'], 2) }}</dd>
                <dt class="col-7 text-muted">Sehat Card / Zakat / Govt Waivers</dt>
                <dd class="col-5 text-end font-monospace text-success">PKR {{ number_format($summary['financial']['total_exemptions_pkr'], 2) }}</dd>
                <dt class="col-7 text-muted">Cash / Card Collected</dt>
                <dd class="col-5 text-end font-monospace fw-bold">PKR {{ number_format($summary['financial']['total_collected_pkr'], 2) }}</dd>
                <dt class="col-7 text-muted">Outstanding Patient Balance</dt>
                <dd class="col-5 text-end font-monospace text-danger">PKR {{ number_format($summary['financial']['total_outstanding_pkr'], 2) }}</dd>
            </dl>
        </div>

        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-2">Diagnostics, Pharmacy &amp; Security Governance</h2>
            <dl class="row small mb-0">
                <dt class="col-8 text-muted">Imaging Studies Linked to PACS UID</dt>
                <dd class="col-4 text-end fw-semibold">{{ $summary['diagnostics']['imaging_pacs_linked'] }} / {{ $summary['diagnostics']['imaging_total'] }}</dd>
                <dt class="col-8 text-muted">Low-Stock Formulary Medications</dt>
                <dd class="col-4 text-end fw-semibold">{{ $summary['pharmacy']['low_stock_medications'] }}</dd>
                <dt class="col-8 text-muted">Active Break-Glass Overrides</dt>
                <dd class="col-4 text-end fw-bold {{ $summary['governance']['active_break_glass'] > 0 ? 'text-danger' : 'text-success' }}">{{ $summary['governance']['active_break_glass'] }}</dd>
                <dt class="col-8 text-muted">Audit Events (Last 24h)</dt>
                <dd class="col-4 text-end fw-semibold">{{ number_format($summary['governance']['audit_events_24h']) }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
