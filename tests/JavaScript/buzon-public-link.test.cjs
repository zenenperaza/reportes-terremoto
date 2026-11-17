const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/buzon-public-link.js', 'utf8');

function harness({present = true, clipboard} = {}) {
    const field = () => ({value: '', hidden: false, handlers: {}, dataset: {}, addEventListener(name, fn) {this.handlers[name] = fn;},
        focus() {this.focused = true;}, select() {this.selected = true;}, removeAttribute(name) {delete this[name];}});
    const select = field(), panel = field(), input = field(), open = field(), copy = field(), status = field();
    select.options = [{dataset: {}}, {dataset: {publicUrl: 'https://example.test/buzon-publico/1/proyecto-a/2?signature=fake-a'}},
        {dataset: {publicUrl: 'https://example.test/buzon-publico/3/proyecto-b/2?signature=fake-b'}}];
    select.selectedIndex = 0;
    const fields = {'[data-public-project]': select, '[data-public-link-panel]': panel, '[data-public-link-input]': input,
        '[data-public-link-open]': open, '[data-public-link-copy]': copy, '[data-public-link-status]': status};
    vm.runInNewContext(source, {navigator: {clipboard}, document: {
        addEventListener(name, fn) {assert.equal(name, 'DOMContentLoaded'); fn();},
        querySelector() {return present ? {querySelector(selector) {return fields[selector];}} : null;},
    }});
    return {select, panel, input, open, copy, status};
}

test('starts with no link and switches only to server-supplied project links', () => {
    const h = harness();
    assert.equal(h.panel.hidden, true); assert.equal(h.copy.disabled, true); assert.equal(h.open.href, undefined);
    h.select.selectedIndex = 1; h.select.handlers.change();
    assert.match(h.input.value, /proyecto-a/); assert.equal(h.open.href, h.input.value); assert.equal(h.panel.hidden, false);
    h.select.selectedIndex = 2; h.select.handlers.change();
    assert.match(h.input.value, /proyecto-b/); assert.equal(h.copy.disabled, false);
    h.select.selectedIndex = 0; h.select.handlers.change();
    assert.equal(h.input.value, ''); assert.equal(h.open.href, undefined); assert.equal(h.panel.hidden, true);
});

test('copies the selected link without submitting data or rendering HTML', async () => {
    let copied;
    const h = harness({clipboard: {async writeText(value) {copied = value;}}});
    h.select.selectedIndex = 1; h.select.handlers.change();
    await h.copy.handlers.click();
    assert.equal(copied, h.input.value); assert.match(h.status.textContent, /Enlace copiado/);
    assert.doesNotMatch(source, /innerHTML|fetch\(|\.submit\(|localStorage|sessionStorage/);
});

test('clipboard failure or absence selects the link and explains how to copy manually', async () => {
    for (const clipboard of [undefined, {async writeText() {throw new Error('Denied');}}]) {
        const h = harness({clipboard}); h.select.selectedIndex = 2; h.select.handlers.change();
        await h.copy.handlers.click();
        assert.equal(h.input.focused, true); assert.equal(h.input.selected, true); assert.match(h.status.textContent, /manualmente/);
    }
});

test('does not copy an empty selection or change pages without the widget', async () => {
    let calls = 0;
    const h = harness({clipboard: {async writeText() {calls++;}}});
    await h.copy.handlers.click(); assert.equal(calls, 0);
    assert.doesNotThrow(() => harness({present: false}));
});
