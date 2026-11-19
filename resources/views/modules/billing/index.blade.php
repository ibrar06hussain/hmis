@extends('layouts.app')

@section('title', 'Billing, Exemptions & Financial Ledger')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Hospital Billing, Government Exemptions &amp; Immutable Ledger</h1>
        <small class="text-muted">Supports General, Sehat Card, Zakat, Government Employee, and Indigent waivers with append-only audit receipts</small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-8">
        <div class="hmis-card p-3 mb-4">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Issued Invoices ({{ $invoices->count() }})</h2>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice #</th>
                            <th>Patient</th>
                            <th>Category</th>
                            <th class="text-end">Net (PKR)</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                            <th class="text-end">Cashier Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $inv)
                            <tr>
                                <td class="font-monospace fw-semibold">{{ $inv->invoice_number }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $inv->patient->full_name }}</div>
                                    <span class="text-muted hmis-mrn-badge">{{ $inv->patient->mrn }}</span>
                                </td>
                                <td><span class="badge bg-light text-dark border text-uppercase">{{ str_replace('_', ' ', $inv->billing_category) }}</span></td>
                                <td class="text-end font-monospace">{{ number_format($inv->net_payable_pkr, 2) }}</td>
                                <td class="text-end font-monospace fw-bold {{ $inv->balance_pkr > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($inv->balance_pkr, 2) }}</td>
                                <td>
                                    <span class="badge {{ $inv->status === 'paid' ? 'bg-success' : ($inv->status === 'voided' ? 'bg-dark' : 'bg-warning text-dark') }} text-uppercase">
                                        {{ str_replace('_', ' ', $inv->status) }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @if(in_array($inv->status, ['issued', 'partially_paid'], true))
                                        <form method="POST" action="{{ route('billing.invoices.pay', $inv) }}" class="d-inline-flex gap-1 align-items-center">
                                            @csrf
                                            <input type="number" step="0.01" name="amount_pkr" value="{{ $inv->balance_pkr }}" max="{{ $inv->balance_pkr }}" class="form-control form-control-sm" style="width: 95px;" required>
                                            <select name="payment_method" class="form-select form-select-sm" style="width: 95px;">
                                                <option value="cash">Cash</option>
                                                <option value="card">Card</option>
                                                <option value="bank_transfer">Bank</option>
                                                <option value="sehat_card">Sehat Card</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-success">Pay</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No invoices issued yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-journal-check me-2 text-success"></i>Append-Only Financial Ledger (Immutable)</h2>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                        <tr><th>Entry #</th><th>Type</th><th>Invoice</th><th>Method</th><th class="text-end">Amount (PKR)</th><th>Recorded (PKT)</th></tr>
                    </thead>
                    <tbody>
                        @forelse($ledgerEntries as $entry)
                            <tr>
                                <td class="font-monospace">{{ $entry->entry_number }}</td>
                                <td><span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $entry->entry_type) }}</span></td>
                                <td class="font-monospace">{{ $entry->invoice?->invoice_number }}</td>
                                <td class="text-uppercase">{{ $entry->payment_method ?? '—' }}</td>
                                <td class="text-end font-monospace fw-semibold">{{ number_format($entry->amount_pkr, 2) }}</td>
                                <td>{{ \App\Support\DateTimeHelper::formatDateTime($entry->recorded_at, 'd M, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No ledger entries recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-plus-circle me-1 text-primary"></i>Issue New Hospital Invoice</h2>
            <form method="POST" action="{{ route('billing.invoices.store') }}">
                @csrf
                <div class="mb-3">
                    <label for="patient_id" class="form-label small fw-semibold">Patient <span class="text-danger">*</span></label>
                    <select id="patient_id" name="patient_id" class="form-select" required>
                        <option value="">Select patient...</option>
                        @foreach($activePatients as $p)
                            <option value="{{ $p->id }}">{{ $p->mrn }} — {{ $p->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="tariff_id" class="form-label small fw-semibold">Service / Procedure Tariff <span class="text-danger">*</span></label>
                    <select id="tariff_id" name="tariff_id" class="form-select" required>
                        @foreach($tariffs as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} (PKR {{ number_format($t->unit_price_pkr, 0) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label for="quantity" class="form-label small fw-semibold">Qty</label>
                        <input type="number" min="1" max="100" id="quantity" name="quantity" value="1" class="form-control" required>
                    </div>
                    <div class="col-8">
                        <label for="billing_category" class="form-label small fw-semibold">Entitlement Category</label>
                        <select id="billing_category" name="billing_category" class="form-select" required>
                            <option value="general">General (Cash/Card)</option>
                            <option value="sehat_card">Sehat Sahulat Card</option>
                            <option value="zakat">Zakat Fund</option>
                            <option value="govt_employee">Government Employee</option>
                            <option value="indigent_waiver">Indigent / Poor Patient Waiver</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label for="exemption_pkr" class="form-label small fw-semibold">Exemption / Waiver Amount (PKR)</label>
                    <input type="number" step="0.01" min="0" id="exemption_pkr" name="exemption_pkr" value="0" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="exemption_reason" class="form-label small fw-semibold">Exemption / Approval Reference</label>
                    <input type="text" id="exemption_reason" name="exemption_reason" class="form-control" placeholder="e.g., Sehat Card # / MS Waiver Approval">
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary fw-semibold btn-touch">Issue Audited Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
