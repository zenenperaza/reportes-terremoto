(function () {
    'use strict';
    const notice = document.getElementById('system-live-notice');
    const token = document.querySelector('meta[name="csrf-token"]');
    if (!notice || !token || typeof fetch === 'undefined') return;

    const administrator = notice.dataset.administrator === '1';
    const oldBanner = document.getElementById('system-maintenance-banner');
    const title = notice.querySelector('[data-live-title]');
    const message = notice.querySelector('[data-live-message]');
    const countdown = notice.querySelector('[data-live-countdown]');
    const connection = document.querySelector('[data-live-connection]');
    let state = null;
    let offset = 0;
    let pending = false;
    let expiredDeadline = null;
    let stopped = false;

    function setText(element, value) {
        if (element.textContent !== value) element.textContent = value;
    }

    function updateOffset() {
        const banner = !notice.hidden ? notice : oldBanner && !oldBanner.hidden ? oldBanner : null;
        document.body.classList.toggle('has-maintenance-banner', !!banner);
        document.body.style.setProperty('--maintenance-banner-height', banner ? Math.ceil(banner.getBoundingClientRect().height) + 'px' : '0px');
    }

    function render() {
        if (!state) return;
        const warning = state.scheduled || state.enabled;
        if (oldBanner) oldBanner.hidden = !(administrator && state.enabled);
        notice.hidden = !warning || !!(oldBanner && administrator && state.enabled);
        notice.classList.toggle('is-blocked', state.enabled);
        setText(title, state.enabled ? 'SISTEMA BLOQUEADO' : 'MANTENIMIENTO PROGRAMADO');
        setText(message, state.enabled
            ? administrator ? 'Los usuarios no administradores tienen el acceso suspendido. Habilite el sistema al finalizar.'
                : 'El mantenimiento ha comenzado. Las operaciones están bloqueadas. No cierre esta página si necesita conservar o copiar lo que no guardó.'
            : (state.message || 'Se realizarán modificaciones. Guarde su trabajo antes del bloqueo.'));
        if (state.scheduled) {
            const remaining = Math.max(0, Math.ceil((Date.parse(state.starts_at) - (Date.now() + offset)) / 1000));
            setText(countdown, remaining ? 'Tiempo para guardar: ' + Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0')
                : 'Comprobando el inicio del bloqueo…');
            if (!remaining && expiredDeadline !== state.starts_at) {
                expiredDeadline = state.starts_at;
                poll(); // Only the server decides when writes are blocked.
            }
        } else setText(countdown, '');
        if (Object.hasOwn(state, 'connected_users')) {
            document.querySelectorAll('[data-connected-count]').forEach(count => { setText(count, String(state.connected_users ?? 'No disponible')); });
        }
        const heading = document.querySelector('[data-maintenance-state]');
        if (heading) heading.textContent = state.enabled ? 'Sistema bloqueado' : state.scheduled ? 'Bloqueo programado' : 'Sistema disponible';
        const description = document.querySelector('[data-maintenance-description]');
        if (description) description.textContent = state.enabled ? 'Los usuarios no administradores no pueden usar el sistema. Los administradores conservan el acceso.'
            : state.scheduled ? 'Aviso enviado. Los usuarios pueden trabajar y guardar hasta la hora programada.' : 'Todos los usuarios activos pueden ingresar y trabajar normalmente.';
        const badge = document.querySelector('[data-maintenance-badge]');
        if (badge) {
            badge.textContent = state.enabled ? 'Mantenimiento activo' : state.scheduled ? 'Aviso enviado' : 'Funcionamiento normal';
            badge.classList.toggle('status-inactive', state.enabled);
            badge.classList.toggle('status-active', !state.enabled);
        }
        const deadline = document.querySelector('[data-maintenance-deadline]');
        if (deadline) deadline.textContent = state.scheduled ? 'Bloqueo programado para ' + new Date(state.starts_at).toLocaleString('es-VE') + '.' : '';
        const toggle = document.getElementById('maintenance-toggle-form');
        if (toggle) {
            toggle.dataset.enabling = state.enabled ? '1' : '0';
            toggle.querySelector('[name="enabled"]').value = state.enabled ? '0' : '1';
            const button = toggle.querySelector('button[type="submit"]');
            button.textContent = state.enabled ? 'Habilitar sistema' : 'Bloquear sistema';
            button.classList.toggle('button-primary', state.enabled);
            button.classList.toggle('button-danger', !state.enabled);
        }
        const schedule = document.getElementById('maintenance-schedule-form');
        if (schedule) schedule.querySelectorAll('select,textarea,button').forEach(field => { field.disabled = state.enabled; });
        const cancel = document.getElementById('maintenance-cancel-form');
        if (cancel) cancel.hidden = !state.scheduled;
        updateOffset();
    }

    async function poll() {
        if (pending || stopped) return;
        pending = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(notice.dataset.statusUrl, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': token.content}
            });
            if ([401, 403, 419].includes(response.status)) { stopped = true; throw new Error('session'); }
            if (!response.ok) throw new Error('connection');
            const payload = await response.json();
            if (typeof payload.enabled !== 'boolean' || typeof payload.scheduled !== 'boolean'
                || !Number.isFinite(Date.parse(payload.server_now))
                || (payload.scheduled && !Number.isFinite(Date.parse(payload.starts_at)))) throw new Error('response');
            state = payload;
            offset = Date.parse(payload.server_now) - Date.now();
            if (connection) connection.textContent = 'Actualizado: ' + new Date().toLocaleTimeString('es-VE') + ' · cada 15 segundos.';
            render();
            document.dispatchEvent(new CustomEvent('sia:maintenance-state', {detail: payload}));
        } catch (error) {
            if (connection) connection.textContent = stopped ? 'La sesión expiró. Ingrese nuevamente para actualizar el conteo.' : 'Sin conexión: el último conteo puede estar desactualizado. Reintentando…';
            // Do not navigate, reload forms, unlock the system or clear a prior warning.
        } finally {
            clearTimeout(timeout);
            pending = false;
        }
    }

    if (window.ResizeObserver) {
        const observer = new window.ResizeObserver(updateOffset);
        observer.observe(notice);
        if (oldBanner) observer.observe(oldBanner);
    }
    window.addEventListener('resize', updateOffset);
    window.addEventListener('focus', poll);
    window.addEventListener('online', poll);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    setInterval(render, 1000);
    setInterval(poll, 15000);
    poll();
}());
