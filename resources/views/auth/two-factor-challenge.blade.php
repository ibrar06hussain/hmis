<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('hmis.auth.two_factor_title') }} — {{ __('hmis.facility_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-3" style="background: linear-gradient(135deg, #071930 0%, #0b2545 60%, #0f766e 100%);">
<div class="card shadow-lg border-0" style="max-width: 460px; width: 100%; border-radius: 0.85rem;">
    <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-end mb-2">
            <form method="POST" action="{{ route('locale.switch') }}" class="d-inline-flex gap-1">
                @csrf
                <button type="submit" name="locale" value="en" class="btn btn-sm {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">EN</button>
                <button type="submit" name="locale" value="ur" class="btn btn-sm {{ app()->getLocale() === 'ur' ? 'btn-primary' : 'btn-outline-secondary' }}">اردو</button>
            </form>
        </div>

        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary mb-3" style="width: 56px; height: 56px;">
                <i class="bi bi-shield-lock-fill fs-3"></i>
            </div>
            <h1 class="h4 fw-bold mb-1">{{ __('hmis.auth.two_factor_title') }}</h1>
            <p class="text-muted small mb-0">{{ __('hmis.auth.two_factor_prompt') }}</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger small mb-3">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.verify') }}" novalidate>
            @csrf
            <div class="mb-4">
                <label for="code" class="form-label fw-semibold">6-Digit Authenticator or Recovery Code</label>
                <input id="code" type="text" name="code" class="form-control form-control-lg text-center hmis-mrn-badge" required autofocus autocomplete="one-time-code" placeholder="123456 or ABCD-EFGH">
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">
                <i class="bi bi-check-circle me-2"></i>{{ __('hmis.auth.verify_code') }}
            </button>
        </form>

        <div class="text-center mt-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-link btn-sm text-muted text-decoration-none">
                    <i class="bi bi-box-arrow-right me-1"></i>{{ __('hmis.actions.sign_out') }}
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
