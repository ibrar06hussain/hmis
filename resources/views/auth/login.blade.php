<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('hmis.actions.sign_in') }} — {{ __('hmis.facility_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-3" style="background: linear-gradient(135deg, #071930 0%, #0b2545 60%, #0f766e 100%);">
<div class="card shadow-lg border-0" style="max-width: 460px; width: 100%; border-radius: 0.85rem;">
    <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-end mb-2">
            <form method="POST" action="{{ route('locale.switch') }}" class="d-inline-flex gap-1" aria-label="Language Switcher">
                @csrf
                <button type="submit" name="locale" value="en" class="btn btn-sm {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">EN</button>
                <button type="submit" name="locale" value="ur" class="btn btn-sm {{ app()->getLocale() === 'ur' ? 'btn-primary' : 'btn-outline-secondary' }}">اردو</button>
            </form>
        </div>

        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 text-danger mb-3" style="width: 56px; height: 56px;">
                <i class="bi bi-heart-pulse-fill fs-3"></i>
            </div>
            <h1 class="h4 fw-bold mb-1">{{ __('hmis.facility_name') }}</h1>
            <p class="text-muted small mb-0">{{ __('hmis.subtitle') }}</p>
        </div>

        @if(session('status'))
            <div class="alert alert-success small">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger small mb-3">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label for="login" class="form-label fw-semibold">{{ __('hmis.auth.username_or_email') }}</label>
                <input id="login" type="text" name="login" value="{{ old('login') }}" class="form-control form-control-lg" required autofocus autocomplete="username" placeholder="e.g. reception or consultant">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">{{ __('hmis.auth.password') }}</label>
                <input id="password" type="password" name="password" class="form-control form-control-lg" required autocomplete="current-password">
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small" for="remember">{{ __('hmis.auth.remember') }}</label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">
                <i class="bi bi-shield-lock me-2"></i>{{ __('hmis.actions.sign_in') }}
            </button>
        </form>

        @if(config('hmis.demo_mode'))
            <div class="mt-4 p-3 rounded bg-light border small">
                <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle me-1"></i>Synthetic Demo Accounts</div>
                <div class="text-muted mb-1">Password: <code>DemoHmis@2026!</code></div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-secondary">reception</span>
                    <span class="badge bg-secondary">consultant</span>
                    <span class="badge bg-secondary">mo_er</span>
                    <span class="badge bg-secondary">nurse_ccu</span>
                    <span class="badge bg-secondary">ms</span>
                    <span class="badge bg-secondary">admin</span>
                    <span class="badge bg-secondary">it_admin</span>
                </div>
            </div>
        @endif

        <div class="text-center text-muted small mt-4">
            {{ __('hmis.auth.audit_notice') }}
        </div>
    </div>
</div>
</body>
</html>
