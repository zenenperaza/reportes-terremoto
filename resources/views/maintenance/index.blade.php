@extends('layouts.app')
@section('title', 'Mantenimiento del sistema | SIA')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/system-maintenance.css') }}">
@endpush
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Configuración</p>
        <h1>Mantenimiento del sistema</h1>
        <p class="muted">Controle temporalmente el acceso de registradores y coordinadores mientras realiza cambios.</p>
    </div>
</section>

<section class="content-card maintenance-presence" aria-label="Usuarios conectados">
    <div><p class="eyebrow">Usuarios conectados</p><strong data-connected-count>{{ $connectedUsers ?? 'No disponible' }}</strong></div>
    <div><p>Usuarios distintos con una sesión abierta que respondió en los últimos 2 minutos, incluidos administradores.</p>
        <small data-live-connection>Se actualiza automáticamente cada 15 segundos. Varias pestañas o dispositivos cuentan como un usuario.</small>
        @if($connectedUsers === null)<p class="text-warning">Aplique la migración de presencia para habilitar el conteo.</p>@endif
    </div>
</section>

<section class="content-card maintenance-control">
    <div>
        <p class="eyebrow">Estado actual</p>
        <h2 data-maintenance-state>{{ $maintenanceEnabled ? 'Sistema bloqueado' : ($maintenanceState['scheduled'] ? 'Bloqueo programado' : 'Sistema disponible') }}</h2>
        <p data-maintenance-description>
            @if($maintenanceEnabled)
                Los registradores y coordinadores no pueden usar el sistema. Los administradores conservan el acceso.
            @else
                Todos los usuarios activos pueden ingresar y trabajar normalmente.
            @endif
        </p>
        <span data-maintenance-badge class="status {{ $maintenanceEnabled ? 'status-inactive' : 'status-active' }}">
            {{ $maintenanceEnabled ? 'Mantenimiento activo' : ($maintenanceState['scheduled'] ? 'Aviso enviado' : 'Funcionamiento normal') }}
        </span>
    </div>

    <form id="maintenance-toggle-form" class="js-maintenance-action" action="{{ route('system-maintenance.update') }}" method="post"
          data-enabling="{{ $maintenanceEnabled ? '1' : '0' }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="enabled" value="{{ $maintenanceEnabled ? 0 : 1 }}">
        <button class="button {{ $maintenanceEnabled ? 'button-primary' : 'button-danger' }}" type="submit">
            {{ $maintenanceEnabled ? 'Habilitar sistema' : 'Bloquear sistema' }}
        </button>
    </form>
</section>

<section class="content-card maintenance-schedule">
    <h2>Avisar antes de bloquear</h2>
    <p>Los usuarios verán un aviso y una cuenta regresiva sin recargar la página. Podrán guardar durante el plazo. Al vencer, se bloquean las operaciones de usuarios no administradores.</p>
    <p data-maintenance-deadline>@if($maintenanceState['scheduled'])Bloqueo programado para {{ \Illuminate\Support\Carbon::parse($maintenanceState['starts_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}.@endif</p>
    <form id="maintenance-schedule-form" class="js-maintenance-action" data-action="schedule" action="{{ route('system-maintenance.update') }}" method="post">
        @csrf @method('PUT')
        <input type="hidden" name="enabled" value="1">
        <div class="form-grid">
            <label for="maintenance-delay">Tiempo para guardar
                <select id="maintenance-delay" name="delay_minutes" @disabled($maintenanceEnabled)><option value="5">5 minutos</option><option value="10">10 minutos</option></select>
            </label>
            <label for="maintenance-message">Mensaje para los usuarios
                <textarea id="maintenance-message" name="message" maxlength="300" rows="2" @disabled($maintenanceEnabled)>{{ old('message', 'Se realizarán modificaciones en el sistema. Guarde su trabajo antes del bloqueo.') }}</textarea>
            </label>
        </div>
        <button class="button button-primary" type="submit" @disabled($maintenanceEnabled)>Enviar aviso y programar bloqueo</button>
    </form>
    <form id="maintenance-cancel-form" class="js-maintenance-action" data-action="cancel" action="{{ route('system-maintenance.update') }}" method="post" @if(!$maintenanceState['scheduled']) hidden @endif>
        @csrf @method('PUT')
        <input type="hidden" name="enabled" value="0">
        <button class="button button-secondary" type="submit">Cancelar bloqueo programado</button>
    </form>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.querySelectorAll('.js-maintenance-action').forEach(function (form) { form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const enabling = this.dataset.enabling === '1';
        const schedule = this.dataset.action === 'schedule';
        const cancel = this.dataset.action === 'cancel';
        const delay = document.getElementById('maintenance-delay').value;
        const description = schedule ? 'Se avisará a los usuarios y el sistema se bloqueará dentro de ' + delay + ' minutos. Hasta entonces podrán guardar su trabajo.'
            : cancel ? 'Se quitará el aviso y el sistema continuará disponible.'
            : enabling ? 'Los registradores y coordinadores podrán ingresar nuevamente.'
            : 'Los usuarios perderán el acceso inmediatamente, sin tiempo para guardar. Los administradores podrán continuar trabajando.';
        if (typeof Swal === 'undefined') { if (window.confirm(description)) this.submit(); return; }
        const result = await Swal.fire({
            icon: enabling ? 'question' : 'warning',
            title: schedule ? '¿Programar el bloqueo?' : cancel ? '¿Cancelar el bloqueo programado?' : enabling ? '¿Habilitar el sistema?' : '¿Bloquear el sistema ahora?',
            text: description,
            showCancelButton: true,
            confirmButtonText: schedule ? 'Sí, avisar y programar' : cancel ? 'Sí, cancelar bloqueo' : enabling ? 'Sí, habilitar' : 'Sí, bloquear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: enabling ? '#1c8b5c' : '#b42318',
            cancelButtonColor: '#647784',
            reverseButtons: true,
            focusCancel: !enabling,
        });

        if (result.isConfirmed) this.submit();
    }); });
</script>
@endpush
