<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('hmis.facility_short_name')) — {{ __('hmis.facility_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="d-flex min-vh-100">
    {{-- Desktop Persistent Clinical Sidebar --}}
    <aside class="hmis-sidebar d-none d-lg-flex flex-column p-3 no-print">
        <div class="pb-3 mb-3 border-bottom border-light border-opacity-25">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger rounded-circle p-2"><i class="bi bi-heart-pulse-fill"></i></span>
                <div>
                    <div class="fw-bold text-white lh-sm">{{ config('hmis.facility_short_name') }}</div>
                    <small class="text-white-50">{{ __('hmis.facility_name') }}</small>
                </div>
            </div>
        </div>

        <nav class="nav flex-column gap-1 flex-grow-1" aria-label="Primary Clinical Navigation">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i> {{ __('hmis.nav.dashboard') }}
            </a>

            @can('patient.view_demographics')
                <a href="{{ route('patients.index') }}" class="nav-link {{ request()->routeIs('patients.index', 'patients.show', 'patients.edit') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i> {{ __('hmis.nav.mpi') }}
                </a>
            @endcan

            @can('patient.register')
                <a href="{{ route('patients.create', ['mode' => 'standard']) }}" class="nav-link {{ request()->routeIs('patients.create') && request('mode', 'standard') === 'standard' ? 'active' : '' }}">
                    <i class="bi bi-person-plus-fill"></i> {{ __('hmis.nav.register_patient') }}
                </a>
            @endcan

            @can('patient.register_emergency')
                <a href="{{ route('patients.create', ['mode' => 'emergency']) }}" class="nav-link {{ request()->routeIs('patients.create') && request('mode') === 'emergency' ? 'active' : '' }}">
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i> {{ __('hmis.nav.emergency_intake') }}
                </a>
            @endcan

            @can('patient.merge')
                <a href="{{ route('patients.merge.form') }}" class="nav-link {{ request()->routeIs('patients.merge.*') ? 'active' : '' }}">
                    <i class="bi bi-intersect"></i> {{ __('hmis.nav.merge_patients') }}
                </a>
            @endcan

            @can('viewAny', \App\Modules\Appointments\Models\Appointment::class)
                <a href="{{ route('opd.index') }}" class="nav-link {{ request()->routeIs('opd.index', 'encounters.*') ? 'active' : '' }}">
                    <i class="bi bi-calendar2-check-fill"></i> {{ __('hmis.nav.opd') }}
                </a>
            @endcan

            @can('emr.vitals.record')
                <a href="{{ route('opd.pre-consult.index') }}" class="nav-link {{ request()->routeIs('opd.pre-consult.*') ? 'active' : '' }}">
                    <i class="bi bi-heart-pulse text-warning"></i> {{ __('hmis.nav.pre_consult') }}
                </a>
            @endcan

            @can('emr.note.sign')
                <a href="{{ route('emr.cosign.index') }}" class="nav-link {{ request()->routeIs('emr.cosign.*') ? 'active' : '' }}">
                    <i class="bi bi-pen-fill text-info"></i> {{ __('hmis.nav.cosign_tray') }}
                </a>
            @endcan

            @can('emr.note.create')
                <a href="{{ route('emr.favorites.index') }}" class="nav-link {{ request()->routeIs('emr.favorites.*') ? 'active' : '' }}">
                    <i class="bi bi-bookmark-star-fill text-success"></i> {{ __('hmis.nav.order_sets') }}
                </a>
            @endcan

            @can('viewAny', \App\Modules\Emergency\Models\EmergencyTriage::class)
                <a href="{{ route('emergency.index') }}" class="nav-link {{ request()->routeIs('emergency.*') ? 'active' : '' }}">
                    <i class="bi bi-heart-pulse-fill text-danger"></i> {{ __('hmis.nav.emergency_triage') }}
                </a>
            @endcan

            @can('viewAny', \App\Modules\Ipd\Models\IpdAdmission::class)
                <a href="{{ route('ipd.index') }}" class="nav-link {{ request()->routeIs('ipd.*') ? 'active' : '' }}">
                    <i class="bi bi-hospital-fill"></i> {{ __('hmis.nav.ipd') }}
                </a>
            @endcan

            @can('viewWorkbench', \App\Modules\Nursing\Models\NursingMarRecord::class)
                <a href="{{ route('nursing.index') }}" class="nav-link {{ request()->routeIs('nursing.*') ? 'active' : '' }}">
                    <i class="bi bi-clipboard2-heart-fill"></i> {{ __('hmis.nav.nursing') }}
                </a>
                <a href="{{ route('icu.index') }}" class="nav-link {{ request()->routeIs('icu.*') ? 'active' : '' }}">
                    <i class="bi bi-activity text-info"></i> {{ __('hmis.nav.icu') }}
                </a>
            @endcan

            @can('viewWorkbench', \App\Modules\Cardiology\Models\CardiacProcedure::class)
                <a href="{{ route('cardiology.procedures.index') }}" class="nav-link {{ request()->routeIs('cardiology.procedures.*') ? 'active' : '' }}">
                    <i class="bi bi-heart-fill text-danger"></i> {{ __('hmis.nav.cardiac_procedures') }}
                </a>
            @endcan

            @can('viewWorkbench', \App\Modules\Lis\Models\LabOrder::class)
                <a href="{{ route('lis.index') }}" class="nav-link {{ request()->routeIs('lis.*') ? 'active' : '' }}">
                    <i class="bi bi-eyedropper"></i> {{ __('hmis.nav.lis') }}
                </a>
            @endcan

            @can('viewWorkbench', \App\Modules\Pacs\Models\ImagingStudy::class)
                <a href="{{ route('ris.index') }}" class="nav-link {{ request()->routeIs('ris.*') ? 'active' : '' }}">
                    <i class="bi bi-image-fill"></i> {{ __('hmis.nav.ris') }}
                </a>
            @endcan

            @can('viewWorkbench', \App\Modules\Pharmacy\Models\Prescription::class)
                <a href="{{ route('pharmacy.index') }}" class="nav-link {{ request()->routeIs('pharmacy.*') ? 'active' : '' }}">
                    <i class="bi bi-capsule-pill"></i> {{ __('hmis.nav.pharmacy') }}
                </a>
            @endcan

            @can('manage', \App\Modules\Billing\Models\Invoice::class)
                <a href="{{ route('billing.index') }}" class="nav-link {{ request()->routeIs('billing.*') ? 'active' : '' }}">
                    <i class="bi bi-receipt-cutoff"></i> {{ __('hmis.nav.billing') }}
                </a>
            @endcan

            @can('viewAny', \App\Modules\Hr\Models\DutyRoster::class)
                <a href="{{ route('rosters.index') }}" class="nav-link {{ request()->routeIs('rosters.*') ? 'active' : '' }}">
                    <i class="bi bi-calendar-week-fill"></i> {{ __('hmis.nav.rosters') }}
                </a>
            @endcan

            @can('viewExecutive', \App\Modules\Reporting\Services\HospitalReportingService::class)
                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <i class="bi bi-bar-chart-line-fill"></i> {{ __('hmis.nav.reports') }}
                </a>
            @endcan

            <hr class="border-light border-opacity-25 my-2">
            <div class="px-2 small text-uppercase text-white-50 fw-semibold">Governance &amp; Security</div>
            @if(auth()->user()?->can('audit.view_logs') || auth()->user()?->can('emr.note.sign'))
                <a href="{{ route('ai.index') }}" class="nav-link {{ request()->routeIs('ai.*') ? 'active' : '' }}">
                    <i class="bi bi-shield-lock-fill text-warning"></i> {{ __('hmis.nav.ai_governance') }} ({{ config('hmis.ai.enabled') ? 'Draft' : 'Off' }})
                </a>
            @endif
            @can('audit.view_logs')
                <a href="{{ route('admin.audit-logs.index') }}" class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                    <i class="bi bi-journal-text text-danger"></i> {{ __('hmis.nav.audit_logs') }}
                </a>
            @endcan
            <a href="{{ route('security.two-factor.show') }}" class="nav-link {{ request()->routeIs('security.two-factor.*') ? 'active' : '' }}">
                <i class="bi bi-key-fill {{ auth()->user()?->hasTwoFactorEnabled() ? 'text-success' : 'text-info' }}"></i> {{ __('hmis.nav.security_2fa') }}
            </a>
        </nav>

        <div class="pt-3 border-top border-light border-opacity-25 small text-white-50">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span><i class="bi bi-hdd-network me-1 text-success"></i> {{ __('hmis.status.offline_lan') }}</span>
                @if(config('hmis.demo_mode'))
                    <span class="badge bg-warning text-dark">DEMO</span>
                @endif
            </div>
            <div>Timezone: {{ config('hmis.display_timezone') }}</div>
        </div>
    </aside>

    {{-- Main Workspace --}}
    <div class="flex-grow-1 d-flex flex-column min-w-0">
        <header class="hmis-topbar px-3 py-2 d-flex align-items-center justify-content-between gap-3 no-print">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#hmisMobileNav" aria-label="Toggle Menu">
                    <i class="bi bi-list"></i>
                </button>
                @can('patient.view_demographics')
                    <form action="{{ route('patients.index') }}" method="GET" class="d-flex align-items-center" role="search">
                        <div class="input-group input-group-sm" style="min-width: 260px;">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input id="hmis-global-patient-search" type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('hmis.actions.search_placeholder') }}">
                            <button class="btn btn-primary" type="submit">{{ __('hmis.actions.find') }}</button>
                        </div>
                    </form>
                @endcan
            </div>

            <div class="d-flex align-items-center gap-3">
                <form method="POST" action="{{ route('locale.switch') }}" class="d-inline-flex gap-1" aria-label="Language Switcher">
                    @csrf
                    <button type="submit" name="locale" value="en" class="btn btn-sm {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">EN</button>
                    <button type="submit" name="locale" value="ur" class="btn btn-sm {{ app()->getLocale() === 'ur' ? 'btn-primary' : 'btn-outline-secondary' }}">اردو</button>
                </form>
                <span class="d-none d-md-inline small text-muted">
                    <i class="bi bi-clock me-1"></i>{{ \App\Support\DateTimeHelper::nowDisplay() }}
                </span>
                @auth
                    <div class="text-end d-none d-sm-block">
                        <div class="fw-semibold lh-sm">{{ auth()->user()->name }}</div>
                        <small class="text-muted text-uppercase">{{ str_replace('_', ' ', auth()->user()->getRoleNames()->first() ?? 'staff') }}</small>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-box-arrow-right me-1"></i>{{ __('hmis.actions.sign_out') }}
                        </button>
                    </form>
                @endauth
            </div>
        </header>

        <main class="p-3 p-lg-4 flex-grow-1">
            @if(session('status'))
                <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show no-print" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

{{-- Tablet/Mobile Offcanvas Navigation --}}
<div class="offcanvas offcanvas-start hmis-sidebar text-white no-print" tabindex="-1" id="hmisMobileNav">
    <div class="offcanvas-header border-bottom border-light border-opacity-25">
        <h5 class="offcanvas-title text-white">{{ __('hmis.facility_name') }}</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="nav flex-column gap-1">
            <a href="{{ route('dashboard') }}" class="nav-link"><i class="bi bi-speedometer2"></i> {{ __('hmis.nav.dashboard') }}</a>
            @can('patient.view_demographics')
                <a href="{{ route('patients.index') }}" class="nav-link"><i class="bi bi-people-fill"></i> {{ __('hmis.nav.mpi') }}</a>
            @endcan
            @can('patient.register')
                <a href="{{ route('patients.create', ['mode' => 'standard']) }}" class="nav-link"><i class="bi bi-person-plus-fill"></i> {{ __('hmis.nav.register_patient') }}</a>
            @endcan
            @can('patient.register_emergency')
                <a href="{{ route('patients.create', ['mode' => 'emergency']) }}" class="nav-link"><i class="bi bi-exclamation-triangle-fill text-warning"></i> {{ __('hmis.nav.emergency_intake') }}</a>
            @endcan
            @can('patient.merge')
                <a href="{{ route('patients.merge.form') }}" class="nav-link"><i class="bi bi-intersect"></i> {{ __('hmis.nav.merge_patients') }}</a>
            @endcan
            @can('viewAny', \App\Modules\Appointments\Models\Appointment::class)
                <a href="{{ route('opd.index') }}" class="nav-link"><i class="bi bi-calendar2-check-fill"></i> {{ __('hmis.nav.opd') }}</a>
            @endcan
            @can('viewAny', \App\Modules\Emergency\Models\EmergencyTriage::class)
                <a href="{{ route('emergency.index') }}" class="nav-link"><i class="bi bi-heart-pulse-fill text-danger"></i> {{ __('hmis.nav.emergency_triage') }}</a>
            @endcan
            @can('viewAny', \App\Modules\Ipd\Models\IpdAdmission::class)
                <a href="{{ route('ipd.index') }}" class="nav-link"><i class="bi bi-hospital-fill"></i> {{ __('hmis.nav.ipd') }}</a>
            @endcan
            @can('audit.view_logs')
                <a href="{{ route('admin.audit-logs.index') }}" class="nav-link"><i class="bi bi-journal-text text-danger"></i> {{ __('hmis.nav.audit_logs') }}</a>
            @endcan
            <a href="{{ route('security.two-factor.show') }}" class="nav-link"><i class="bi bi-key-fill text-info"></i> {{ __('hmis.nav.security_2fa') }}</a>
        </nav>
    </div>
</div>
</body>
</html>
