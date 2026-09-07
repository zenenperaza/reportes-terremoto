@extends('layouts.app')

@section('title', 'Crear nueva contraseña | Respuesta ASONACOP')

@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <p class="eyebrow">Seguridad de la cuenta</p>
        <h1>Crear nueva contraseña</h1>
        <p class="muted">Defina una contraseña de al menos ocho caracteres para recuperar el acceso.</p>
        <form method="post" action="{{ route('password.update') }}" class="stack-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>Correo electr&oacute;nico
                <input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus>
            </label>
            <label>Nueva contrase&ntilde;a
                <input type="password" name="password" autocomplete="new-password" minlength="8" required>
            </label>
            <label>Confirmar nueva contrase&ntilde;a
                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            </label>
            <button class="button button-primary" type="submit">Actualizar contrase&ntilde;a</button>
        </form>
        <p class="auth-footer"><a href="{{ route('login') }}">Volver al inicio de sesi&oacute;n</a></p>
    </div>
</section>
@endsection
