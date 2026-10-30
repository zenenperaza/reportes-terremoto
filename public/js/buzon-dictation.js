document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const form = document.querySelector('form[data-buzon-entry]');
    const panel = form?.querySelector('[data-buzon-dictation]');
    if (!panel) return;
    const status = panel.querySelector('[data-buzon-voice-status]');
    const preview = panel.querySelector('[data-buzon-voice-preview]');
    const dataTranscript = panel.querySelector('[data-buzon-data-transcript]');
    const applyDataButton = panel.querySelector('[data-buzon-voice-apply]');
    const clearDataButton = panel.querySelector('[data-buzon-voice-clear]');
    const transcriptPanel = panel.querySelector('[data-buzon-transcript-panel]');
    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const supported = Boolean(Recognition) && window.isSecureContext !== false;
    const controls = Array.from(form.querySelectorAll('[data-buzon-voice-target]')).map(button => ({
        button, field: button.dataset.buzonVoiceTarget === 'datos' ? dataTranscript : form.elements.namedItem(button.dataset.buzonVoiceTarget),
        dataMode: button.dataset.buzonVoiceTarget === 'datos',
        label: button.querySelector('[data-buzon-voice-label]'),
    })).filter(control => ['datos', 'comment', 'improvement'].includes(control.button.dataset.buzonVoiceTarget)
        && control.field && !control.field.disabled && !control.field.readOnly);
    controls.forEach(control => { control.idleLabel = control.label.textContent; });
    let session = null;

    const applyData = () => {
        if (!dataTranscript || !window.BuzonVoiceData?.parse) return;
        status.hidden = false;
        const {values, issues} = window.BuzonVoiceData.parse(dataTranscript.value);
        const allowedFields = new Set(['place_name', 'full_name', 'phone', 'age', 'gender', 'disabilities', 'afro_descendant', 'opinion', 'feedback_type']);
        let count = 0;
        for (const [name, value] of Object.entries(values)) {
            if (!allowedFields.has(name)) continue;
            if (name === 'disabilities') {
                form.querySelectorAll('input[name="disabilities[]"]').forEach(input => {
                    input.checked = value.includes(input.value);
                });
                count++;
                continue;
            }
            if (name === 'opinion') {
                form.querySelectorAll('input[name="opinion"]').forEach(input => {input.checked = input.value === value;});
                count++;
                continue;
            }
            const field = form.elements.namedItem(name);
            if (!field || field.disabled || field.readOnly) continue;
            field.value = value;
            field.classList.add('buzon-voice-filled');
            field.dispatchEvent(new Event('input', {bubbles: true}));
            field.dispatchEvent(new Event('change', {bubbles: true}));
            count++;
        }
        status.textContent = `${count ? `Se completaron ${count} campos reconocidos.` : 'No se reconocieron datos para completar.'} Revise los datos, especialmente el teléfono, antes de guardar. Proyecto y fecha no se modifican por dictado. ${issues.join(' ')}`;
    };

    const sync = () => controls.forEach(control => {
        const active = session?.control === control;
        control.button.disabled = !supported || Boolean(session && (!active || session.stopping));
        control.button.setAttribute('aria-pressed', active ? 'true' : 'false');
        control.button.classList.toggle('is-listening', active);
        control.label.textContent = active ? (session.stopping ? 'Finalizando dictado…' : 'Detener dictado') : control.idleLabel;
    });
    const finish = current => {
        if (session !== current) return;
        session = null;
        sync();
    };
    const cancel = message => {
        const current = session;
        if (!current) return;
        // Invalidate callbacks first: late results must not write after cancellation.
        finish(current);
        try { current.recognition.abort(); } catch (_) { /* Already ended. */ }
        preview.textContent = '';
        preview.hidden = true;
        status.textContent = message;
    };
    const stop = (message = 'Finalizando dictado. Revise el texto antes de guardar.') => {
        const current = session;
        if (!current || current.stopping) return;
        current.stopping = true;
        status.textContent = message;
        sync();
        try { current.recognition.stop(); }
        catch (_) { cancel('Dictado detenido. Se conserva el texto ya añadido; revíselo antes de guardar.'); }
    };
    const start = control => {
        if (!supported || session) return;
        status.hidden = false;
        let recognition;
        try { recognition = new Recognition(); }
        catch (_) { status.textContent = 'No se pudo iniciar el dictado. Puede escribir normalmente.'; return; }
        const current = {recognition, control, seen: new Set(), stopping: false, limit: false};
        if (control.dataMode) dataTranscript.value = '';
        if (control.dataMode && transcriptPanel) transcriptPanel.open = true;
        session = current;
        preview.textContent = '';
        preview.hidden = true;
        recognition.lang = 'es-VE';
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;
        recognition.onstart = () => {
            if (session !== current) {
                try { recognition.abort(); } catch (_) { /* Cancelled during startup. */ }
                return;
            }
            if (current.stopping) return;
            status.textContent = `Escuchando: ${control.dataMode ? 'datos del formulario' : control.idleLabel === 'Dictar mejora' ? 'propuesta de mejora' : 'comentario, sugerencia o queja'}. Pulse «Detener dictado» al terminar.`;
        };
        recognition.onresult = event => {
            if (session !== current || current.limit) return;
            const interim = [];
            for (let index = event.resultIndex; index < event.results.length; index++) {
                const result = event.results[index];
                const text = String(result[0]?.transcript || '').trim();
                if (!text) continue;
                if (!result.isFinal) { interim.push(text); continue; }
                if (current.seen.has(index)) continue;
                current.seen.add(index);
                const field = control.field;
                const separator = field.value && !/\s$/.test(field.value) ? ' ' : '';
                const value = field.value + separator + text;
                if (field.maxLength >= 0 && value.length > field.maxLength) {
                    current.limit = true;
                    preview.textContent = text;
                    preview.hidden = false;
                    stop('El texto supera el límite del campo. La última frase no se añadió; puede copiarla de la vista previa y revisarla.');
                    return;
                }
                // Only final results are appended. Manual edits and earlier sessions remain intact.
                field.value = value;
                field.dispatchEvent(new Event('input', {bubbles: true}));
                field.dispatchEvent(new Event('change', {bubbles: true}));
            }
            preview.textContent = interim.join(' ');
            preview.hidden = !preview.textContent;
        };
        recognition.onerror = event => {
            if (session !== current) return;
            const messages = {
                'not-allowed': 'No se concedió permiso para usar el micrófono. Revise los permisos del navegador.',
                'service-not-allowed': 'El navegador no permite usar el servicio de dictado.',
                'audio-capture': 'No se encontró un micrófono disponible.',
                'no-speech': 'No se detectó voz. Pulse Dictar para intentarlo nuevamente.',
                'network': 'No se pudo conectar con el servicio de dictado. Revise su conexión o escriba el texto.',
                'language-not-supported': 'El servicio no admite el idioma de dictado configurado.',
                'aborted': 'Dictado cancelado. Se conserva el texto ya añadido.',
            };
            cancel(messages[event.error] || 'No fue posible reconocer la voz. Se conserva lo escrito y puede continuar manualmente.');
        };
        recognition.onend = () => {
            if (session !== current) return;
            finish(current);
            if (!current.limit) {
                preview.textContent = '';
                preview.hidden = true;
                status.textContent = 'Dictado finalizado. Revise el texto y pulse Guardar cuando el formulario esté completo.';
                if (control.dataMode) applyData();
            }
        };
        status.textContent = 'Solicitando acceso al micrófono…';
        sync();
        try { recognition.start(); }
        catch (_) { cancel('No se pudo iniciar el micrófono. Revise los permisos o escriba el texto.'); }
    };
    controls.forEach(control => control.button.addEventListener('click', () => {
        if (session?.control === control) stop();
        else start(control);
    }));
    applyDataButton?.addEventListener('click', () => { if (!session) applyData(); });
    clearDataButton?.addEventListener('click', () => {
        if (!dataTranscript || (session && !session.control.dataMode)) return;
        cancel('Dictado detenido.');
        dataTranscript.value = '';
        preview.textContent = '';
        preview.hidden = true;
        status.hidden = false;
        status.textContent = 'Se limpió la transcripción. Los campos del formulario no se borraron.';
    });
    const beforeSave = event => {
        if (!session) return;
        event.preventDefault();
        stop('Terminando el dictado. Revise el texto y vuelva a pulsar Guardar para enviar el formulario.');
    };
    form.addEventListener('submit', beforeSave);
    form.querySelectorAll('[type="submit"]').forEach(button => button.addEventListener('click', beforeSave));
    window.addEventListener('pagehide', () => cancel('Dictado detenido al salir de la página.'));
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) cancel('Dictado detenido al ocultar la página. Puede iniciarlo nuevamente cuando lo necesite.');
    });
    status.hidden = supported;
    status.textContent = !supported
        ? (window.isSecureContext === false ? 'El dictado requiere HTTPS o localhost. Puede escribir normalmente.' : 'Este navegador no ofrece reconocimiento de voz. Puede escribir normalmente.')
        : 'Pulse el botón del campo que desea dictar. Nada se guarda automáticamente.';
    sync();
});
