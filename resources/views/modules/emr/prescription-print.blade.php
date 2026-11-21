@extends('layouts.app')

@section('title', 'Bilingual OPD Prescription — '.$patient->mrn)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <div class="d-flex gap-2">
        <a href="{{ route('encounters.rapid.show', $encounter) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Rapid Consult
        </a>
        <a href="{{ route('opd.index') }}" class="btn btn-sm btn-outline-secondary">OPD Queue</a>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm fw-semibold" onclick="window.print()">
            <i class="bi bi-printer-fill me-1"></i>Print Prescription / نسخہ پرنٹ کریں
        </button>
        @if($nextWaiting)
            @can('consult', $nextWaiting)
                <form method="POST" action="{{ route('opd.appointments.start-consult', $nextWaiting) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="mode" value="rapid">
                    <button type="submit" class="btn btn-success btn-sm fw-semibold">
                        Next Patient: Token {{ $nextWaiting->formattedToken() }} ({{ $nextWaiting->patient->full_name }}) <i class="bi bi-arrow-right-circle ms-1"></i>
                    </button>
                </form>
            @endcan
        @endif
    </div>
</div>

<div class="hmis-card p-4 bg-white" style="max-width: 900px; margin: 0 auto;">
    {{-- Bilingual Hospital Letterhead --}}
    <div class="d-flex justify-content-between align-items-start border-bottom border-2 border-dark pb-3 mb-3 gap-2">
        <div>
            <div class="fw-bold fs-4">{{ config('hmis.facility_name') }}</div>
            <div class="small text-muted">{{ config('hmis.facility_region') }} &middot; {{ $encounter->department->name }}</div>
            <div class="small font-monospace fw-bold mt-1">{{ $encounter->encounter_number }}</div>
        </div>
        <div class="text-center" aria-label="Prescription Verification QR">{!! $qrSvg !!}</div>
        <div class="text-end" dir="rtl" lang="ur">
            <div class="fw-bold fs-4">کارڈیک ہسپتال گلگت</div>
            <div class="small text-muted">او پی ڈی طبی نسخہ اور ہدایات</div>
            <div class="small mt-1">{{ \App\Support\DateTimeHelper::formatDateTime($encounter->started_at, 'd M Y, H:i') }} PKT</div>
        </div>
    </div>

    {{-- Patient & Vitals Strip --}}
    <div class="row g-2 small border rounded p-2 px-3 bg-light mb-3">
        <div class="col-md-5">
            <span class="text-muted">Patient / <span dir="rtl">مریض</span>:</span>
            <strong class="fs-6 ms-1">{{ $patient->full_name }}</strong>
            <div class="hmis-mrn-badge text-primary">{{ $patient->mrn }} &middot; {{ $patient->ageDisplay() }} / {{ ucfirst($patient->sex) }}</div>
        </div>
        <div class="col-md-4">
            <span class="text-muted">Vitals / <span dir="rtl">اہم علامات</span>:</span>
            @if($latestVital)
                <div class="fw-semibold">
                    BP: {{ $latestVital->systolic_bp ?? '—' }}/{{ $latestVital->diastolic_bp ?? '—' }} mmHg &middot;
                    HR: {{ $latestVital->heart_rate ?? '—' }} &middot;
                    SpO2: {{ $latestVital->spo2_percent ?? '—' }}%
                </div>
            @else
                <div class="text-muted">Not recorded</div>
            @endif
        </div>
        <div class="col-md-3 text-md-end">
            <span class="text-muted">Consultant / <span dir="rtl">معالج</span>:</span>
            <div class="fw-semibold">{{ $encounter->attendingStaff?->user?->name ?? $latestNote?->signedBy?->name ?? 'Cardiology Consultant' }}</div>
        </div>
    </div>

    {{-- Allergy Strip --}}
    @if($patient->activeAllergies->isNotEmpty())
        <div class="hmis-allergy-strip p-2 px-3 rounded mb-3 small">
            <strong>ALLERGIES / <span dir="rtl">الرجی</span>:</strong>
            @foreach($patient->activeAllergies as $allergy)
                <span class="badge bg-danger ms-1">{{ $allergy->substance }}</span>
            @endforeach
        </div>
    @endif

    {{-- Clinical Diagnosis & Summary --}}
    <div class="mb-3 small">
        <div class="d-flex justify-content-between border-bottom pb-1 mb-1 fw-bold">
            <span>Diagnosis &amp; Assessment</span>
            <span dir="rtl" lang="ur">تشخیصِ مرض</span>
        </div>
        @if($encounter->diagnoses->isNotEmpty())
            <div class="mb-1">
                @foreach($encounter->diagnoses as $dx)
                    <span class="badge bg-dark font-monospace">{{ $dx->code }}</span>
                    <strong class="me-2">{{ $dx->display_name }}</strong>
                @endforeach
            </div>
        @endif
        @if($latestNote?->assessment_and_diagnosis)
            <div>{{ $latestNote->assessment_and_diagnosis }}</div>
        @endif
    </div>

    {{-- Rx Table with Visual Urdu Morning / Noon / Night Schedule --}}
    <div class="mb-3">
        <div class="d-flex justify-content-between border-bottom pb-1 mb-2 fw-bold">
            <span><i class="bi bi-capsule-pill me-1"></i>Rx — Prescribed Medications</span>
            <span dir="rtl" lang="ur">ادویات استعمال کرنے کا طریقہ (صبح / دوپہر / رات)</span>
        </div>

        @php
            $rxItems = $encounter->prescriptions->flatMap(fn ($rx) => $rx->items);
        @endphp

        @if($rxItems->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle small mb-0">
                    <thead class="table-light text-center">
                        <tr>
                            <th class="text-start" style="width: 30%;">Medicine / <span dir="rtl">دوا کا نام</span></th>
                            <th style="width: 11%;">صبح<br><small class="fw-normal text-muted">Morning</small></th>
                            <th style="width: 11%;">دوپہر<br><small class="fw-normal text-muted">Noon</small></th>
                            <th style="width: 11%;">رات<br><small class="fw-normal text-muted">Night</small></th>
                            <th style="width: 10%;">Days<br><small class="fw-normal text-muted" dir="rtl">دن</small></th>
                            <th class="text-start" style="width: 27%;">Timing &amp; Advice / <span dir="rtl">ہدایات</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rxItems as $item)
                            @php
                                $sched = \App\Modules\Emr\Services\RapidConsultService::scheduleMatrix($item->frequency, $item->dose);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $item->medication?->generic_name }} {{ $item->medication?->strength }}</div>
                                    <div class="text-muted">
                                        @if($item->medication?->brand_name)({{ $item->medication->brand_name }}) &middot; @endif
                                        {{ $item->dose }} {{ strtoupper($item->route) }} &middot; <code>{{ $item->frequency }}</code>
                                    </div>
                                </td>
                                <td class="text-center fs-6 fw-bold {{ $sched['morning'] !== '—' ? 'bg-warning bg-opacity-25' : 'text-muted' }}">{{ $sched['morning'] }}</td>
                                <td class="text-center fs-6 fw-bold {{ $sched['noon'] !== '—' ? 'bg-info bg-opacity-25' : 'text-muted' }}">{{ $sched['noon'] }}</td>
                                <td class="text-center fs-6 fw-bold {{ $sched['night'] !== '—' ? 'bg-primary bg-opacity-10' : 'text-muted' }}">{{ $sched['night'] }}</td>
                                <td class="text-center fw-semibold">{{ $item->duration_days }} <div class="text-muted" style="font-size: 0.72rem;">Qty {{ $item->quantity_prescribed }}</div></td>
                                <td>
                                    <div class="fw-semibold" dir="rtl" lang="ur">{{ $sched['urdu_label'] }}</div>
                                    <div class="text-muted" dir="rtl" lang="ur">{{ \App\Modules\Emr\Services\RapidConsultService::mealTimingUrdu($item->meal_timing) }}</div>
                                    @if($item->instructions)<div class="text-muted">{{ $item->instructions }}</div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="small text-muted">No medications prescribed in this encounter.</div>
        @endif
    </div>

    {{-- Ordered Investigations --}}
    @if($encounter->labOrders->isNotEmpty() || $encounter->imagingStudies->isNotEmpty())
        <div class="mb-3 small">
            <div class="d-flex justify-content-between border-bottom pb-1 mb-1 fw-bold">
                <span>Ordered Investigations (Lab / ECG / Echo)</span>
                <span dir="rtl" lang="ur">تجویز کردہ ٹیسٹ</span>
            </div>
            <div class="d-flex flex-wrap gap-1">
                @foreach($encounter->labOrders as $lo)
                    @foreach($lo->items as $li)
                        <span class="badge bg-light text-dark border">LAB: {{ $li->test?->name }}</span>
                    @endforeach
                @endforeach
                @foreach($encounter->imagingStudies as $img)
                    <span class="badge bg-light text-dark border">{{ $img->modality }}: {{ $img->procedure_name }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Follow-Up & Red-Flag Cardiac Warnings --}}
    <div class="row g-2 small mb-3">
        <div class="col-md-6">
            <div class="p-2 border rounded bg-light h-100">
                <strong>Follow-Up / Advice:</strong>
                <div>{{ $latestNote?->follow_up_instructions ?: 'Follow up at Cardiology OPD as advised.' }}</div>
                @if($latestNote?->treatment_plan)<div class="text-muted mt-1">{{ $latestNote->treatment_plan }}</div>@endif
            </div>
        </div>
        <div class="col-md-6" dir="rtl" lang="ur">
            <div class="p-2 border border-danger rounded bg-danger bg-opacity-10 h-100">
                <strong class="text-danger">ہنگامی ہدایات:</strong>
                <div>سینے میں شدید درد، سانس پھولنا یا چکر آنے کی صورت میں فوری کارڈیک ایمرجنسی تشریف لائیں۔ ڈاکٹر کے مشورے کے بغیر دل کی دوائیں بند نہ کریں۔</div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-end pt-2 border-top small text-muted">
        <div>
            Signed: <strong>{{ $latestNote?->signedBy?->name ?? $encounter->attendingStaff?->user?->name ?? 'Consultant' }}</strong>
            @if($latestNote?->signed_at)
                ({{ \App\Support\DateTimeHelper::formatDateTime($latestNote->signed_at, 'd M Y, H:i') }} PKT)
            @endif
        </div>
        <div>Stamp &amp; Signature: ________________________</div>
    </div>
</div>
@endsection
