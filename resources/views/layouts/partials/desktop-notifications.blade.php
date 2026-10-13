<div class="dropdown ms-1 header-item" id="desktop-notifications" data-user-id="{{ auth()->id() }}" data-icon="{{ asset('icons/asonacop-app.png') }}"
     data-allowed="{{ \App\Support\SystemNotifications::allowed(auth()->user()) ? '1' : '0' }}" data-read-url="{{ route('system-notifications.read') }}">
    <button type="button" id="desktop-notifications-menu" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notificaciones en la PC" title="Notificaciones en la PC">
        <i class="ri-notification-3-line fs-22" aria-hidden="true"></i>
        <span class="badge bg-danger rounded-pill" data-notification-count hidden aria-label="Notificaciones pendientes"></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-3" style="width: min(320px, calc(100vw - 24px));" aria-labelledby="desktop-notifications-menu">
        <h6>Notificaciones en la PC</h6>
        <p class="small text-muted mb-2">Con el permiso «Recibir notificaciones», los administradores reciben avisos de acceso; los registradores y coordinadores, cambios hechos por otros integrantes de su equipo.</p>
        <p class="small mb-2" data-desktop-status role="status" aria-live="polite">Comprobando disponibilidad…</p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-desktop-toggle disabled>Activar notificaciones</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-desktop-test disabled>Probar notificación</button>
        </div>
        <p class="small mt-2 mb-0" data-desktop-test-result role="status" aria-live="polite" aria-atomic="true" hidden></p>
        <hr>
        <h6>Avisos recientes</h6>
        <div data-notification-list class="small" style="max-height: 240px; overflow-y: auto;" aria-live="polite">Sin avisos pendientes.</div>
        <button type="button" class="btn btn-link btn-sm px-0" data-notification-read hidden>Marcar como vistos</button>
        <noscript><p class="small mb-0 mt-2">Active JavaScript para recibir notificaciones.</p></noscript>
    </div>
</div>
@php
    $desktopEvents = [];
    if (session('desktop_login_event')) {
        $desktopEvents[] = ['id' => session('desktop_login_event'), 'kind' => 'opened'];
    }
    if (session('success')) {
        $desktopSuccess = \Illuminate\Support\Str::lower((string) session('success'));
        $desktopKind = match (true) {
            \Illuminate\Support\Str::contains($desktopSuccess, ['eliminad', 'desvinculad']) => 'deleted',
            \Illuminate\Support\Str::contains($desktopSuccess, ['actualizad', 'modificad']) => 'updated',
            \Illuminate\Support\Str::contains($desktopSuccess, ['guardad', 'cread', 'cargad', 'importad', 'agregad']) => 'saved',
            default => 'success',
        };
        $desktopEvents[] = ['id' => (string) \Illuminate\Support\Str::uuid(), 'kind' => $desktopKind];
    }
    if (session('error') || $errors->any()) {
        $desktopEvents[] = ['id' => (string) \Illuminate\Support\Str::uuid(), 'kind' => 'error'];
    }
@endphp
<script type="application/json" id="desktop-notification-events">{!! \Illuminate\Support\Js::encode($desktopEvents) !!}</script>
