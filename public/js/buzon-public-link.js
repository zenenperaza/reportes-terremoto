document.addEventListener('DOMContentLoaded', () => {
    const section = document.querySelector('[data-buzon-public-link]');
    if (!section) return;
    const select = section.querySelector('[data-public-project]');
    const panel = section.querySelector('[data-public-link-panel]');
    const input = section.querySelector('[data-public-link-input]');
    const open = section.querySelector('[data-public-link-open]');
    const copy = section.querySelector('[data-public-link-copy]');
    const status = section.querySelector('[data-public-link-status]');
    const qrPanel = section.querySelector('[data-public-qr-panel]');
    const qrCanvas = section.querySelector('[data-public-qr-canvas]');
    const qrDownload = section.querySelector('[data-public-qr-download]');
    const qrEmpty = section.querySelector('[data-public-qr-empty]');
    let generation = 0;
    let cancelLogo = () => {};
    const clearQr = () => {
        if (qrPanel) qrPanel.hidden = true;
        if (qrEmpty) qrEmpty.hidden = false;
        if (qrDownload) {
            qrDownload.removeAttribute('href');
            qrDownload.removeAttribute('download');
        }
        if (qrCanvas) {qrCanvas.width = 1; qrCanvas.height = 1;}
    };
    const drawQr = (url, code, projectId, logo = null) => {
        if (!qrPanel || !qrCanvas || !qrDownload) return;
        const encoder = globalThis.qrcodegen?.QrCode;
        if (!encoder) throw new Error('QR encoder unavailable');
        const qr = encoder.encodeText(url, logo ? encoder.Ecc.HIGH : encoder.Ecc.MEDIUM);
        const border = 4; // White quiet zone, never covered by the caption.
        const scale = Math.max(4, Math.ceil(480 / (qr.size + border * 2)));
        const qrPixels = (qr.size + border * 2) * scale;
        qrCanvas.width = qrPixels;
        const context = qrCanvas.getContext('2d');
        if (!context) throw new Error('Canvas unavailable');
        context.font = '18px sans-serif';
        const lines = [];
        let line = '';
        for (const letter of code) {
            if (line && context.measureText(line + letter).width > qrPixels - 40) {lines.push(line); line = '';}
            line += letter;
        }
        if (line) lines.push(line);
        qrCanvas.height = qrPixels + 90 + lines.length * 24;
        context.imageSmoothingEnabled = false;
        context.fillStyle = '#fff';
        context.fillRect(0, 0, qrCanvas.width, qrCanvas.height);
        context.fillStyle = '#000';
        for (let y = 0; y < qr.size; y++) for (let x = 0; x < qr.size; x++) {
            if (qr.getModule(x, y)) context.fillRect((x + border) * scale, (y + border) * scale, scale, scale);
        }
        if (logo) {
            const logoModules = Math.max(3, Math.floor(qr.size * 0.12));
            const plateModules = logoModules + 2;
            const plateStart = Math.floor((qr.size - plateModules) / 2);
            const plateX = (border + plateStart) * scale;
            const plateWidth = plateModules * scale;
            context.fillStyle = '#fff';
            context.fillRect(plateX, plateX, plateWidth, plateWidth);
            const factor = Math.min(logoModules * scale / logo.naturalWidth, logoModules * scale / logo.naturalHeight);
            const width = Math.max(1, Math.round(logo.naturalWidth * factor));
            const height = Math.max(1, Math.round(logo.naturalHeight * factor));
            context.imageSmoothingEnabled = true;
            context.drawImage(logo, Math.round(plateX + (plateWidth - width) / 2), Math.round(plateX + (plateWidth - height) / 2), width, height);
        }
        context.fillStyle = '#000';
        context.textAlign = 'center';
        context.font = 'bold 22px sans-serif';
        context.fillText('Buzón ASONACOP', qrPixels / 2, qrPixels + 32);
        context.font = '18px sans-serif';
        lines.forEach((text, index) => context.fillText(text, qrPixels / 2, qrPixels + 60 + index * 24));
        context.font = '16px sans-serif';
        context.fillText('Escanee para enviar su opinión', qrPixels / 2, qrCanvas.height - 16);
        const png = qrCanvas.toDataURL('image/png');
        if (!png.startsWith('data:image/png')) throw new Error('PNG unavailable');
        qrDownload.href = png;
        const safeCode = code.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 100);
        qrDownload.download = `buzon-${safeCode || 'proyecto'}-${projectId}.png`;
        qrCanvas.setAttribute('aria-label', `Código QR del Buzón del proyecto ${code}`);
        qrPanel.hidden = false;
        if (qrEmpty) qrEmpty.hidden = true;
    };
    const update = () => {
        cancelLogo();
        const currentGeneration = ++generation;
        const option = select.options[select.selectedIndex];
        const url = option?.dataset.publicUrl || '';
        clearQr();
        input.value = url;
        panel.hidden = !url;
        copy.disabled = !url;
        if (url) open.href = url;
        else open.removeAttribute('href');
        status.textContent = '';
        if (url) {
            const code = option.dataset.projectCode || `Proyecto ${select.value}`;
            const projectId = select.value;
            const render = (logo = null) => {
                if (generation !== currentGeneration) return;
                try {drawQr(url, code, projectId, logo);}
                catch (_) {
                    clearQr();
                    if (logo) {
                        try {drawQr(url, code, projectId); status.textContent = 'No se pudo incluir el logo. Puede descargar el QR sin logo.'; return;}
                        catch (_) {clearQr();}
                    }
                    status.textContent = 'No fue posible generar el QR. Puede copiar y compartir el enlace.';
                }
            };
            const logoUrl = option.dataset.projectLogo;
            if (!logoUrl) {render(); return;}
            try {
                const address = new URL(logoUrl, location.href);
                if (address.origin !== location.origin || !['https:', 'http:'].includes(address.protocol)) throw new Error('Invalid logo origin');
                const image = new Image();
                let completed = false;
                const finish = (success) => {
                    if (completed || generation !== currentGeneration) return;
                    completed = true;
                    cancelLogo();
                    if (success && image.naturalWidth && image.naturalHeight) {status.textContent = ''; render(image);}
                    else {status.textContent = 'No se pudo cargar el logo. El QR está disponible sin logo.'; render();}
                };
                const timer = setTimeout(() => finish(false), 8000);
                cancelLogo = () => {clearTimeout(timer); image.onload = null; image.onerror = null;};
                image.onload = () => finish(true);
                image.onerror = () => finish(false);
                status.textContent = 'Preparando el QR con el logo del donante…';
                image.src = address.href;
            } catch (_) {
                status.textContent = 'No se pudo cargar el logo. El QR está disponible sin logo.';
                render();
            }
        }
    };
    select.addEventListener('change', update);
    copy.addEventListener('click', async () => {
        if (!input.value) return;
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(input.value);
            status.textContent = 'Enlace copiado. Puede compartirlo con las personas de este proyecto.';
        } catch (_) {
            input.focus(); input.select();
            status.textContent = 'Seleccione y copie el enlace manualmente (Ctrl + C o mantener pulsado en el teléfono).';
        }
    });
    update();
});
