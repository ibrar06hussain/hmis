@extends('layouts.app')

@section('title', __('hmis.nav.security_2fa'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">{{ __('hmis.auth.two_factor_title') }}</h1>
        <p class="text-muted mb-0">Offline RFC 6238 TOTP protection for privileged clinical and administrative accounts (zero internet dependency).</p>
    </div>
    <div>
        @if($user->hasTwoFactorEnabled())
            <span class="badge bg-success fs-6"><i class="bi bi-shield-check me-1"></i>2FA Enabled</span>
        @else
            <span class="badge bg-warning text-dark fs-6"><i class="bi bi-shield-exclamation me-1"></i>Not Enrolled</span>
        @endif
    </div>
</div>

@if($user->requiresPrivilegedTwoFactor())
    <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div>
            <strong>Privileged Role Policy:</strong> Your role ({{ str_replace('_', ' ', $user->getRoleNames()->first() ?? 'staff') }}) is classified as privileged under Cardiac Hospital Gilgit security policy.
            @if(config('hmis.two_factor_enforced'))
                Mandatory 2FA enforcement is currently <strong>ACTIVE</strong>.
            @else
                Enforce hospital-wide by setting <code>HMIS_2FA_ENFORCED=true</code> in production.
            @endif
        </div>
    </div>
@endif

@if(! empty($newRecoveryCodes))
    <div class="hmis-card p-4 mb-4 border-warning">
        <h2 class="h5 fw-bold text-warning-emphasis mb-2"><i class="bi bi-key-fill me-2"></i>Save Your One-Time Recovery Codes Now</h2>
        <p class="small text-muted mb-3">Each code can be used once if your authenticator device is lost or unavailable. These plaintext codes will not be displayed again.</p>
        <div class="row g-2">
            @foreach($newRecoveryCodes as $recoveryCode)
                <div class="col-6 col-md-3">
                    <div class="p-2 border rounded bg-light text-center hmis-mrn-badge">{{ $recoveryCode }}</div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="hmis-card p-4">
            @if(! $user->hasTwoFactorEnabled())
                <h2 class="h5 fw-bold mb-3">Step 1: Scan Local Offline QR Code or Enter Secret</h2>
                <p class="small text-muted">
                    Scan this locally rendered SVG QR code using any offline RFC 6238 authenticator app (e.g., Aegis, FreeOTP, Google Authenticator, or Microsoft Authenticator), or enter the Base32 secret key manually:
                </p>

                @if(! empty($qrSvg))
                    <div class="d-flex flex-column align-items-center p-3 mb-3 rounded bg-white border">
                        <div aria-label="TOTP Authenticator QR Code">{!! $qrSvg !!}</div>
                        <small class="text-muted mt-2"><i class="bi bi-hdd-network me-1 text-success"></i>Rendered locally as inline SVG (zero external QR API calls)</small>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-uppercase text-muted">Base32 Secret Key (Manual Entry)</label>
                    <div class="p-3 rounded bg-light border hmis-mrn-badge fs-5 text-break" dir="ltr">{{ $pendingSecret }}</div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold text-uppercase text-muted">Provisioning URI (otpauth://)</label>
                    <input type="text" class="form-control form-control-sm font-monospace" value="{{ $provisioningUri }}" readonly dir="ltr">
                </div>

                <h2 class="h5 fw-bold mb-3">Step 2: Confirm with 6-Digit Code</h2>
                @if($errors->has('code'))
                    <div class="alert alert-danger small">{{ $errors->first('code') }}</div>
                @endif
                <form method="POST" action="{{ route('security.two-factor.confirm') }}">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-7">
                            <label for="totp_code" class="form-label fw-semibold">Current 6-Digit Code</label>
                            <input id="totp_code" type="text" name="code" class="form-control hmis-mrn-badge" required autocomplete="one-time-code" placeholder="000000" dir="ltr">
                        </div>
                        <div class="col-sm-5">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-shield-check me-1"></i>Enable 2FA
                            </button>
                        </div>
                    </div>
                </form>
            @else
                <h2 class="h5 fw-bold mb-2">Two-Factor Authentication is Active</h2>
                <p class="small text-muted mb-3">
                    Confirmed at: <strong>{{ \App\Support\DateTimeHelper::formatDisplay($user->two_factor_confirmed_at) }}</strong>
                    ({{ count((array) ($user->two_factor_recovery_codes ?? [])) }} unused recovery codes remaining).
                </p>

                <hr>

                <h3 class="h6 fw-bold text-danger mb-2">Disable Two-Factor Authentication</h3>
                <p class="small text-muted">Enter your current account password to turn off 2FA. This action is recorded in the immutable audit log.</p>
                @if($errors->has('current_password'))
                    <div class="alert alert-danger small">{{ $errors->first('current_password') }}</div>
                @endif
                <form method="POST" action="{{ route('security.two-factor.disable') }}">
                    @csrf
                    @method('DELETE')
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-7">
                            <label for="current_password" class="form-label fw-semibold">Current Password</label>
                            <input id="current_password" type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="col-sm-5">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-shield-x me-1"></i>Disable 2FA
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="hmis-card p-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Hospital LAN Security Notes</h2>
            <ul class="small text-muted mb-0 ps-3 d-flex flex-column gap-2">
                <li><strong>100% Offline:</strong> TOTP codes are computed purely using RFC 6238 HMAC-SHA1 against the hospital LAN NTP clock — no SMS gateway or external API is required.</li>
                <li><strong>Encrypted Storage:</strong> TOTP secrets are stored encrypted at rest via Laravel's <code>APP_KEY</code>; recovery codes are hashed with bcrypt/argon2 and single-use.</li>
                <li><strong>Immutable Audit Trail:</strong> Enrollment, verification, recovery-code use, failed attempts, and disable actions are logged in <code>audit_logs</code>.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
