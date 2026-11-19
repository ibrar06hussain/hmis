@extends('layouts.app')

@section('title', 'OPD Visit Slip — '.$appointment->formattedToken())

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="{{ route('opd.index', ['date' => $appointment->appointment_date->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to OPD Queue
    </a>
    <button type="button" class="btn btn-primary btn-sm fw-semibold" onclick="window.print()">
        <i class="bi bi-printer-fill me-1"></i>Print OPD Slip / پرچی پرنٹ کریں
    </button>
</div>

<div class="hmis-card p-4 bg-white" style="max-width: 680px; margin: 0 auto;">
    <div class="d-flex justify-content-between align-items-start border-bottom border-2 border-dark pb-3 mb-3 gap-2">
        <div>
            <div class="fw-bold fs-5">{{ config('hmis.facility_name') }}</div>
            <div class="small text-muted">OPD Token &amp; Consultation Slip</div>
        </div>
        <div class="text-end" dir="rtl" lang="ur">
            <div class="fw-bold fs-5">کارڈیک ہسپتال گلگت</div>
            <div class="small text-muted">او پی ڈی ٹوکن اور معائنہ پرچی</div>
        </div>
    </div>

    <div class="row align-items-center g-3 mb-3">
        <div class="col-7">
            <div class="small text-muted">Token Number / <span dir="rtl">ٹوکن نمبر</span></div>
            <div class="display-5 fw-bold font-monospace text-primary">{{ $appointment->formattedToken() }}</div>
            <div class="small mt-1">
                <span class="badge bg-dark text-uppercase">{{ str_replace('_', ' ', $appointment->visit_type) }}</span>
                <span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $appointment->status) }}</span>
            </div>
        </div>
        <div class="col-5 text-end">
            <div class="d-inline-block p-2 border rounded bg-white" aria-label="OPD Slip QR Code">{!! $qrSvg !!}</div>
        </div>
    </div>

    <table class="table table-sm table-bordered small mb-3">
        <tbody>
            <tr>
                <th class="bg-light" style="width: 38%;">Patient Name / <span dir="rtl">مریض کا نام</span></th>
                <td class="fw-bold">{{ $appointment->patient->full_name }}</td>
            </tr>
            <tr>
                <th class="bg-light">MRN / <span dir="rtl">مریض نمبر</span></th>
                <td class="hmis-mrn-badge">{{ $appointment->patient->mrn }}</td>
            </tr>
            <tr>
                <th class="bg-light">Age &amp; Sex / <span dir="rtl">عمر اور جنس</span></th>
                <td>{{ $appointment->patient->ageDisplay() }} &middot; {{ ucfirst($appointment->patient->sex) }}</td>
            </tr>
            <tr>
                <th class="bg-light">Department / <span dir="rtl">شعبہ</span></th>
                <td class="fw-semibold">{{ $appointment->department->name }}</td>
            </tr>
            <tr>
                <th class="bg-light">Clinician / <span dir="rtl">معالج</span></th>
                <td>{{ $appointment->clinician?->user?->name ?? 'On-Duty Cardiology Clinician' }}</td>
            </tr>
            <tr>
                <th class="bg-light">Clinic Date / <span dir="rtl">تاریخِ معائنہ</span></th>
                <td>{{ $appointment->appointment_date->format('d M Y') }}</td>
            </tr>
            @if($appointment->encounter)
                <tr>
                    <th class="bg-light">Encounter # / <span dir="rtl">انکاؤنٹر نمبر</span></th>
                    <td class="font-monospace">{{ $appointment->encounter->encounter_number }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="border-top pt-2 small text-muted d-flex justify-content-between flex-wrap gap-2">
        <span>Please present this slip at the Nursing Vitals Desk &amp; Clinic Room.</span>
        <span dir="rtl" lang="ur">برائے مہربانی یہ پرچی نرسنگ ڈیسک اور ڈاکٹر کے کمرے میں دکھائیں۔</span>
    </div>
    <div class="small text-muted mt-2 text-end">Printed: {{ \App\Support\DateTimeHelper::nowDisplay() }}</div>
</div>
@endsection
