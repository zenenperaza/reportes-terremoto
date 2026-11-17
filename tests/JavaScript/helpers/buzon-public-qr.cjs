const fs = require('node:fs');
const vm = require('node:vm');
const librarySource = fs.readFileSync('public/vendor/qrcodegen/qrcodegen.js', 'utf8');
const source = fs.readFileSync('public/js/buzon-public-link.js', 'utf8');

function fixture({library = true, contextAvailable = true, encodeFailure = false, pngFailure = false,
    code = 'VNZ/PCA202380/PD2023237', present = true, logoUrl = '', drawLogoFailure = false} = {}) {
    const field = () => ({value: '', hidden: false, dataset: {}, handlers: {}, attrs: {},
        addEventListener(name, fn) {this.handlers[name] = fn;}, removeAttribute(name) {delete this[name]; delete this.attrs[name];},
        setAttribute(name, value) {this.attrs[name] = value;}, focus() {}, select() {}});
    const select = field(), panel = field(), input = field(), open = field(), copy = field(), status = field();
    const qrPanel = field(), canvas = field(), download = field(), qrEmpty = field();
    const signedUrl = 'https://registros.example.test/buzon-publico/1/vnz-pca202380-pd2023237/7?signature=' + 'a'.repeat(64);
    select.options = [{value: '', dataset: {}}, {value: '1', dataset: {projectCode: code, publicUrl: signedUrl}},
        {value: '22', dataset: {projectCode: 'PROYECTO-OTRO', publicUrl: signedUrl.replace('/1/', '/22/').replace('signature=a', 'signature=b')}}];
    select.selectedIndex = 0;
    select.options[1].dataset.projectLogo = logoUrl;
    const context = {rectangles: [], texts: [], images: [], fillStyle: '', font: '',
        measureText(text) {return {width: [...text].length * 10};},
        fillRect(x, y, width, height) {this.rectangles.push({x, y, width, height, color: this.fillStyle});},
        fillText(text, x, y) {this.texts.push({text, x, y});},
        drawImage(image, x, y, width, height) {if (drawLogoFailure) throw new Error('Image draw failed'); this.images.push({image, x, y, width, height});}};
    canvas.getContext = () => contextAvailable ? context : null;
    canvas.toDataURL = format => {canvas.format = format; if (pngFailure) throw new Error('PNG failed'); return 'data:image/png;base64,TEST';};
    const fields = {'[data-public-project]': select, '[data-public-link-panel]': panel, '[data-public-link-input]': input,
        '[data-public-link-open]': open, '[data-public-link-copy]': copy, '[data-public-link-status]': status,
        '[data-public-qr-panel]': qrPanel, '[data-public-qr-canvas]': canvas, '[data-public-qr-download]': download,
        '[data-public-qr-empty]': qrEmpty};
    const images = [], timers = new Map(); let nextTimer = 0;
    const environment = vm.createContext({navigator: {}, URL, location: {href: 'https://registros.example.test/buzon', origin: 'https://registros.example.test'},
        Image: class {constructor() {this.naturalWidth = 160; this.naturalHeight = 100; images.push(this);}},
        setTimeout(fn) {timers.set(++nextTimer, fn); return nextTimer;}, clearTimeout(id) {timers.delete(id);}, document: {
        addEventListener(name, fn) {fn();},
        querySelector() {return present ? {querySelector(selector) {return fields[selector];}} : null;},
    }});
    const encoded = [], symbols = [];
    if (library) {
        vm.runInContext(librarySource, environment);
        const Encoder = environment.qrcodegen.QrCode;
        const encode = Encoder.encodeText;
        Encoder.encodeText = (text, ecc) => {
            encoded.push(text);
            if (encodeFailure) throw new Error('Encoding unavailable');
            const symbol = encode(text, ecc);
            symbols.push(symbol);
            return symbol;
        };
    }
    vm.runInContext(source, environment);
    function choose(index) {
        context.rectangles = []; context.texts = []; context.images = [];
        select.selectedIndex = index; select.value = select.options[index].value; select.handlers.change();
    }
    function pixels() {
        const rgba = new Uint8ClampedArray(canvas.width * canvas.height * 4);
        for (const rect of context.rectangles) {
            const value = rect.color === '#fff' ? 255 : 0;
            for (let y = rect.y; y < rect.y + rect.height; y++) for (let x = rect.x; x < rect.x + rect.width; x++) {
                const offset = (y * canvas.width + x) * 4;
                rgba[offset] = rgba[offset + 1] = rgba[offset + 2] = value; rgba[offset + 3] = 255;
            }
        }
        for (const image of context.images) {
            for (let y = image.y; y < image.y + image.height; y++) for (let x = image.x; x < image.x + image.width; x++) {
                const value = (x % 2 === 0) !== (y % 3 === 0) ? 20 : 240;
                const offset = (y * canvas.width + x) * 4;
                rgba[offset] = rgba[offset + 1] = rgba[offset + 2] = value; rgba[offset + 3] = 255;
            }
        }
        return rgba;
    }
    return {select, panel, input, open, copy, status, qrPanel, qrEmpty, canvas, download, context, encoded, symbols, choose, pixels, images, timers};
}

module.exports = {fixture, source, librarySource};
