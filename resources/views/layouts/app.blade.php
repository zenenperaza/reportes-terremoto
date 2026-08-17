<!doctype html>
<html lang="es" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none">
<head>
    @php($asonacopIconUrl = asset('icons/asonacop-app.png').'?v='.(@filemtime(public_path('icons/asonacop-app.png')) ?: 1))
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#004b87">
    <meta name="application-name" content="Respuesta ASONACOP Venezuela">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ASONACOP">
    <title>@yield('title', 'Respuesta ASONACOP Venezuela')</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ $asonacopIconUrl }}">
    <link rel="shortcut icon" href="{{ $asonacopIconUrl }}">
    @auth
        <script src="{{ asset('assets/js/layout.js') }}"></script>
        <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/icons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/libs/simplebar/simplebar.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/libs/node-waves/waves.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/app.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/custom.min.css') }}">
    @else
        <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    @endauth
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/navigation-fixes.css') }}">
    <link rel="stylesheet" href="{{ asset('css/geolocation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/report-form-fixes.css') }}">
    <link rel="stylesheet" href="{{ asset('css/beneficiary-records.css') }}">
    <link rel="stylesheet" href="{{ asset('css/recurrence-alert.css') }}">
    <link rel="stylesheet" href="{{ asset('css/beneficiary-immediate.css') }}">
    <link rel="stylesheet" href="{{ asset('css/beneficiary-entry.css') }}">
    <link rel="stylesheet" href="{{ asset('css/user-management.css') }}">
    <link rel="stylesheet" href="{{ asset('css/beneficiary-summary.css') }}">
    <link rel="stylesheet" href="{{ asset('css/donor-report.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pwa.css') }}">
    <link rel="stylesheet" href="{{ asset('css/catalog-management.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/select2-custom.css') }}">
    <link rel="stylesheet" href="{{ asset('css/indicator-select2.css') }}">
    @auth<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">@endauth
    @stack('styles')
</head>
<body class="{{ auth()->check() ? 'admin-layout' : 'guest-layout' }}">
@auth
<div id="layout-wrapper">
    <header id="page-topbar">
        <div class="layout-width"><div class="navbar-header">
            <div class="d-flex align-items-center">
                <div class="navbar-brand-box horizontal-logo">
                    <a href="{{ route('dashboard') }}" class="logo logo-dark"><span class="logo-sm"><img src="{{ $asonacopIconUrl }}" alt="ASONACOP" height="34"></span><span class="logo-lg"></span></a>
                </div>
                <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger" id="topnav-hamburger-icon" aria-label="Abrir o cerrar men&uacute;"><span class="hamburger-icon" aria-hidden="true"><span></span><span></span><span></span></span><i class="ri-arrow-right-line menu-closed-icon" aria-hidden="true"></i></button>
                <div class="app-context d-none d-md-block"><span class="app-context-title">Respuesta al terremoto</span><small>Venezuela</small></div>
            </div>
            <div class="d-flex align-items-center gap-1">
                <a class="btn btn-primary d-none d-sm-inline-flex align-items-center" href="{{ route('reports.create') }}"><i class="ri-add-line me-1"></i> Nuevo registro</a>
                <div class="dropdown ms-1 header-item topbar-user">
                    <button type="button" class="btn" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="d-flex align-items-center"><span class="avatar-sm"><span class="avatar-title rounded-circle bg-primary-subtle text-primary"><i class="ri-user-3-line fs-20"></i></span></span><span class="text-start ms-xl-2 d-none d-xl-block"><span class="d-block fw-semibold user-name-text">{{ auth()->user()->name }}</span><span class="d-block fs-12 text-muted user-name-sub-text">{{ \App\Models\User::roleLabels()[auth()->user()->role] ?? auth()->user()->role }}</span></span></span></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Hola, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h6>
                        <a class="dropdown-item" href="{{ route('profile.show') }}"><i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i> Mi perfil</a>
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('logout') }}" method="post">@csrf<button class="dropdown-item" type="submit"><i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> Salir</button></form>
                    </div>
                </div>
            </div>
        </div></div>
    </header>

    <div class="app-menu navbar-menu">
        <div class="navbar-brand-box">
            <a href="{{ route('dashboard') }}" class="logo logo-light sidebar-brand"><span class="logo-sm"><img src="{{ $asonacopIconUrl }}" alt="ASONACOP" height="38"></span><span class="logo-lg"><img src="{{ $asonacopIconUrl }}" alt="ASONACOP" height="42"><span><strong>ASONACOP</strong><small>Respuesta Venezuela</small></span></span></a>
            <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover" aria-label="Contraer men&uacute;"><i class="ri-record-circle-line"></i></button>
        </div>
        <div id="scrollbar"><div class="container-fluid"><ul class="navbar-nav" id="navbar-nav">
            <li class="menu-title"><span>Principal</span></li>
            <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="ri-dashboard-2-line"></i><span>Panel</span></a></li>
            <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('reports.create') ? 'active' : '' }}" href="{{ route('reports.create') }}"><i class="ri-add-circle-line"></i><span>Nuevo registro</span></a></li>
            <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('reports.index', 'reports.show', 'reports.edit') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i class="ri-file-list-3-line"></i><span>Registros</span></a></li>
            <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('beneficiaries.summary') ? 'active' : '' }}" href="{{ route('beneficiaries.summary') }}"><i class="ri-group-line"></i><span>Informe de beneficiarios</span></a></li>
            @if(auth()->user()->isAdministrator())
                @php($catalogOpen = request()->routeIs('users.*', 'place-names.*', 'donantes.*', 'proyectos.*', 'indicadores.*', 'actividades.*', 'servicios.*', 'system-maintenance.*'))
                <li class="menu-title"><span>Administraci&oacute;n</span></li>
                <li class="nav-item">
                    <a class="nav-link menu-link {{ $catalogOpen ? '' : 'collapsed' }}" href="#sidebarConfiguration" data-bs-toggle="collapse" role="button" aria-expanded="{{ $catalogOpen ? 'true' : 'false' }}" aria-controls="sidebarConfiguration"><i class="ri-settings-3-line"></i><span>Configuraci&oacute;n</span></a>
                    <div class="collapse menu-dropdown {{ $catalogOpen ? 'show' : '' }}" id="sidebarConfiguration"><ul class="nav nav-sm flex-column">
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">Usuarios</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('place-names.*') ? 'active' : '' }}" href="{{ route('place-names.index') }}">Lugares</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('donantes.*') ? 'active' : '' }}" href="{{ route('donantes.index') }}">Donantes</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('proyectos.*') ? 'active' : '' }}" href="{{ route('proyectos.index') }}">Proyectos</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('indicadores.*') ? 'active' : '' }}" href="{{ route('indicadores.index') }}">Indicadores</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('actividades.*') ? 'active' : '' }}" href="{{ route('actividades.index') }}">Actividades</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('servicios.*') ? 'active' : '' }}" href="{{ route('servicios.index') }}">Servicios</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('system-maintenance.*') ? 'active' : '' }}" href="{{ route('system-maintenance.index') }}">Mantenimiento</a></li>
                    </ul></div>
                </li>
            @endif
        </ul></div></div>
        <div class="sidebar-background"></div>
    </div>
    <div class="vertical-overlay"></div>

    <div class="main-content">
        <div class="page-content"><div class="container-fluid">
            @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Revise los datos del formulario.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div></div>
        <footer class="footer"><div class="container-fluid"><div class="row"><div class="col-sm-6">{{ now()->year }} &copy; ASONACOP.</div><div class="col-sm-6"><div class="text-sm-end d-none d-sm-block">Sistema de respuesta al terremoto &middot; Venezuela</div></div></div></div></footer>
    </div>
</div>
<button onclick="topFunction()" class="btn btn-danger btn-icon" id="back-to-top" aria-label="Volver arriba"><i class="ri-arrow-up-line"></i></button>
@else
<header class="site-header"><div class="header-inner"><a class="brand" href="{{ route('login') }}"><span class="brand-mark">ASONACOP</span><span>Respuesta al terremoto<br><strong>Venezuela</strong></span></a></div></header>
<main class="page-shell">
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-error" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" role="alert"><strong>Revise los datos del formulario.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@endauth
<script src="{{ asset('vendor/select2/js/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/select2/js/select2.full.min.js') }}"></script>
@auth
<script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/libs/node-waves/waves.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script src="{{ asset('js/admin-menu.js') }}"></script>
@else
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
@endauth
<script src="{{ asset('js/pwa.js') }}" defer></script>
@stack('scripts')
</body>
</html>
