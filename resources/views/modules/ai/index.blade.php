@extends('layouts.app')

@section('title', 'Phase 7 AI Assistant Governance & Audit')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-shield-lock-fill me-2 text-warning"></i>Phase 7 AI Assistant Governance &amp; Safety Controls</h1>
        <small class="text-muted">Disabled by default (<code>HMIS_AI_ENABLED=false</code>); strictly human-approved drafts only; prompt-injection protected &amp; fully audited</small>
    </div>
    <span class="badge {{ $aiEnabled ? 'bg-warning text-dark' : 'bg-dark' }} fs-6">
        AI Kill Switch: {{ $aiEnabled ? 'ENABLED (DRAFT-ONLY MODE)' : 'DISABLED (SAFE DEFAULT)' }}
    </span>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if($generatedDraft)
    <div class="alert alert-warning border-warning shadow-sm mb-3">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Generated Draft Output (Requires Authorized Clinician Review)</div>
        <div class="font-monospace small">{{ $generatedDraft }}</div>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="hmis-card p-3 h-100">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-2 text-success">Allow-Listed Read / Draft Tools</h2>
            <ul class="small mb-3">
                @foreach($allowedTools as $tool)
                    <li><code>{{ $tool }}</code></li>
                @endforeach
            </ul>
            <h3 class="h6 fw-bold border-bottom pb-2 mb-2 text-danger">Hard-Prohibited Autonomous Clinical Actions</h3>
            <ul class="small mb-0 text-danger">
                @foreach($prohibitedActions as $action)
                    <li><code>{{ $action }}</code> — Always Blocked &amp; Audited</li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="hmis-card p-3 h-100">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Test Governed Draft Tool (Provider: <code>{{ $providerName }}</code>)</h2>
            <form method="POST" action="{{ route('ai.draft') }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label small fw-semibold" for="tool_name">Draft Tool</label>
                    <select id="tool_name" name="tool_name" class="form-select form-select-sm" required>
                        @foreach($allowedTools as $tool)
                            <option value="{{ $tool }}">{{ $tool }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold" for="patient_id">Scoped Patient Context</label>
                    <select id="patient_id" name="patient_id" class="form-select form-select-sm">
                        <option value="">None</option>
                        @foreach($patients as $p)
                            <option value="{{ $p->id }}">{{ $p->mrn }} — {{ $p->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="additional_prompt">Optional Clinician Prompt</label>
                    <input type="text" id="additional_prompt" name="additional_prompt" class="form-control form-control-sm" placeholder="e.g., Prepare brief follow-up summary outline">
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-sm btn-warning fw-semibold">Run Through AI Governance Gate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="hmis-card p-3">
    <h2 class="h6 fw-bold border-bottom pb-2 mb-2">Immutable AI Interaction &amp; Block Audit Log ({{ $logs->count() }})</h2>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small">
            <thead class="table-light">
                <tr><th>Time (PKT)</th><th>Actor</th><th>Tool</th><th>Provider</th><th>Outcome</th><th>Latency</th></tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ \App\Support\DateTimeHelper::formatDateTime($log->occurred_at, 'd M, H:i:s') }}</td>
                        <td>{{ $log->user?->name }}</td>
                        <td class="font-monospace">{{ $log->tool_name }}</td>
                        <td class="font-monospace">{{ $log->model_provider }}</td>
                        <td>
                            <span class="badge {{ str_starts_with($log->status, 'blocked') ? 'bg-danger' : 'bg-warning text-dark' }} text-uppercase">
                                {{ str_replace('_', ' ', $log->status) }}
                            </span>
                        </td>
                        <td>{{ $log->latency_ms }} ms</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No AI invocations recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
