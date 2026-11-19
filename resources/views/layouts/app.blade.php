<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('hmis.facility_short_name')) — {{ config('hmis.facility_name') }}</title>
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
                    <small class="text-white-50">{{ config('hmis.facility_name') }}</small>
                </div>
            </div>
        </div>

        <nav class="nav flex-column gap-1 flex-grow-1" aria-label="Primary Clinical Navigation">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            @can('patient.view_demographics')
                <a href="{{ route('patients.index') }}" class="nav-link {{ request()->routeIs('patients.index', 'patients.show', 'patients.edit') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i> Master Patient Index
                </a>
            @endcan

            @can('patient.register')
                <a href="{{ route('patients.create', ['mode' => 'standard']) }}" class="nav-link {{ request()->routeIs('patients.create') && request('mode', 'standard') === 'standard' ? 'active' : '' }}">
                    <i class="bi bi-person-plus-fill"></i> Register Patient
                </a>
            @endcan

            @can('patient.register_emergency')
                <a href="{{ route('patients.create', ['mode' => 'emergency']) }}" class="nav-link {{ request()->routeIs('patients.create') && request('mode') === 'emergency' ? 'active' : '' }}">
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i> Emergency Intake
                </a>
            @endcan

            @can('patient.merge')
                <a href="{{ route('patients.merge.form') }}" class="nav-link {{ request()->routeIs('patients.merge.*') ? 'active' : '' }}">
                    <i class="bi bi-intersect"></i> Merge Patients
                </a>
            @endcan

            @can('viewAny', \App\Modules\Appointments\Models\Appointment::class)
                <a href="{{ route('opd.index') }}" class="nav-link {{ request()->routeIs('opd.*', 'encounters.*') ? 'active' : '' }}">
                    <i class="bi bi-calendar2-check-fill"></i> OPD &amp; Appointments
                </a>
            @endcan

            <hr class="border-light border-opacity-25 my-2">
            <div class="px-2 small text-uppercase text-white-50 fw-semibold">Upcoming Modules</div>
            <span class="nav-link disabled opacity-50"><i class="bi bi-hospital"></i> Wards &amp; IPD (Phase 2)</span>
            <span class="nav-link disabled opacity-50"><i class="bi bi-eyedropper"></i> Laboratory / LIS (Phase 3)</span>
            <span class="nav-link disabled opacity-50"><i class="bi bi-image"></i> Radiology / PACS (Phase 3/6)</span>
        </nav>

        <div class="pt-3 border-top border-light border-opacity-25 small text-white-50">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span><i class="bi bi-hdd-network me-1 text-success"></i> LAN Offline Ready</span>
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
                            <input id="hmis-global-patient-search" type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search MRN, CNIC, Phone, Name (/)">
                            <button class="btn btn-primary" type="submit">Find</button>
                        </div>
                    </form>
                @endcan
            </div>

            <div class="d-flex align-items-center gap-3">
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
                            <i class="bi bi-box-arrow-right me-1"></i>Sign Out
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
        <h5 class="offcanvas-title text-white">{{ config('hmis.facility_name') }}</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="nav flex-column gap-1">
            <a href="{{ route('dashboard') }}" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            @can('patient.view_demographics')
                <a href="{{ route('patients.index') }}" class="nav-link"><i class="bi bi-people-fill"></i> Master Patient Index</a>
            @endcan
            @can('patient.register')
                <a href="{{ route('patients.create', ['mode' => 'standard']) }}" class="nav-link"><i class="bi bi-person-plus-fill"></i> Register Patient</a>
            @endcan
            @can('patient.register_emergency')
                <a href="{{ route('patients.create', ['mode' => 'emergency']) }}" class="nav-link"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Emergency Intake</a>
            @endcan
            @can('patient.merge')
                <a href="{{ route('patients.merge.form') }}" class="nav-link"><i class="bi bi-intersect"></i> Merge Patients</a>
            @endcan
            @can('viewAny', \App\Modules\Appointments\Models\Appointment::class)
                <a href="{{ route('opd.index') }}" class="nav-link"><i class="bi bi-calendar2-check-fill"></i> OPD &amp; Appointments</a>
            @endcan
        </nav>
    </div>
</div>
</body>
</html>
