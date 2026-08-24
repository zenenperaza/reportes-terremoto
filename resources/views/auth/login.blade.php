@extends('layouts.app')

@section('title', 'Ingresar | Respuesta ASONACOP')

@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <p class="eyebrow">Sistema de registros</p>
        <h1>Ingrese a su cuenta</h1>
        <p class="muted">Registre y haga seguimiento a las actividades de respuesta.</p>
        <form method="post" action="{{ route('login.store') }}" class="stack-form">
            @csrf
            <label>Correo electr&oacute;nico
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </label>
            <label>Contrase&ntilde;a
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <label class="checkbox-label"><input type="checkbox" name="remember" value="1"> Mantener mi sesi&oacute;n abierta</label>
            <button class="button button-primary" type="submit">Ingresar</button>
        </form>
        <p class="auth-footer">&iquest;No tiene una cuenta? Solic&iacute;tela al administrador del sistema.</p>
    </div>
</section>
@endsection
