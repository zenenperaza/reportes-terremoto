@extends('layouts.app')

@section('title', 'Recuperar contraseña | Respuesta ASONACOP')

@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <p class="eyebrow">Seguridad de la cuenta</p>
        <h1>Recuperar contraseña</h1>
        <p class="muted">Ingrese su correo electrónico y le enviaremos un enlace temporal para crear una nueva contraseña.</p>
        <form method="post" action="{{ route('password.email') }}" class="stack-form">
            @csrf
            <label>Correo electr&oacute;nico
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </label>
            <button class="button button-primary" type="submit">Enviar enlace de recuperaci&oacute;n</button>
        </form>
        <p class="auth-footer"><a href="{{ route('login') }}">Volver al inicio de sesi&oacute;n</a></p>
    </div>
</section>
@endsection
