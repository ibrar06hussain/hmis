@extends('layouts.app')

@section('title', 'Discharge Summary — '.$admission->admission_number)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <div>
        <a href="{{ route('ipd.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Wards &amp; IPD
        </a>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm fw-semibold" onclick="window.print()">
            <i class="bi bi-printer-fill me-1"></i>Print Discharge Summary / ڈسچارج سمری پرنٹ کریں
        </button>
    </div>
</div>

<div class="hmis-card p-4 bg-white" style="max-width: 960px; margin: 0 auto;">
    {{-- Bilingual Hospital Letterhead --}}
    <div class="d-flex justify-content-between align-items-start border-bottom border-2 border-dark pb-3 mb-3 gap-3">
        <div>
            <div class="fw-bold fs-4 text-dark">{{ config('hmis.facility_name') }}</div>
            <div class="text-muted small">{{ config('hmis.facility_region') }} &middot; Enterprise HMIS</div>
            <div class="mt-2">
                <span class="badge {{ $admission->status === \App\Modules\Ipd\Models\IpdAdmission::STATUS_ADMITTED ? 'bg-warning text-dark' : 'bg-dark' }} text-uppercase">
                    {{ $admission->status === \App\Modules\Ipd\Models\IpdAdmission::STATUS_ADMITTED ? 'Interim Inpatient Summary (Draft)' : 'Official Discharge Summary' }}
                </span>
            </div>
        </div>

        <div class="text-center">
            <div aria-label="Discharge Verification QR">{!! $qrSvg !!}</div>
            <div class="small font-monospace text-muted mt-1">{{ $admission->admission_number }}</div>
        </div>

        <div class="text-end" dir="rtl" lang="ur">
            <div class="fw-bold fs-4 text-dark">کارڈیک ہسپتال گلگت</div>
            <div class="text-muted small">گلگت بلتستان، پاکستان</div>
            <div class="fw-semibold small mt-2">مریض کی ڈسچارج سمری اور ہدایات</div>
        </div>
    </div>

    {{-- Patient & Admission Identity Grid --}}
    <div class="row g-2 mb-3 small border rounded p-3 bg-light">
        <div class="col-md-4">
            <div class="text-muted">Patient Name / <span dir="rtl">مریض کا نام</span></div>
            <div class="fw-bold fs-6">{{ $patient->full_name }}</div>
            <div class="hmis-mrn-badge text-primary">MRN: {{ $patient->mrn }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted">Age &amp; Sex / <span dir="rtl">عمر اور جنس</span></div>
            <div class="fw-semibold">{{ $patient->ageDisplay() }} &middot; {{ ucfirst($patient->sex) }}</div>
            <div class="text-muted">CNIC: {{ $patient->maskedCnic() ?? 'Not Recorded' }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted">Ward &amp; Bed / <span dir="rtl">وارڈ اور بستر</span></div>
            <div class="fw-semibold">{{ $admission->ward?->name }} — Bed {{ $admission->bed?->bed_number }}</div>
            <div class="text-muted">Admitted: {{ \App\Support\DateTimeHelper::formatDateTime($admission->admitted_at, 'd M Y, H:i') }} PKT</div>
            @if($admission->discharged_at)
                <div class="text-muted">Discharged: {{ \App\Support\DateTimeHelper::formatDateTime($admission->discharged_at, 'd M Y, H:i') }} PKT</div>
            @endif
        </div>
    </div>

    {{-- Allergies Safety Strip --}}
    @php
        $activeAllergies = $patient->allergies->where('clinical_status', 'active');
    @endphp
    @if($activeAllergies->isNotEmpty())
        <div class="hmis-allergy-strip p-2 px-3 rounded mb-3 small">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <strong><i class="bi bi-exclamation-octagon-fill me-1"></i>KNOWN ALLERGIES:</strong>
                <strong dir="rtl" lang="ur">ادویات / خوراک سے الرجی:</strong>
            </div>
            <div class="mt-1">
                @foreach($activeAllergies as $allergy)
                    <span class="badge bg-danger me-1">{{ $allergy->substance }} ({{ strtoupper($allergy->severity) }}@if($allergy->reaction) — {{ $allergy->reaction }}@endif)</span>
                @endforeach
            </div>
        </div>
    @else
        <div class="hmis-allergy-strip-safe p-2 px-3 rounded mb-3 small d-flex justify-content-between">
            <span><i class="bi bi-check-circle-fill me-1"></i>No Known Active Drug/Contrast Allergies Recorded</span>
            <span dir="rtl" lang="ur">کوئی معلوم الرجی درج نہیں</span>
        </div>
    @endif

    {{-- Diagnoses --}}
    <div class="mb-3">
        <div class="d-flex justify-content-between border-bottom pb-1 mb-2 fw-bold">
            <span>1. Clinical Diagnoses (ICD-10 / ICD-11)</span>
            <span dir="rtl" lang="ur">۱۔ تشخیصِ مرض</span>
        </div>
        <div class="small mb-2"><strong>Admission Diagnosis:</strong> {{ $admission->admission_diagnosis }}</div>
        @if($diagnoses->isNotEmpty())
            <ul class="small mb-0">
                @foreach($diagnoses as $dx)
                    <li>
                        <span class="badge bg-dark font-monospace">{{ $dx->coding_system }} {{ $dx->code }}</span>
                        <strong>{{ $dx->display_name }}</strong>
                        <span class="text-muted">({{ ucfirst($dx->diagnosis_rank) }} &middot; {{ ucfirst($dx->clinical_status) }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Hospital Course & Discharge Summary --}}
    <div class="mb-3">
        <div class="d-flex justify-content-between border-bottom pb-1 mb-2 fw-bold">
            <span>2. Hospital Course &amp; Discharge Summary</span>
            <span dir="rtl" lang="ur">۲۔ علاج کی تفصیل اور ڈسچارج خلاصہ</span>
        </div>
        <div class="small mb-2">
            <strong>Outcome:</strong> <span class="text-uppercase">{{ str_replace('_', ' ', $admission->status) }}</span>
            @if($admission->discharge_condition)
                &middot; <strong>Condition at Discharge:</strong> <span class="text-uppercase">{{ str_replace('_', ' ', $admission->discharge_condition) }}</span>
            @endif
        </div>
        <div class="p-3 border rounded bg-light small" style="white-space: pre-line;">{{ $admission->discharge_summary ?: 'Patient currently admitted; final discharge narrative will populate upon discharge sign-off.' }}</div>
    </div>

    {{-- Discharge Medications --}}
    <div class="mb-3">
        <div class="d-flex justify-content-between border-bottom pb-1 mb-2 fw-bold">
            <span>3. Prescribed / Discharge Medications</span>
            <span dir="rtl" lang="ur">۳۔ گھر کے لیے تجویز کردہ ادویات</span>
        </div>
        @php
            $rxItems = $prescriptions->flatMap(fn ($rx) => $rx->items);
        @endphp
        @if($rxItems->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm table-bordered small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Medication / <span dir="rtl">دوا کا نام</span></th>
                            <th>Dose &amp; Route / <span dir="rtl">خوراک</span></th>
                            <th>Frequency / <span dir="rtl">اوقات</span></th>
                            <th>Duration / <span dir="rtl">مدت</span></th>
                            <th>Instructions / <span dir="rtl">ہدایات</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rxItems as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->medication?->generic_name ?? 'Medication' }} {{ $item->medication?->strength }}</td>
                                <td>{{ $item->dose }} ({{ strtoupper($item->route) }})</td>
                                <td>{{ $item->frequency }}</td>
                                <td>{{ $item->duration_days }} days</td>
                                <td>{{ $item->instructions ?: 'Take as directed' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="small text-muted">No inpatient/discharge prescriptions attached to this encounter.</div>
        @endif
    </div>

    {{-- Follow-up & Bilingual Cardiac Emergency Red-Flag Warnings --}}
    <div class="mb-4">
        <div class="d-flex justify-content-between border-bottom pb-1 mb-2 fw-bold">
            <span>4. Follow-Up Plan &amp; Emergency Cardiac Warnings</span>
            <span dir="rtl" lang="ur">۴۔ فالو اپ اور ہنگامی علامات کی ہدایات</span>
        </div>
        <div class="small mb-2">
            <strong>Follow-Up Plan:</strong> {{ $admission->follow_up_plan ?: 'Follow up at Cardiology OPD within 7–14 days with this discharge summary and latest lab/ECG reports.' }}
        </div>
        <div class="row g-2 small">
            <div class="col-md-6">
                <div class="p-2 border border-danger rounded bg-danger bg-opacity-10">
                    <strong class="text-danger">Return to Cardiac Emergency Immediately If:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <li>Chest pain, tightness, or pain radiating to arm/jaw</li>
                        <li>Severe shortness of breath or inability to lie flat</li>
                        <li>Fainting, severe dizziness, or sudden palpitations</li>
                        <li>Do NOT stop blood thinners / cardiac medicines without doctor advice.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6" dir="rtl" lang="ur">
                <div class="p-2 border border-danger rounded bg-danger bg-opacity-10">
                    <strong class="text-danger">ان علامات کی صورت میں فوری ایمرجنسی تشریف لائیں:</strong>
                    <ul class="mb-0 mt-1 pe-3">
                        <li>سینے میں درد، دباؤ یا بازو/جبڑے کی طرف درد کا پھیلنا</li>
                        <li>سانس پھولنا یا لیٹنے میں شدید دشواری</li>
                        <li>چکر آنا، بے ہوشی یا دل کی دھڑکن کا اچانک تیز ہونا</li>
                        <li>ڈاکٹر کے مشورے کے بغیر دل یا خون پتلا کرنے والی دوائیں بند نہ کریں۔</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Consultant Sign-Off Block --}}
    <div class="row pt-3 border-top small">
        <div class="col-6">
            <div class="text-muted">Attending / Discharging Consultant</div>
            <div class="fw-bold mt-2">{{ $admission->dischargedBy?->name ?? $admission->attendingStaff?->user?->name ?? 'On-Duty Cardiology Consultant' }}</div>
            <div class="text-muted">Signature &amp; Stamp: ____________________________</div>
        </div>
        <div class="col-6 text-end">
            <div class="text-muted">Printed (PKT) / <span dir="rtl">پرنٹ کا وقت</span></div>
            <div class="fw-semibold mt-2">{{ \App\Support\DateTimeHelper::nowDisplay() }}</div>
            <div class="text-muted">Hospital Reference: {{ $admission->admission_number }}</div>
        </div>
    </div>
</div>
@endsection
