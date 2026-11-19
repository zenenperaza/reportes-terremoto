const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/buzon-feedback.js', 'utf8');

function fixture({kind = '', nameValue = '', phoneValue = '', readOnly = false, present = true} = {}) {
    const field = value => ({value, required: false, handlers: {}, addEventListener(type, fn) {this.handlers[type] = fn;}});
    const name = field(nameValue), phone = field(phoneValue);
    const radios = ['suggestion', 'comment', 'complaint', 'request'].map(value => ({...field(value), checked: value === kind}));
    const labels = ['Nombre', 'Teléfono'].map(text => ({dataset: {buzonContactLabel: text}}));
    const contact = {dataset: {contactReadonly: readOnly ? '1' : '0'}, hidden: true, querySelectorAll() {return labels;}};
    const form = {querySelector(selector) {return selector === '[data-buzon-contact]' ? contact : selector.includes('full_name') ? name : phone;},
        querySelectorAll() {return radios;}};
    const windowEvents = {};
    vm.runInNewContext(source, {document: {addEventListener(type, fn) {fn();}, querySelectorAll() {return present ? [form] : []; }},
        window: {addEventListener(type, fn) {windowEvents[type] = fn;}}});
    return {name, phone, contact, labels, windowEvents, choose(kind) {
        radios.forEach(input => {input.checked = input.value === kind;});
        radios.find(input => input.value === kind).handlers.change();
    }};
}

test('empty anonymous feedback always shows both optional contact fields', () => {
    const h = fixture();
    assert.equal(h.contact.hidden, false);
    assert.equal(h.name.required, false); assert.equal(h.phone.required, false);
});

test('Solicitud keeps both contact fields visible and requires each, also on restored errors', () => {
    for (const initial of ['', 'request']) {
        const h = fixture({kind: initial});
        h.choose('request');
        assert.equal(h.contact.hidden, false);
        assert.equal(h.name.required, true); assert.equal(h.phone.required, true);
        assert.equal(h.labels[0].textContent, 'Nombre *'); assert.equal(h.labels[1].textContent, 'Teléfono *');
    }
});

test('other choices stop requiring contact and never erase already entered values', () => {
    const h = fixture({kind: 'request', nameValue: 'Nombre de prueba', phoneValue: '04140000000'});
    for (const kind of ['suggestion', 'comment', 'complaint']) {
        h.choose(kind);
        assert.equal(h.name.required, false); assert.equal(h.phone.required, false);
        assert.equal(h.contact.hidden, false);
        assert.equal(h.name.value, 'Nombre de prueba'); assert.equal(h.phone.value, '04140000000');
        assert.equal(h.labels[0].textContent, 'Nombre (opcional)');
    }
});

test('empty contact remains visible and optional for every non-request choice', () => {
    const h = fixture({kind: 'request'});
    for (const kind of ['suggestion', 'comment', 'complaint']) {
        h.choose(kind);
        assert.equal(h.contact.hidden, false);
        assert.equal(h.name.required, false); assert.equal(h.phone.required, false);
    }
});

test('historic data and read-only forms remain visible for all feedback types', () => {
    assert.equal(fixture({kind: 'comment', nameValue: 'Histórico'}).contact.hidden, false);
    assert.equal(fixture({kind: 'comment', phoneValue: '04140000000'}).contact.hidden, false);
    assert.equal(fixture({kind: 'comment', readOnly: true}).contact.hidden, false);
});

test('dictated contact values and browser restoration synchronize visibility and required state', () => {
    const h = fixture(); h.name.value = 'Nombre dictado'; h.name.handlers.input();
    assert.equal(h.contact.hidden, false);
    h.choose('request'); h.name.required = false; h.windowEvents.pageshow();
    assert.equal(h.name.required, true);
    assert.doesNotMatch(source, /fetch\(|\.submit\(|\.value\s*=(?!=)|innerHTML/);
    assert.doesNotThrow(() => fixture({present: false}));
});
