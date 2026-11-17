const {test} = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const {fixture, source, librarySource} = require('./helpers/buzon-public-qr.cjs');

test('uses the pinned local QR encoder, retaining its license and without third-party network calls', () => {
    assert.match(librarySource, /MIT License/);
    assert.equal(crypto.createHash('sha256').update(librarySource).digest('hex'), '6a1116192ed1dd67fa1bf31e77f5817103d71c23bbac24c382e698b7668bdd01');
    assert.doesNotMatch(librarySource + source, /fetch\(|XMLHttpRequest|https:\/\/api\./);
});

test('QR remains hidden until a project is chosen and encodes the full exact signed link', () => {
    const h = fixture();
    assert.equal(h.qrPanel.hidden, true); assert.equal(h.download.href, undefined); assert.equal(h.encoded.length, 0);
    assert.equal(h.qrEmpty.hidden, false);
    h.choose(1);
    assert.equal(h.encoded[0], h.input.value);
    assert.match(h.encoded[0], /signature=a{64}$/);
    assert.equal(h.qrPanel.hidden, false); assert.equal(h.canvas.format, 'image/png');
    assert.equal(h.qrEmpty.hidden, true);
    assert.match(h.download.href, /^data:image\/png/);
    assert.equal(h.download.download, 'buzon-VNZ-PCA202380-PD2023237-1.png');
    assert.match(h.canvas.attrs['aria-label'], /VNZ\/PCA202380\/PD2023237/);
});

test('renders crisp black modules with an intact four-module white quiet zone and a separate caption', () => {
    const h = fixture(); h.choose(1);
    const qr = h.symbols[0], border = 4, scale = h.canvas.width / (qr.size + border * 2);
    assert.equal(Number.isInteger(scale), true); assert.ok(scale >= 4);
    const background = h.context.rectangles[0];
    assert.deepEqual(background, {x: 0, y: 0, width: h.canvas.width, height: h.canvas.height, color: '#fff'});
    const dark = h.context.rectangles.slice(1);
    let expected = 0;
    for (let y = 0; y < qr.size; y++) for (let x = 0; x < qr.size; x++) if (qr.getModule(x, y)) expected++;
    assert.equal(dark.length, expected);
    for (const r of dark) {
        assert.equal(r.color, '#000'); assert.equal(r.width, scale); assert.equal(r.height, scale);
        assert.ok(r.x >= border * scale && r.y >= border * scale);
        assert.ok(r.x + r.width <= h.canvas.width - border * scale);
        assert.ok(r.y + r.height <= h.canvas.width - border * scale);
        assert.equal(r.x % scale, 0); assert.equal(r.y % scale, 0);
    }
    assert.equal(h.context.imageSmoothingEnabled, false);
    assert.ok(h.context.texts.every(t => t.y > h.canvas.width && t.y < h.canvas.height));
    assert.ok(h.context.texts.some(t => t.text === 'Buzón ASONACOP'));
    assert.ok(h.context.texts.some(t => t.text === 'VNZ/PCA202380/PD2023237'));
});

test('switching projects replaces the QR, label and filename, and clearing removes the old download', () => {
    const h = fixture(); h.choose(1); const first = h.encoded[0]; h.choose(2);
    assert.notEqual(h.encoded[1], first); assert.equal(h.encoded[1], h.input.value);
    assert.equal(h.download.download, 'buzon-PROYECTO-OTRO-22.png');
    assert.ok(h.context.texts.some(t => t.text === 'PROYECTO-OTRO'));
    h.choose(0);
    assert.equal(h.qrPanel.hidden, true); assert.equal(h.download.href, undefined); assert.equal(h.download.download, undefined);
    assert.equal(h.qrEmpty.hidden, false);
    assert.equal(h.canvas.width, 1); assert.equal(h.canvas.height, 1);
});

test('long or unsafe project codes wrap below the QR and do not produce unsafe filenames', () => {
    const code = 'Ñ/PROYECTO:<prueba>\\'.repeat(12);
    const h = fixture({code}); h.choose(1);
    assert.equal(h.qrPanel.hidden, false); assert.match(h.download.download, /^buzon-[a-zA-Z0-9_-]+-1\.png$/);
    const lines = h.context.texts.slice(1, -1);
    assert.ok(lines.length > 1); assert.equal(lines.map(t => t.text).join(''), code);
});

test('missing library, canvas support or PNG export leaves the link usable and clears stale QR data', () => {
    for (const options of [{library: false}, {contextAvailable: false}, {encodeFailure: true}, {pngFailure: true}]) {
        const h = fixture(options); h.choose(1);
        assert.equal(h.panel.hidden, false); assert.ok(h.input.value); assert.equal(h.copy.disabled, false);
        assert.equal(h.qrPanel.hidden, true); assert.equal(h.download.href, undefined);
        assert.equal(h.qrEmpty.hidden, false);
        assert.match(h.status.textContent, /Puede copiar y compartir el enlace/);
    }
});

test('a project logo loads from the same origin and is drawn small in the center with high correction', () => {
    const h = fixture({logoUrl: 'https://registros.example.test/proyectos/1/logo?v=sample'}); h.choose(1);
    assert.equal(h.qrPanel.hidden, true); assert.equal(h.images.length, 1); assert.equal(h.copy.disabled, false);
    assert.equal(h.images[0].src, 'https://registros.example.test/proyectos/1/logo?v=sample');
    h.images[0].onload();
    assert.equal(h.qrPanel.hidden, false); assert.equal(h.timers.size, 0);
    assert.equal(h.symbols[0].errorCorrectionLevel.ordinal, 3);
    assert.equal(h.encoded[0], h.input.value);
    assert.equal(h.context.images.length, 1);
    const icon = h.context.images[0], plate = h.context.rectangles.at(-1);
    assert.equal(plate.color, '#fff'); assert.ok(icon.x > plate.x && icon.y > plate.y);
    assert.ok(icon.x + icon.width < plate.x + plate.width); assert.ok(icon.y + icon.height < plate.y + plate.height);
    assert.ok(plate.width < h.canvas.width * 0.20);
    assert.ok(Math.abs(icon.width / icon.height - 160 / 100) < 0.08);
    assert.match(h.download.href, /^data:image\/png/);
});

test('load errors, timeout and canvas errors fall back to a usable plain QR', () => {
    for (const failure of ['load', 'timeout', 'draw']) {
        const h = fixture({logoUrl: '/proyectos/1/logo', drawLogoFailure: failure === 'draw'}); h.choose(1);
        if (failure === 'load') h.images[0].onerror();
        if (failure === 'timeout') [...h.timers.values()][0]();
        if (failure === 'draw') h.images[0].onload();
        assert.equal(h.qrPanel.hidden, false); assert.match(h.download.href, /^data:image\/png/);
        assert.equal(h.context.images.length, 0); assert.match(h.status.textContent, /sin logo/);
    }
});

test('late image completion cannot replace the QR after switching projects or clearing selection', () => {
    const h = fixture({logoUrl: '/proyectos/1/logo'}); h.choose(1);
    const late = h.images[0].onload;
    h.choose(2); const url = h.input.value; const filename = h.download.download;
    late(); assert.equal(h.input.value, url); assert.equal(h.download.download, filename);
    assert.equal(h.context.images.length, 0); assert.equal(h.timers.size, 0);
    h.choose(1); const later = h.images[1].onload; h.choose(0); later();
    assert.equal(h.qrPanel.hidden, true); assert.equal(h.download.href, undefined);
});

test('foreign-origin or non-HTTP logo sources are not requested', () => {
    for (const logoUrl of ['https://external.example.test/logo.png', 'data:image/png;base64,fake', 'javascript:alert(1)']) {
        const h = fixture({logoUrl}); h.choose(1);
        assert.equal(h.images.length, 0); assert.equal(h.qrPanel.hidden, false);
        assert.match(h.status.textContent, /sin logo/);
    }
});
