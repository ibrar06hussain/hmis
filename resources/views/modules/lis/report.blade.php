@extends('layouts.app')

@section('title', 'Laboratory Report — '.$order->order_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="{{ route('lis.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to LIS Workbench
    </a>
    <button type="button" class="btn btn-primary btn-sm fw-semibold" onclick="window.print()">
        <i class="bi bi-printer-fill me-1"></i>Print Official Lab Report / لیب رپورٹ پرنٹ کریں
    </button>
</div>

<div class="hmis-card p-4 bg-white" style="max-width: 940px; margin: 0 auto;">
    @if($order->status !== \App\Modules\Lis\Models\LabOrder::STATUS_VERIFIED)
        <div class="alert alert-warning fw-bold text-center small mb-3">
            PRELIMINARY / UNVERIFIED REPORT — AWAITING AUTHORIZED PATHOLOGIST FINAL SIGN-OFF
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-start border-bottom border-2 border-dark pb-3 mb-3 gap-2">
        <div>
            <div class="fw-bold fs-4">{{ config('hmis.facility_name') }}</div>
            <div class="small text-muted">Department of Pathology &amp; Clinical Laboratory (LIS)</div>
            <div class="small font-monospace fw-bold mt-1">{{ $order->order_number }}</div>
        </div>
        <div class="text-center" aria-label="Lab Report Verification QR">{!! $qrSvg !!}</div>
        <div class="text-end" dir="rtl" lang="ur">
            <div class="fw-bold fs-4">کارڈیک ہسپتال گلگت</div>
            <div class="small text-muted">پیتھالوجی اور کلینیکل لیبارٹری رپورٹ</div>
            <div class="small mt-1">{{ \App\Support\DateTimeHelper::formatDateTime($order->ordered_at, 'd M Y, H:i') }} PKT</div>
        </div>
    </div>

    <div class="row g-2 small border rounded p-2 px-3 bg-light mb-3">
        <div class="col-md-4">
            <div class="text-muted">Patient / <span dir="rtl">مریض</span></div>
            <div class="fw-bold fs-6">{{ $patient->full_name }}</div>
            <div class="hmis-mrn-badge text-primary">{{ $patient->mrn }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted">Age &amp; Sex / <span dir="rtl">عمر اور جنس</span></div>
            <div class="fw-semibold">{{ $patient->ageDisplay() }} &middot; {{ ucfirst($patient->sex) }}</div>
            <div class="text-muted">Priority: <span class="text-uppercase fw-semibold">{{ $order->priority }}</span></div>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="text-muted">Specimen Barcodes</div>
            @foreach($order->specimens as $sp)
                <div class="font-monospace">{{ $sp->barcode }} ({{ $sp->specimen_type }})</div>
            @endforeach
        </div>
    </div>

    @foreach($groupedItems as $panelTitle => $items)
        <div class="mb-3">
            <div class="fw-bold small bg-dark text-white px-2 py-1 rounded-top">{{ $panelTitle }}</div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 32%;">Parameter / Test</th>
                            <th style="width: 20%;">Result</th>
                            <th style="width: 10%;">Flag</th>
                            <th style="width: 22%;">Reference Range</th>
                            <th style="width: 16%;">Method / Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr class="{{ $item->is_critical ? 'table-danger' : ($item->abnormal_flag && $item->abnormal_flag !== 'normal' ? 'table-warning' : '') }}">
                                <td>
                                    <div class="fw-semibold">{{ $item->test->name }}</div>
                                    <span class="text-muted font-monospace" style="font-size: 0.72rem;">{{ $item->test->code }}@if($item->test->loinc_code) &middot; LOINC {{ $item->test->loinc_code }}@endif</span>
                                    @if($item->remarks)<div class="text-muted fst-italic">Note: {{ $item->remarks }}</div>@endif
                                </td>
                                <td class="fw-bold fs-6">
                                    {{ $item->result_value_text ?? 'Pending' }} {{ $item->unit }}
                                </td>
                                <td>
                                    @if($item->is_critical)
                                        <span class="badge bg-danger">CRITICAL</span>
                                    @elseif($item->abnormal_flag && $item->abnormal_flag !== 'normal')
                                        <span class="badge bg-warning text-dark text-uppercase">{{ $item->abnormal_flag }}</span>
                                    @elseif($item->result_value_text !== null)
                                        <span class="badge bg-success-subtle text-success">NORMAL</span>
                                    @endif
                                </td>
                                <td class="font-monospace">{{ $item->test->referenceDisplayForSex($patient->sex) }}</td>
                                <td class="text-muted">
                                    <div>{{ $item->test->method_name ?? 'Standard' }}</div>
                                    <div style="font-size: 0.7rem;">{{ strtoupper($item->result_source) }}@if($item->analyzer_device_code) ({{ $item->analyzer_device_code }})@endif</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <div class="row pt-3 border-top small">
        <div class="col-6">
            <div class="text-muted">Verified &amp; Electronically Signed By</div>
            <div class="fw-bold mt-1">{{ $order->verifiedBy?->name ?? 'Pending Pathologist Verification' }}</div>
            @if($order->verified_at)
                <div class="text-muted">Verified at: {{ \App\Support\DateTimeHelper::formatDateTime($order->verified_at, 'd M Y, H:i') }} PKT</div>
            @endif
        </div>
        <div class="col-6 text-end text-muted">
            <div>Printed (PKT): {{ \App\Support\DateTimeHelper::nowDisplay() }}</div>
            <div>Electronically generated — Cardiac Hospital Gilgit LIS</div>
        </div>
    </div>
</div>
@endsection
