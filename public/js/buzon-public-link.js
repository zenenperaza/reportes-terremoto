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
    const drawQr = (url, code, projectId, organizationLogo = null, donorLogo = null) => {
        if (!qrPanel || !qrCanvas || !qrDownload) return;
        const encoder = globalThis.qrcodegen?.QrCode;
        if (!encoder) throw new Error('QR encoder unavailable');
        const qr = encoder.encodeText(url, organizationLogo ? encoder.Ecc.HIGH : encoder.Ecc.MEDIUM);
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
        if (organizationLogo) {
            const logoModules = Math.max(3, Math.floor(qr.size * 0.12));
            const logos = donorLogo ? [donorLogo, organizationLogo] : [organizationLogo];
            const gapModules = donorLogo ? 1 : 0;
            const plateModulesWide = logoModules * logos.length + gapModules + 2;
            const plateModulesHigh = logoModules + 2;
            const plateX = (border + Math.floor((qr.size - plateModulesWide) / 2)) * scale;
            const plateY = (border + Math.floor((qr.size - plateModulesHigh) / 2)) * scale;
            context.fillStyle = '#fff';
            context.fillRect(plateX, plateY, plateModulesWide * scale, plateModulesHigh * scale);
            context.imageSmoothingEnabled = true;
            logos.forEach((logo, index) => {
                const factor = Math.min(logoModules * scale / logo.naturalWidth, logoModules * scale / logo.naturalHeight);
                const width = Math.max(1, Math.round(logo.naturalWidth * factor));
                const height = Math.max(1, Math.round(logo.naturalHeight * factor));
                const boxX = plateX + scale + index * (logoModules + gapModules) * scale;
                const boxY = plateY + scale;
                context.drawImage(logo, Math.round(boxX + (logoModules * scale - width) / 2),
                    Math.round(boxY + (logoModules * scale - height) / 2), width, height);
            });
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
            const render = (organizationLogo = null, donorLogo = null) => {
                if (generation !== currentGeneration) return;
                try {drawQr(url, code, projectId, organizationLogo, donorLogo);}
                catch (_) {
                    clearQr();
                    if (organizationLogo) {
                        if (donorLogo) {
                            try {drawQr(url, code, projectId, organizationLogo); status.textContent = 'No se pudo incluir el logo del donante. El QR conserva el logo de ASONACOP.'; return;}
                            catch (_) {clearQr();}
                        }
                        try {drawQr(url, code, projectId); status.textContent = 'No se pudieron incluir los logos. Puede descargar el QR sin logos.'; return;}
                        catch (_) {clearQr();}
                    }
                    status.textContent = 'No fue posible generar el QR. Puede copiar y compartir el enlace.';
                }
            };
            const organizationUrl = section.dataset?.asonacopLogo;
            const donorUrl = option.dataset.projectLogo;
            if (!organizationUrl) {
                status.textContent = 'No se pudo cargar el logo de ASONACOP. El QR está disponible sin logos.';
                render(); return;
            }
            const cleanups = [];
            cancelLogo = () => cleanups.forEach(cleanup => cleanup());
            let organizationDone = false, donorDone = !donorUrl, finished = false;
            let organizationImage = null, donorImage = null;
            const finish = () => {
                if (finished || generation !== currentGeneration || !organizationDone || !donorDone) return;
                finished = true;
                cancelLogo();
                if (!organizationImage) {
                    status.textContent = 'No se pudo cargar el logo de ASONACOP. El QR está disponible sin logos.';
                    render();
                } else {
                    status.textContent = donorUrl && !donorImage
                        ? 'No se pudo cargar el logo del donante. El QR conserva el logo de ASONACOP.' : '';
                    render(organizationImage, donorImage);
                }
            };
            const loadLogo = (logoUrl, ready) => {
                try {
                    const address = new URL(logoUrl, location.href);
                    if (address.origin !== location.origin || !['https:', 'http:'].includes(address.protocol)) throw new Error('Invalid logo origin');
                    const image = new Image();
                    let completed = false;
                    const cleanup = () => {clearTimeout(timer); image.onload = null; image.onerror = null;};
                    const loaded = success => {
                        if (completed || generation !== currentGeneration) return;
                        completed = true; cleanup();
                        ready(success && image.naturalWidth && image.naturalHeight ? image : null);
                    };
                    const timer = setTimeout(() => loaded(false), 8000);
                    cleanups.push(cleanup);
                    image.onload = () => loaded(true);
                    image.onerror = () => loaded(false);
                    image.src = address.href;
                } catch (_) {ready(null);}
            };
            status.textContent = 'Preparando el QR con los logos de ASONACOP y del donante…';
            loadLogo(organizationUrl, image => {
                organizationImage = image; organizationDone = true;
                if (!image) donorDone = true;
                finish();
            });
            if (!organizationDone || organizationImage) {
                if (donorUrl) loadLogo(donorUrl, image => {donorImage = image; donorDone = true; finish();});
                else finish();
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
