(function () {
    'use strict';
    const controls = document.getElementById('desktop-notifications');
    if (!controls) return;

    const toggle = controls.querySelector('[data-desktop-toggle]');
    const test = controls.querySelector('[data-desktop-test]');
    const status = controls.querySelector('[data-desktop-status]');
    const testResult = controls.querySelector('[data-desktop-test-result]');
    const supported = window.isSecureContext && typeof window.Notification === 'function';
    const prefix = 'sia-desktop:' + controls.dataset.userId + ':';
    const memory = {};
    let latest = null;
    let requesting = false;
    let testing = false;
    let testSequence = 0;
    let actionSequence = 0;
    let allowed = controls.dataset.allowed === '1';
    let inbox = [];
    let inboxCount = 0;
    let acknowledging = false;
    const list = controls.querySelector('[data-notification-list]');
    const badge = controls.querySelector('[data-notification-count]');
    const markRead = controls.querySelector('[data-notification-read]');
    const actionMessages = {
        opened: ['SIA · Bienvenido', 'El sistema está listo para trabajar.'],
        saved: ['SIA · Datos guardados', 'La información se guardó correctamente.'],
        updated: ['SIA · Datos actualizados', 'Los cambios se guardaron correctamente.'],
        deleted: ['SIA · Datos eliminados', 'La eliminación se completó correctamente.'],
        success: ['SIA · Operación completada', 'La operación se completó correctamente. Revise el detalle en SIA.'],
        error: ['SIA · Revise la operación', 'La operación requiere atención. Revise el mensaje que aparece en SIA.'],
        exported: ['SIA · Exportación preparada', 'La exportación está preparada. Revise el resultado en su navegador.'],
    };

    // Preferences and deduplication contain no beneficiary data or message text.
    function read(key) {
        try { return window.localStorage.getItem(prefix + key) ?? memory[key]; }
        catch (error) { return memory[key]; }
    }
    function write(key, value) {
        memory[key] = value;
        try { window.localStorage.setItem(prefix + key, value); } catch (error) { /* Private/storage-restricted browser. */ }
    }
    function enabled() {
        return allowed && supported && window.Notification.permission === 'granted' && read('enabled') !== 'off';
    }
    function refresh() {
        toggle.disabled = !allowed || !supported || requesting;
        test.disabled = !enabled() || testing;
        toggle.textContent = enabled() ? 'Desactivar notificaciones' : 'Activar notificaciones';
        toggle.setAttribute('aria-pressed', enabled() ? 'true' : 'false');
        status.textContent = !allowed ? 'Su usuario no tiene el permiso «Recibir notificaciones». Solicítelo al administrador.'
            : !supported ? 'No disponibles. Use un navegador compatible y HTTPS (o localhost).'
            : window.Notification.permission === 'denied' ? 'Permiso bloqueado. Permita las notificaciones de SIA en la configuración del navegador.'
            : enabled() ? 'Notificaciones activadas en este navegador. Si no aparecen, revise las notificaciones y No molestar de Windows.'
            : 'Notificaciones desactivadas. Pulse Activar y permita las notificaciones del navegador.';
    }
    function feedback(message, failed = false) {
        if (!testResult) return;
        testResult.hidden = false;
        testResult.className = 'small mt-2 mb-0 ' + (failed ? 'text-danger' : 'text-body');
        testResult.textContent = message;
    }
    function show(title, body, tag, options = {}, onResult) {
        if (!enabled()) return false;
        try {
            const notification = new window.Notification(title, {
                body, icon: controls.dataset.icon, tag, lang: 'es', ...options,
            });
            notification.onshow = function () { if (onResult) onResult(true); };
            notification.onerror = function () {
                if (onResult) onResult(false);
                else feedback('El navegador no pudo mostrar la notificación. Revise los permisos de SIA y las notificaciones del navegador en Windows.', true);
            };
            notification.onclick = function () {
                // Focus this page only: never reload a form or lose unsaved work.
                window.focus();
                notification.close();
            };
            return true;
        } catch (error) {
            status.textContent = 'No se pudo mostrar la notificación. Revise los permisos del navegador y del sistema operativo.';
            return false;
        }
    }
    // A browser may accept a notification while the OS hides its banner. Never
    // claim that it appeared on screen merely because an API call succeeded.
    function bounded(promise) {
        return new Promise((resolve, reject) => {
            const timer = window.setTimeout(() => resolve({timedOut: true}), 8000);
            Promise.resolve(promise).then(value => {
                window.clearTimeout(timer);
                resolve({value});
            }, error => {
                window.clearTimeout(timer);
                reject(error);
            });
        });
    }
    async function sendTest() {
        if (!enabled() || testing) return;
        testing = true;
        refresh();
        test.textContent = 'Enviando prueba…';
        feedback('Enviando notificación de prueba…');
        const title = 'SIA · Notificación de prueba';
        const body = 'Esta es una prueba de las notificaciones de SIA.';
        const sequence = ++testSequence;
        const tag = 'sia-notification-test-' + Date.now() + '-' + sequence;
        const options = {body, icon: controls.dataset.icon, tag, lang: 'es', renotify: true, requireInteraction: true};
        try {
            let registration;
            try {
                const worker = window.navigator?.serviceWorker;
                if (worker && typeof worker.getRegistration === 'function') {
                    const lookup = await bounded(worker.getRegistration());
                    if (!lookup.timedOut) registration = lookup.value;
                }
            } catch (error) { /* No active PWA: use the desktop API below. */ }
            if (!enabled()) {
                feedback('La prueba se canceló porque las notificaciones ya no están habilitadas.', true);
                return;
            }
            if (registration?.active && typeof registration.showNotification === 'function') {
                try {
                    const sent = await bounded(registration.showNotification(title, options));
                    feedback(sent.timedOut
                        ? 'El navegador no confirmó el envío. Revise el centro de notificaciones de Windows antes de repetir la prueba.'
                        : 'Prueba enviada al sistema de notificaciones. Si no ve el aviso, revise el centro de notificaciones de Windows, No molestar y los permisos del navegador.');
                    return;
                } catch (error) { /* Some browsers expose the PWA API but reject it. Try the desktop API. */ }
            }
            await new Promise(resolve => {
                const timer = window.setTimeout(() => {
                    feedback('El navegador recibió la prueba, pero no confirmó su visualización. Revise el centro de notificaciones de Windows, No molestar y los permisos del navegador.');
                    resolve();
                }, 8000);
                const accepted = show(title, body, tag, {renotify: true, requireInteraction: true}, displayed => {
                    window.clearTimeout(timer);
                    if (sequence === testSequence) feedback(displayed
                        ? 'El navegador confirmó la notificación. Si no ve el aviso, revise el centro de notificaciones de Windows y No molestar.'
                        : 'El navegador no pudo mostrar la prueba. Revise los permisos de SIA y las notificaciones del navegador en Windows.', !displayed);
                    resolve();
                });
                if (!accepted) {
                    window.clearTimeout(timer);
                    feedback('No se pudo enviar la prueba. Revise los permisos de SIA y las notificaciones del navegador en Windows.', true);
                    resolve();
                }
            });
        } finally {
            testing = false;
            test.textContent = 'Probar notificación';
            refresh();
        }
    }
    function markOpened() {
        memory.opened = '1';
        try { window.sessionStorage.setItem(prefix + 'opened', '1'); } catch (error) { /* In-page fallback. */ }
    }
    function hasOpened() {
        try { return window.sessionStorage.getItem(prefix + 'opened') === '1' || memory.opened === '1'; }
        catch (error) { return memory.opened === '1'; }
    }
    function notifyAction(kind, id) {
        // Fixed, non-sensitive messages only. Never copy form/response text to the desktop.
        if (!Object.hasOwn(actionMessages, kind) || !enabled()) return false;
        if (typeof id !== 'string' || !id || id.length > 100) {
            id = Date.now() + ':' + Math.random().toString(36).slice(2) + ':' + (++actionSequence);
        }
        let seen;
        try { seen = JSON.parse(read('last-actions') || '[]'); } catch (error) { seen = []; }
        if (!Array.isArray(seen)) seen = [];
        if (seen.includes(id)) return false;
        const [title, body] = actionMessages[kind];
        if (!show(title, body, 'sia-action-' + kind)) return false;
        write('last-actions', JSON.stringify([...seen.slice(-29), id]));
        if (kind === 'opened') markOpened();
        return true;
    }
    window.SiaDesktopNotifications = {notify: notifyAction};

    function renderInbox() {
        if (badge) {
            badge.hidden = !allowed || !inboxCount;
            badge.textContent = inboxCount > 99 ? '99+' : String(inboxCount);
        }
        if (markRead) markRead.hidden = !allowed || !inbox.length;
        if (!list) return;
        list.replaceChildren();
        if (!allowed || !inbox.length) {
            list.textContent = allowed ? 'Sin avisos pendientes.' : 'No tiene permiso para recibir avisos.';
            return;
        }
        inbox.forEach(item => {
            const row = document.createElement('div');
            row.className = 'border-bottom pb-2 mb-2';
            const heading = document.createElement('strong');
            heading.textContent = item.title;
            const detail = document.createElement('p');
            detail.className = 'mb-0';
            detail.textContent = item.body;
            row.appendChild(heading);
            row.appendChild(detail);
            list.appendChild(row);
        });
    }
    async function acknowledge(ids) {
        const token = document.querySelector('meta[name="csrf-token"]');
        if (!allowed || acknowledging || !ids.length || !token || typeof fetch !== 'function') return;
        acknowledging = true;
        try {
            const response = await fetch(controls.dataset.readUrl, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token.content},
                body: JSON.stringify({ids}),
            });
            if (response.ok) {
                const removed = inbox.filter(item => ids.includes(item.id)).length;
                inbox = inbox.filter(item => !ids.includes(item.id));
                inboxCount = Math.max(0, inboxCount - removed);
                renderInbox();
            }
        } catch (error) { /* Retry next poll; never interrupt the user's form. */ }
        finally { acknowledging = false; }
    }
    function receiveInbox(payload) {
        if (typeof payload.can_receive_notifications === 'boolean') allowed = payload.can_receive_notifications;
        if (!allowed) {
            inbox = []; inboxCount = 0;
            renderInbox();
            return;
        }
        if (!Array.isArray(payload.notifications)) return;
        inbox = payload.notifications.slice(0, 20).filter(item => item && typeof item.id === 'string'
            && /^[1-9]\d{0,18}$/.test(item.id) && ['login', 'record_created', 'record_updated', 'record_deleted'].includes(item.kind)
            && typeof item.title === 'string' && typeof item.body === 'string');
        inboxCount = Number.isInteger(payload.notification_count) && payload.notification_count >= 0 ? payload.notification_count : inbox.length;
        renderInbox();
        if (!enabled()) return;
        let seen;
        try { seen = JSON.parse(read('last-actions') || '[]'); } catch (error) { seen = []; }
        if (!Array.isArray(seen)) seen = [];
        const delivered = [];
        let shown = 0;
        inbox.forEach(item => {
            const key = 'system:' + item.id;
            if (seen.includes(key)) delivered.push(item.id);
            else if (shown < 3 && show('SIA · ' + item.title, item.body, 'sia-remote-' + item.id)) {
                shown++;
                seen = [...seen.slice(-29), key];
                write('last-actions', JSON.stringify(seen));
                delivered.push(item.id);
            }
        });
        if (delivered.length) acknowledge(delivered);
    }
    if (markRead) markRead.addEventListener('click', function () { acknowledge(inbox.map(item => item.id)); });
    function announce(state, previous, force = false) {
        if (!enabled()) return;
        let id, title, body;
        if (state.enabled) {
            id = 'blocked:' + (state.starts_at || 'immediate');
            title = 'SIA · Mantenimiento iniciado';
            body = 'El sistema está en mantenimiento. No cierre formularios con cambios sin guardar. Los administradores conservan el acceso.';
        } else if (state.scheduled) {
            id = 'scheduled:' + state.starts_at;
            title = 'SIA · Mantenimiento programado';
            body = (state.message || 'Se realizarán modificaciones.') + ' Guarde su trabajo antes de las '
                + new Date(state.starts_at).toLocaleTimeString('es-VE', {hour: '2-digit', minute: '2-digit'}) + '.';
        } else if (previous && (previous.enabled || previous.scheduled)) {
            id = 'available';
            title = 'SIA · Sistema disponible';
            body = previous.enabled ? 'El mantenimiento finalizó. Puede continuar trabajando.' : 'El bloqueo programado fue cancelado. Puede continuar trabajando.';
        } else {
            // An idle state ends the preceding event, allowing a future immediate lock.
            write('last-event', 'available');
            return;
        }
        if (!force && read('last-event') === id) return;
        if (show(title, body, 'sia-maintenance')) write('last-event', id);
    }

    toggle.addEventListener('click', async function () {
        if (!allowed || !supported || requesting) return;
        if (enabled()) {
            write('enabled', 'off');
            refresh();
            return;
        }
        requesting = true;
        try {
            // Permission must be requested directly from an explicit user gesture.
            const permission = window.Notification.permission === 'default'
                ? await window.Notification.requestPermission() : window.Notification.permission;
            if (permission === 'granted') write('enabled', 'on');
            refresh();
            if (enabled()) {
                markOpened();
                if (latest && (latest.scheduled || latest.enabled)) announce(latest, null, true);
                else show('SIA · Notificaciones activadas', 'Recibirá avisos de sus operaciones y del mantenimiento mientras SIA esté abierto.', 'sia-notification-test');
            }
        } catch (error) {
            status.textContent = 'No se pudo solicitar el permiso. Revise la configuración del navegador.';
        } finally {
            requesting = false;
            // Do not replace an error message from requesting/showing the notification.
            toggle.disabled = !allowed || !supported;
        }
    });
    test.addEventListener('click', sendTest);
    document.addEventListener('sia:maintenance-state', function (event) {
        const state = event.detail;
        if (!state || typeof state.enabled !== 'boolean' || typeof state.scheduled !== 'boolean'
            || (state.scheduled && !Number.isFinite(Date.parse(state.starts_at)))) return;
        const previous = latest;
        latest = state;
        receiveInbox(state);
        refresh();
        announce(state, previous);
    });
    window.addEventListener('focus', refresh);
    window.addEventListener('storage', refresh);
    refresh();
    let initialEvents = [];
    try {
        initialEvents = JSON.parse(document.getElementById('desktop-notification-events')?.textContent || '[]');
    } catch (error) { /* Invalid bootstrap must not affect forms. */ }
    if (!Array.isArray(initialEvents)) initialEvents = [];
    if (initialEvents.length) {
        markOpened();
        initialEvents.forEach(event => { if (event) notifyAction(event.kind, event.id); });
    } else if (!hasOpened()) notifyAction('opened');
}());
