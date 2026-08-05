<!doctype html>
<html lang="es">
<head>
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
    <link rel="apple-touch-icon" href="{{ asset('icons/asonacop-app.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
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
    @stack('styles')
</head>
<body>
    <header class="site-header navbar navbar-expand-xl" data-bs-theme="dark">
        <div class="header-inner">
            <a class="brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                <span class="brand-mark">ASONACOP</span>
                <span>Respuesta al terremoto<br><strong>Venezuela</strong></span>
            </a>
            @auth
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primary-navigation" aria-controls="primary-navigation" aria-expanded="false" aria-label="Abrir menú de navegación">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="primary-navigation">
                    <nav class="main-nav navbar-nav ms-xl-auto" aria-label="Navegación principal">
                        <a href="{{ route('dashboard') }}">Panel</a>
                        <a href="{{ route('reports.index') }}">Registros</a>
                        <a href="{{ route('beneficiaries.summary') }}">Informe de beneficiarios</a>
                        @if (auth()->user()->isAdministrator())
                            <details class="nav-submenu">
                                <summary>Configuraci&oacute;n</summary>
                                <div class="nav-submenu-links">
                                    <a href="{{ route('users.index') }}">Usuarios</a>
                                    <a href="{{ route('place-names.index') }}">Lugares</a>
                                </div>
                            </details>
                        @endif
                        <a class="button button-small" href="{{ route('reports.create') }}">+ Nuevo registro</a>
                        <div class="current-user" aria-label="Usuario conectado">
                            <strong>{{ auth()->user()->name }}</strong>
                            <span>{{ \App\Models\User::roleLabels()[auth()->user()->role] ?? auth()->user()->role }}</span>
                        </div>
                        <form action="{{ route('logout') }}" method="post">
                            @csrf
                            <button class="link-button" type="submit">Salir</button>
                        </form>
                    </nav>
                </div>
            @endauth
        </div>
    </header>

    <main class="page-shell">
        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <strong>Revise los datos del formulario.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/pwa.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
