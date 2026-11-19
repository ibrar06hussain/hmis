@extends('layouts.app')

@section('title', 'Hospital Pharmacy & Dispensing')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-capsule-pill me-2 text-primary"></i>Hospital Pharmacy &amp; Dispensing Workbench</h1>
        <small class="text-muted">Atomic stock deduction, high-alert cardiac medication warnings, and allergy-override audit flags</small>
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
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Active &amp; Recent Prescriptions ({{ $prescriptions->count() }})</h2>
            @forelse($prescriptions as $rx)
                <div class="border rounded p-3 mb-3 {{ $rx->allergy_override_used ? 'border-danger bg-danger-subtle' : '' }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <span class="font-monospace fw-bold">{{ $rx->prescription_number }}</span>
                            <span class="mx-1">&middot;</span>
                            <a href="{{ route('patients.show', $rx->patient) }}" class="fw-semibold text-decoration-none">{{ $rx->patient->full_name }}</a>
                            <span class="badge bg-light text-dark border hmis-mrn-badge ms-1">{{ $rx->patient->mrn }}</span>
                            @if($rx->allergy_override_used)
                                <span class="badge bg-danger ms-1">ALLERGY OVERRIDE</span>
                            @endif
                        </div>
                        <div class="small text-muted">
                            Dr. {{ $rx->prescribedBy?->name }} &middot; {{ \App\Support\DateTimeHelper::formatDateTime($rx->prescribed_at, 'd M, H:i') }}
                            <span class="badge {{ $rx->status === 'dispensed' ? 'bg-success' : 'bg-info text-dark' }} ms-1 text-uppercase">{{ str_replace('_', ' ', $rx->status) }}</span>
                        </div>
                    </div>

                    @if($rx->allergy_override_reason)
                        <div class="alert alert-danger py-1 px-2 small mb-2">
                            <strong>Prescriber Allergy Override Justification:</strong> {{ $rx->allergy_override_reason }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 small bg-white">
                            <thead class="table-light">
                                <tr><th>Medication</th><th>Regimen</th><th>Qty</th><th>Stock</th><th>Status</th><th class="text-end">Dispense</th></tr>
                            </thead>
                            <tbody>
                                @foreach($rx->items as $item)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">{{ $item->medication->generic_name }} {{ $item->medication->strength }}</span>
                                            @if($item->medication->is_high_alert)
                                                <span class="badge bg-danger ms-1">HIGH ALERT</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->dose }} {{ $item->route }} {{ $item->frequency }} &times; {{ $item->duration_days }}d</td>
                                        <td>{{ $item->quantity_prescribed }}</td>
                                        <td>
                                            <span class="badge {{ $item->medication->isLowStock() ? 'bg-warning text-dark' : 'bg-light text-dark border' }}">
                                                {{ $item->medication->stock_quantity }} in stock
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $item->status === 'dispensed' ? 'bg-success' : 'bg-secondary' }} text-uppercase">{{ $item->status }}</span>
                                        </td>
                                        <td class="text-end">
                                            @if($item->status === 'pending')
                                                @can('dispense', \App\Modules\Pharmacy\Models\Prescription::class)
                                                    <form method="POST" action="{{ route('pharmacy.items.dispense', $item) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success fw-semibold">Dispense</button>
                                                    </form>
                                                @endcan
                                            @else
                                                <span class="text-muted">{{ $item->dispensedBy?->name }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-4">No prescriptions issued yet.</div>
            @endforelse
        </div>
    </div>

    <div class="col-xl-4">
        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Cardiac Formulary Stock</h2>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                        <tr><th>Drug</th><th>Strength</th><th class="text-end">Stock</th></tr>
                    </thead>
                    <tbody>
                        @foreach($medications as $med)
                            <tr class="{{ $med->isLowStock() ? 'table-warning' : '' }}">
                                <td>
                                    <div class="fw-semibold">{{ $med->generic_name }}</div>
                                    <small class="text-muted">{{ $med->brand_name }} @if($med->is_high_alert)<span class="text-danger fw-bold">&middot; HIGH ALERT</span>@endif</small>
                                </td>
                                <td>{{ $med->strength }}</td>
                                <td class="text-end fw-bold font-monospace">{{ $med->stock_quantity }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
