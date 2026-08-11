<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#004b87">
    <title>Sistema en mantenimiento | ASONACOP</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/system-maintenance.css') }}">
</head>
<body class="maintenance-page">
    <main class="maintenance-message" role="alert">
        <div class="maintenance-brand">ASONACOP</div>
        <div class="maintenance-icon" aria-hidden="true">⚙</div>
        <p class="eyebrow">Mantenimiento programado</p>
        <h1>Sistema temporalmente no disponible</h1>
        <p>{{ $message }}</p>
        <p class="maintenance-help">Sus datos permanecen protegidos. Intente nuevamente dentro de unos minutos.</p>
        <a class="button button-primary" href="{{ route('dashboard') }}">Reintentar</a>
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button class="link-button" type="submit">Cerrar sesión</button>
        </form>
    </main>
</body>
</html>
