<aside id="system-live-notice" class="system-maintenance-banner system-live-notice" role="status" aria-live="polite" hidden
       data-status-url="{{ route('system-maintenance.status') }}" data-administrator="{{ auth()->user()->isAdministrator() ? '1' : '0' }}">
    <i class="ri-alert-line" aria-hidden="true"></i>
    <div class="system-maintenance-banner__message">
        <strong data-live-title>Aviso de mantenimiento</strong>
        <span data-live-message></span>
        <span data-live-countdown aria-live="off"></span>
    </div>
    @if(auth()->user()->isAdministrator())<a href="{{ route('system-maintenance.index') }}">Administrar bloqueo</a>@endif
</aside>
