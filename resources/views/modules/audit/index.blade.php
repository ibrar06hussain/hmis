@extends('layouts.app')

@section('title', __('hmis.nav.audit_logs'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Immutable Audit Log Viewer &amp; Break-Glass Oversight</h1>
        <small class="text-muted">Governance console for Medical Superintendent, Hospital Director, Administrator &amp; IT Administrator (append-only audit trail)</small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Security KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="hmis-card p-3">
            <div class="small text-muted text-uppercase">Audit Events (24h)</div>
            <div class="fs-3 fw-bold">{{ number_format($metrics['events_24h']) }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="hmis-card p-3">
            <div class="small text-muted text-uppercase">Auth / 2FA Failures (7d)</div>
            <div class="fs-3 fw-bold {{ $metrics['auth_failures_7d'] > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($metrics['auth_failures_7d']) }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="hmis-card p-3 border-warning">
            <div class="small text-muted text-uppercase">Active Break-Glass Grants</div>
            <div class="fs-3 fw-bold {{ $metrics['active_break_glass'] > 0 ? 'text-warning-emphasis' : 'text-success' }}">{{ number_format($metrics['active_break_glass']) }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="hmis-card p-3">
            <div class="small text-muted text-uppercase">Total Break-Glass History</div>
            <div class="fs-3 fw-bold">{{ number_format($metrics['total_break_glass']) }}</div>
        </div>
    </div>
</div>

{{-- Break-Glass Emergency Override Oversight --}}
<div class="hmis-card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
        <h2 class="h6 fw-bold mb-0"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Break-Glass Emergency Access Oversight</h2>
        <span class="badge {{ $activeBreakGlass->isNotEmpty() ? 'bg-danger' : 'bg-success' }}">{{ $activeBreakGlass->count() }} Active Now</span>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Status</th>
                    <th>Clinician</th>
                    <th>Patient MRN</th>
                    <th>Reason &amp; Justification</th>
                    <th>Granted (PKT)</th>
                    <th>Expires (PKT)</th>
                    <th class="text-end">Governance Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentBreakGlass as $bg)
                    @php $active = $bg->isActive(); @endphp
                    <tr class="{{ $active ? 'table-warning' : '' }}">
                        <td>
                            <span class="badge {{ $active ? 'bg-danger' : 'bg-secondary' }}">{{ $active ? 'ACTIVE' : 'EXPIRED / ENDED' }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $bg->user?->name ?? 'Unknown' }}</div>
                            <div class="text-muted font-monospace">{{ $bg->user?->username }} &middot; {{ $bg->ip_address ?? 'n/a' }}</div>
                        </td>
                        <td class="hmis-mrn-badge">{{ $bg->patient?->mrn ?? ('#'.$bg->patient_id) }}</td>
                        <td>
                            <span class="badge bg-light text-dark border text-uppercase">{{ str_replace('_', ' ', $bg->reason_code) }}</span>
                            <div class="text-muted mt-1">{{ $bg->justification }}</div>
                        </td>
                        <td>{{ \App\Support\DateTimeHelper::formatDateTime($bg->granted_at, 'd M Y, H:i') }}</td>
                        <td>{{ \App\Support\DateTimeHelper::formatDateTime($bg->expires_at, 'd M Y, H:i') }}</td>
                        <td class="text-end">
                            @if($active)
                                <form method="POST" action="{{ route('admin.break-glass.revoke', $bg) }}" class="d-inline-flex gap-1 align-items-center">
                                    @csrf
                                    <input type="text" name="reason" class="form-control form-control-sm" style="width: 190px;" required minlength="5" placeholder="Revocation reason...">
                                    <button type="submit" class="btn btn-sm btn-danger text-nowrap">Revoke Now</button>
                                </form>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">No break-glass overrides recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Immutable Audit Log Filter & Table --}}
<div class="hmis-card p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-3">
        <h2 class="h6 fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Immutable System Audit Trail</h2>
        <div class="d-flex flex-wrap gap-1">
            @php
                $presets = [
                    '' => 'All Events',
                    'break_glass' => 'Break-Glass',
                    'auth_2fa' => 'Auth & 2FA',
                    'patient_merge' => 'Patient Merge',
                    'billing' => 'Billing & Void',
                    'ai' => 'AI Governance',
                ];
            @endphp
            @foreach($presets as $key => $label)
                <a href="{{ route('admin.audit-logs.index', array_filter(array_merge($filters, ['preset' => $key]))) }}"
                   class="btn btn-xs btn-sm {{ $filters['preset'] === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="preset" value="{{ $filters['preset'] }}">
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Action Contains</label>
            <input type="text" name="action" value="{{ $filters['action'] }}" class="form-control form-control-sm" placeholder="e.g. security.2fa">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Actor Role</label>
            <select name="actor_role" class="form-select form-select-sm">
                <option value="">All Roles</option>
                @foreach($availableRoles as $role)
                    <option value="{{ $role }}" @selected($filters['actor_role'] === $role)>{{ str_replace('_', ' ', $role) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Staff User</label>
            <input type="text" name="actor_q" value="{{ $filters['actor_q'] }}" class="form-control form-control-sm" placeholder="Name or username">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Patient MRN</label>
            <input type="text" name="mrn" value="{{ $filters['mrn'] }}" class="form-control form-control-sm" placeholder="CHG-2026-...">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">From Date (PKT)</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Time (PKT)</th>
                    <th>Action</th>
                    <th>Actor &amp; Role</th>
                    <th>Patient MRN</th>
                    <th>Subject</th>
                    <th>Reason / Metadata</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auditLogs as $log)
                    <tr>
                        <td class="text-nowrap">{{ \App\Support\DateTimeHelper::formatDateTime($log->occurred_at, 'd M Y, H:i:s') }}</td>
                        <td><code class="fw-semibold">{{ $log->action }}</code></td>
                        <td>
                            <div class="fw-semibold">{{ $log->actor?->name ?? 'System / Guest' }}</div>
                            @if($log->actor_role)
                                <span class="badge bg-light text-dark border text-uppercase">{{ str_replace('_', ' ', $log->actor_role) }}</span>
                            @endif
                        </td>
                        <td class="hmis-mrn-badge">{{ $log->patient?->mrn ?? '—' }}</td>
                        <td class="font-monospace text-muted">
                            @if($log->subject_type)
                                {{ class_basename($log->subject_type) }}#{{ $log->subject_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($log->reason)
                                <div>{{ $log->reason }}</div>
                            @endif
                            @if(! empty($log->new_values))
                                <div class="text-muted font-monospace" style="font-size: 0.75rem;">
                                    {{ \Illuminate\Support\Str::limit(json_encode($log->new_values, JSON_UNESCAPED_SLASHES), 90) }}
                                </div>
                            @endif
                        </td>
                        <td class="font-monospace text-muted">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No audit events match the active filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($auditLogs->hasPages())
        <div class="mt-3">
            {{ $auditLogs->links() }}
        </div>
    @endif
</div>
@endsection
