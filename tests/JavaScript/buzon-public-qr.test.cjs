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

const organizationLogoUrl = '/assets/images/asonacop/logo.png?v=test';

test('draws ASONACOP on the left and the donor on the right in a white centered plate', () => {
    const h = fixture({organizationLogoUrl, logoUrl: '/donantes/1/logo'}); h.choose(1);
    assert.equal(h.qrPanel.hidden, true); assert.equal(h.images.length, 2); assert.equal(h.copy.disabled, false);
    assert.equal(h.images[0].src, 'https://registros.example.test' + organizationLogoUrl);
    h.images[0].naturalWidth = 150; h.images[0].naturalHeight = 89;
    h.images[0].onload();
    assert.equal(h.qrPanel.hidden, true);
    h.images[1].onload();
    assert.equal(h.qrPanel.hidden, false); assert.equal(h.timers.size, 0);
    assert.equal(h.symbols[0].errorCorrectionLevel.ordinal, 3);
    assert.equal(h.encoded[0], h.input.value);
    assert.equal(h.context.images.length, 2);
    const [organization, donor] = h.context.images, plate = h.context.rectangles.at(-1);
    assert.equal(organization.image, h.images[0]); assert.equal(donor.image, h.images[1]);
    assert.ok(organization.x + organization.width < donor.x);
    assert.equal(plate.color, '#fff');
    for (const icon of [organization, donor]) {
        assert.ok(icon.x > plate.x && icon.y > plate.y);
        assert.ok(icon.x + icon.width < plate.x + plate.width);
        assert.ok(icon.y + icon.height < plate.y + plate.height);
    }
    assert.ok(plate.width < h.canvas.width * 0.30);
    assert.ok(plate.height < h.canvas.width * 0.18);
    assert.ok(Math.abs((plate.x + plate.width / 2) - h.canvas.width / 2) < 8);
    assert.ok(Math.abs((plate.y + plate.height / 2) - h.canvas.width / 2) < 8);
    assert.ok(Math.abs(organization.width / organization.height - 150 / 89) < 0.08);
    assert.ok(Math.abs(donor.width / donor.height - 160 / 100) < 0.08);
    assert.match(h.download.href, /^data:image\/png/);
});

test('completion order never swaps the organization and donor', () => {
    const h = fixture({organizationLogoUrl, logoUrl: '/donantes/1/logo'}); h.choose(1);
    h.images[1].onload(); assert.equal(h.qrPanel.hidden, true);
    h.images[0].onload();
    assert.equal(h.context.images[0].image, h.images[0]);
    assert.equal(h.context.images[1].image, h.images[1]);
    assert.ok(h.context.images[0].x < h.context.images[1].x);
});

test('projects without a donor logo show only ASONACOP', () => {
    const h = fixture({organizationLogoUrl}); h.choose(1);
    assert.equal(h.images.length, 1); h.images[0].onload();
    assert.equal(h.qrPanel.hidden, false); assert.equal(h.context.images.length, 1);
    assert.equal(h.context.images[0].image, h.images[0]);
    assert.equal(h.symbols[0].errorCorrectionLevel.ordinal, 3);
});

test('donor failure or timeout retains ASONACOP and a usable PNG', () => {
    for (const failure of ['load', 'timeout']) {
        const h = fixture({organizationLogoUrl, logoUrl: '/donantes/1/logo'}); h.choose(1);
        h.images[0].onload();
        if (failure === 'load') h.images[1].onerror();
        else [...h.timers.values()][0]();
        assert.equal(h.qrPanel.hidden, false); assert.match(h.download.href, /^data:image\/png/);
        assert.equal(h.context.images.length, 1); assert.equal(h.context.images[0].image, h.images[0]);
        assert.match(h.status.textContent, /conserva el logo de ASONACOP/);
    }
});

test('organization load failure or timeout never renders the donor by itself', () => {
    for (const failure of ['load', 'timeout']) {
        const h = fixture({organizationLogoUrl, logoUrl: '/donantes/1/logo'}); h.choose(1);
        h.images[1].onload();
        if (failure === 'load') h.images[0].onerror();
        else [...h.timers.values()][0]();
        assert.equal(h.context.images.length, 0); assert.equal(h.qrPanel.hidden, false);
        assert.match(h.status.textContent, /ASONACOP.*sin logos/);
        assert.equal(h.timers.size, 0);
    }
});

test('canvas failures try ASONACOP alone, then fall back to an unbranded QR if necessary', () => {
    for (const failAll of [false, true]) {
        const h = fixture({organizationLogoUrl, logoUrl: '/donantes/1/logo',
            drawLogoFailure: failAll || (image => image.src.includes('/donantes/'))});
        h.choose(1); h.images[0].onload(); h.images[1].onload();
        assert.equal(h.qrPanel.hidden, false); assert.match(h.download.href, /^data:image\/png/);
        assert.equal(h.context.images.length, failAll ? 0 : 1);
        if (!failAll) assert.equal(h.context.images[0].image, h.images[0]);
    }
});

test('organization failure cancels the pending donor and ignores its late completion', () => {
    const h = fixture({organizationLogoUrl, logoUrl: '/donantes/1/logo'}); h.choose(1);
    const lateDonor = h.images[1].onload;
    h.images[0].onerror();
    assert.equal(h.qrPanel.hidden, false); assert.equal(h.context.images.length, 0);
    assert.equal(h.images[1].onload, null); assert.equal(h.timers.size, 0);
    const renders = h.encoded.length;
    lateDonor();
    assert.equal(h.encoded.length, renders); assert.equal(h.context.images.length, 0);
});

test('late completion cannot replace either logo after switching or clearing projects', () => {
    const h = fixture({organizationLogoUrl, logoUrl: '/donantes/1/logo'}); h.choose(1);
    const lateOrganization = h.images[0].onload, lateDonor = h.images[1].onload;
    h.choose(2);
    lateOrganization(); lateDonor(); assert.equal(h.qrPanel.hidden, true);
    h.images[2].onload();
    const url = h.input.value, filename = h.download.download;
    lateDonor(); assert.equal(h.input.value, url); assert.equal(h.download.download, filename);
    assert.equal(h.context.images.length, 1); assert.equal(h.timers.size, 0);
    h.choose(1); const later = h.images[3].onload; h.choose(0); later();
    assert.equal(h.qrPanel.hidden, true); assert.equal(h.download.href, undefined);
    assert.equal(h.timers.size, 0);
});

test('missing or unsafe organization source never requests the donor or exposes it alone', () => {
    for (const organizationLogoUrl of ['', 'https://external.example.test/logo.png', 'data:image/png;base64,fake', 'javascript:alert(1)']) {
        const h = fixture({organizationLogoUrl, logoUrl: '/donantes/1/logo'}); h.choose(1);
        assert.equal(h.images.length, 0); assert.equal(h.qrPanel.hidden, false);
        assert.match(h.status.textContent, /sin logos/);
    }
});

test('unsafe donor sources are ignored without requesting third-party images', () => {
    for (const logoUrl of ['https://external.example.test/logo.png', 'data:image/png;base64,fake', 'javascript:alert(1)']) {
        const h = fixture({organizationLogoUrl, logoUrl}); h.choose(1);
        assert.equal(h.images.length, 1); h.images[0].onload();
        assert.equal(h.qrPanel.hidden, false); assert.equal(h.context.images.length, 1);
        assert.match(h.status.textContent, /ASONACOP/);
    }
});
