@extends('layouts.app')

@section('title', 'Verificar acceso | Respuesta ASONACOP')

@section('content')
<section class="auth-shell">
    <div class="auth-card two-factor-auth-card">
        <span class="two-factor-auth-icon" aria-hidden="true">&#9993;</span>
        <p class="eyebrow">Doble autenticaci&oacute;n</p>
        <h1>Verifique su acceso</h1>
        <p class="muted">Enviamos un c&oacute;digo de 6 d&iacute;gitos a <strong>{{ $maskedEmail }}</strong>. El c&oacute;digo vence en 10 minutos.</p>
        <form method="post" action="{{ route('two-factor.verify') }}" class="stack-form">
            @csrf
            <label for="two-factor-code">C&oacute;digo de verificaci&oacute;n
                <input class="two-factor-code-input" id="two-factor-code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
            </label>
            <button class="button button-primary" type="submit">Verificar e ingresar</button>
        </form>
        <div class="two-factor-actions">
            <form method="post" action="{{ route('two-factor.resend') }}">@csrf<button class="link-button two-factor-resend" type="submit">Reenviar c&oacute;digo</button></form>
            <a href="{{ route('login') }}">Volver al inicio de sesi&oacute;n</a>
        </div>
    </div>
</section>
@endsection
