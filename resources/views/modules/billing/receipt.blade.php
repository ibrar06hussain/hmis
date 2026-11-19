@extends('layouts.app')

@section('title', 'Hospital Receipt — '.$invoice->invoice_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="{{ route('billing.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Billing Desk
    </a>
    <button type="button" class="btn btn-primary btn-sm fw-semibold" onclick="window.print()">
        <i class="bi bi-printer-fill me-1"></i>Print Official Receipt / رسید پرنٹ کریں
    </button>
</div>

<div class="hmis-card p-4 bg-white" style="max-width: 780px; margin: 0 auto;">
    @if($invoice->status === \App\Modules\Billing\Models\Invoice::STATUS_VOIDED)
        <div class="alert alert-danger fw-bold text-center text-uppercase mb-3">
            VOIDED INVOICE — REVERSED IN FINANCIAL LEDGER / منسوخ شدہ رسید
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-start border-bottom border-2 border-dark pb-3 mb-3 gap-2">
        <div>
            <div class="fw-bold fs-5">{{ config('hmis.facility_name') }}</div>
            <div class="small text-muted">Official OPD / Hospital Fee Receipt</div>
            <div class="small font-monospace fw-bold mt-1">{{ $invoice->invoice_number }}</div>
        </div>

        <div class="text-center" aria-label="Receipt Verification QR">{!! $qrSvg !!}</div>

        <div class="text-end" dir="rtl" lang="ur">
            <div class="fw-bold fs-5">کارڈیک ہسپتال گلگت</div>
            <div class="small text-muted">سرکاری ہسپتال فیس رسید</div>
            <div class="small mt-1">{{ strtoupper(str_replace('_', ' ', $invoice->billing_category)) }}</div>
        </div>
    </div>

    <div class="row g-2 small mb-3 bg-light border rounded p-3">
        <div class="col-sm-6">
            <div class="text-muted">Patient / <span dir="rtl">مریض کا نام</span></div>
            <div class="fw-bold">{{ $invoice->patient->full_name }}</div>
            <div class="hmis-mrn-badge text-primary">{{ $invoice->patient->mrn }}</div>
        </div>
        <div class="col-sm-6 text-sm-end">
            <div class="text-muted">Issued (PKT) / <span dir="rtl">تاریخِ اجرا</span></div>
            <div class="fw-semibold">{{ \App\Support\DateTimeHelper::formatDateTime($invoice->issued_at, 'd M Y, H:i') }}</div>
            <div>
                <span class="badge {{ $invoice->status === 'paid' ? 'bg-success' : ($invoice->status === 'voided' ? 'bg-dark' : 'bg-warning text-dark') }} text-uppercase">
                    {{ str_replace('_', ' ', $invoice->status) }}
                </span>
            </div>
        </div>
    </div>

    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered small mb-0">
            <thead class="table-light">
                <tr>
                    <th>Service / Description (<span dir="rtl">تفصیل</span>)</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Unit (PKR)</th>
                    <th class="text-end">Total (PKR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->description }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-end font-monospace">{{ number_format($item->unit_price_pkr, 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format($item->line_total_pkr, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Subtotal / <span dir="rtl">کل رقم</span></th>
                    <th class="text-end font-monospace">PKR {{ number_format($invoice->subtotal_pkr, 2) }}</th>
                </tr>
                <tr>
                    <th colspan="3" class="text-end">
                        Exemption / Waiver (<span dir="rtl">رعایت / استثنیٰ</span>)
                        @if($invoice->exemption_reason)
                            <span class="fw-normal text-muted">— {{ $invoice->exemption_reason }}</span>
                        @endif
                    </th>
                    <th class="text-end font-monospace text-success">- PKR {{ number_format($invoice->exemption_pkr, 2) }}</th>
                </tr>
                <tr class="table-light">
                    <th colspan="3" class="text-end">Net Payable / <span dir="rtl">قابلِ ادائیگی رقم</span></th>
                    <th class="text-end font-monospace fs-6">PKR {{ number_format($invoice->net_payable_pkr, 2) }}</th>
                </tr>
                <tr>
                    <th colspan="3" class="text-end">Amount Paid / <span dir="rtl">وصول شدہ رقم</span></th>
                    <th class="text-end font-monospace text-primary">PKR {{ number_format($invoice->paid_pkr, 2) }}</th>
                </tr>
                <tr>
                    <th colspan="3" class="text-end">Remaining Balance / <span dir="rtl">بقایا رقم</span></th>
                    <th class="text-end font-monospace {{ $invoice->balance_pkr > 0 ? 'text-danger' : 'text-success' }}">PKR {{ number_format($invoice->balance_pkr, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    @php
        $paymentEntries = $invoice->ledgerEntries->where('entry_type', 'payment');
    @endphp
    @if($paymentEntries->isNotEmpty())
        <div class="mb-3">
            <div class="small fw-bold mb-1">Immutable Payment Ledger Receipts / <span dir="rtl">تصدیق شدہ ادائیگی ریکارڈ</span></div>
            <ul class="small list-unstyled mb-0 border rounded p-2 bg-light">
                @foreach($paymentEntries as $entry)
                    <li class="d-flex justify-content-between">
                        <span><code class="fw-bold">{{ $entry->entry_number }}</code> ({{ strtoupper((string) $entry->payment_method) }}) — {{ $entry->recordedBy?->name }}</span>
                        <span class="font-monospace">PKR {{ number_format(abs($entry->amount_pkr), 2) }} &middot; {{ \App\Support\DateTimeHelper::formatDateTime($entry->recorded_at, 'd M Y, H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row pt-2 border-top small text-muted">
        <div class="col-6">
            Cashier: <strong>{{ $invoice->issuedBy?->name ?? 'Billing Desk' }}</strong>
        </div>
        <div class="col-6 text-end">
            Printed (PKT): {{ \App\Support\DateTimeHelper::nowDisplay() }}
        </div>
    </div>
</div>
@endsection
