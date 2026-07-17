@extends('layouts.app')

@section('title', 'Crear cuenta | Respuesta UNICEF')

@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <p class="eyebrow">Primer acceso</p>
        <h1>Cree su cuenta</h1>
        <p class="muted">La cuenta identifica a la persona que remite cada reporte.</p>
        <form method="post" action="{{ route('register.store') }}" class="stack-form">
            @csrf
            <label>Nombre completo
                <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
            </label>
            <label>Correo electrónico
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
            </label>
            <label>Contraseña
                <input type="password" name="password" autocomplete="new-password" required>
            </label>
            <label>Confirme la contraseña
                <input type="password" name="password_confirmation" autocomplete="new-password" required>
            </label>
            <button class="button button-primary" type="submit">Crear cuenta</button>
        </form>
        <p class="auth-footer">¿Ya tiene una cuenta? <a href="{{ route('login') }}">Ingrese aquí</a>.</p>
    </div>
</section>
@endsection
