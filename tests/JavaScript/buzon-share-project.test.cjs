const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/buzon-share-project.js', 'utf8');

function fixture({plugin = true, present = true, logo = '/donantes/1/logo', donor = 'FUNDANA'} = {}) {
    function node(tag = 'span') {
        return {tag, children: [], dataset: {}, handlers: {}, hidden: false, textContent: '',
            append(...items) {this.children.push(...items);}, replaceChildren(...items) {this.children = items;},
            addEventListener(name, callback) {this.handlers[name] = callback;},
            dispatchEvent(event) {this.handlers[event.type]?.(event);},
        };
    }
    const select = node('select'), preview = node('div');
    select.options = [{value: '', dataset: {}}, {value: '1', dataset: {
        donorName: donor, projectCode: 'TEST-01', projectTitle: 'Proyecto primero', projectLogo: logo,
    }}, {value: '2', dataset: {donorName: 'OTRO DONANTE', projectCode: 'TEST-02', projectTitle: 'Proyecto segundo'}}];
    select.selectedIndex = 0;
    const section = {querySelector(selector) {return selector.includes('preview') ? preview : select;}};
    const pluginEvents = {};
    let config;
    const $ = element => element === select ? {select2(options) {
        config = options;
        return {on(names, callback) {for (const name of names.split(' ')) pluginEvents[name] = callback;}};
    }} : element;
    $.fn = {select2() {}};
    vm.runInNewContext(source, {URL, location: {href: 'https://example.test/buzon', origin: 'https://example.test'},
        Event: class {constructor(type, options) {this.type = type; Object.assign(this, options);}},
        window: {jQuery: plugin ? $ : undefined}, document: {
            createElement: node, addEventListener(name, callback) {callback();},
            querySelector() {return present ? section : null;},
        }});
    return {select, preview, config, pluginEvents, choose(index) {select.selectedIndex = index; select.dispatchEvent({type: 'change'});}};
}

test('renders donor logo and project identity in both dropdown rows and selection', () => {
    const h = fixture();
    assert.equal(h.preview.hidden, true);
    assert.equal(h.config.allowClear, true);
    for (const template of [h.config.templateResult, h.config.templateSelection]) {
        const row = template({id: '1', element: h.select.options[1]});
        const [avatar, copy] = row.children;
        assert.equal(avatar.children[1].tag, 'img');
        assert.equal(avatar.children[1].src, 'https://example.test/donantes/1/logo');
        assert.equal(avatar.children[1].alt, 'Logo de FUNDANA');
        assert.equal(copy.children[0].textContent, 'FUNDANA');
        assert.equal(copy.children[1].textContent, 'TEST-01 — Proyecto primero');
    }
    assert.equal(h.config.templateResult({text: 'Seleccione un proyecto'}), 'Seleccione un proyecto');
});

test('selected donor preview switches and clears together with the native select', () => {
    const h = fixture();
    h.choose(1);
    assert.equal(h.preview.hidden, false);
    assert.equal(h.preview.children[0].children[1].children[0].textContent, 'FUNDANA');
    h.choose(2);
    assert.equal(h.preview.children.length, 1);
    assert.equal(h.preview.children[0].children[1].children[0].textContent, 'OTRO DONANTE');
    h.choose(0);
    assert.equal(h.preview.hidden, true);
    assert.equal(h.preview.children.length, 0);
});

test('Select2 selection and clearing each notify native QR listeners once', () => {
    const h = fixture();
    let changes = 0;
    const original = h.select.handlers.change;
    h.select.handlers.change = event => {changes++; original(event);};
    h.select.selectedIndex = 1;
    h.pluginEvents['select2:select.buzonShare']();
    assert.equal(changes, 1); assert.equal(h.preview.hidden, false);
    h.select.selectedIndex = 0;
    h.pluginEvents['select2:clear.buzonShare']();
    assert.equal(changes, 2); assert.equal(h.preview.hidden, true);
});

test('failed or missing image retains donor name and initials without broken artwork', () => {
    const h = fixture(); h.choose(1);
    const [fallback, image] = h.preview.children[0].children[0].children;
    image.handlers.error();
    assert.equal(image.hidden, true); assert.equal(fallback.hidden, false); assert.equal(fallback.textContent, 'F');
    h.choose(2);
    assert.equal(h.preview.children[0].children[0].children.length, 1);
});

test('never requests remote or executable logo sources, and treats names as text', () => {
    for (const logo of ['https://other.example/logo.png', 'javascript:alert(1)', 'data:image/svg+xml,test']) {
        const h = fixture({logo, donor: '<img src=x onerror=alert(1)>'});
        h.choose(1);
        const [avatar, copy] = h.preview.children[0].children;
        assert.equal(avatar.children.length, 1);
        assert.equal(copy.children[0].textContent, '<img src=x onerror=alert(1)>');
    }
    assert.doesNotMatch(source, /innerHTML|fetch\(|\.submit\(|localStorage/);
});

test('native select preview works without Select2 and does not require a widget on other pages', () => {
    const h = fixture({plugin: false}); h.choose(1);
    assert.equal(h.preview.hidden, false);
    assert.equal(h.config, undefined);
    assert.doesNotThrow(() => fixture({present: false}));
});
