const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/period-closing-notice.js', 'utf8');

function harness({present = true, supported = true, failToOpen = false} = {}) {
    let focused = '', closed = 0;
    const button = {hidden: true, handlers: {}, addEventListener(type, fn) {this.handlers[type] = fn;}, focus() {focused = 'button';}};
    const notice = {hidden: false, querySelector() {return button;}, before(node) {this.insertedBefore = node;}};
    const dialog = {open: false, attrs: {}, handlers: {}, setAttribute(name, value) {this.attrs[name] = value;},
        append(child) {this.child = child;}, before(node) {this.restoredBefore = node;},
        addEventListener(type, fn) {this.handlers[type] = fn;}, remove() {this.removed = true;},
        close() {this.open = false; closed++;}};
    if (supported) dialog.showModal = () => {if (failToOpen) throw new Error('Unavailable'); dialog.open = true;};
    vm.runInNewContext(source, {document: {
        addEventListener(type, fn) {assert.equal(type, 'DOMContentLoaded'); fn();},
        getElementById(id) {assert.equal(id, 'period-closing-notice'); return present ? notice : null;},
        activeElement: {focus() {focused = 'previous';}},
        createElement(tag) {assert.equal(tag, 'dialog'); return dialog;},
    }});
    return {notice, button, dialog, get focused() {return focused;}, get closed() {return closed;}};
}

test('opens an accessible modal without submitting forms or persisting browser data', () => {
    const h = harness();
    assert.equal(h.dialog.open, true);
    assert.equal(h.dialog.child, h.notice);
    assert.equal(h.dialog.attrs['aria-labelledby'], 'period-closing-title');
    assert.equal(h.dialog.attrs['aria-describedby'], 'period-closing-description');
    assert.equal(h.focused, 'button');
    assert.equal(h.button.hidden, false);
    assert.doesNotMatch(source, /fetch\(|localStorage|sessionStorage|requestSubmit\(|\.submit\(/);
});

test('Entendido closes the notice and restores focus to the page', () => {
    const h = harness(); h.button.handlers.click();
    assert.equal(h.closed, 1); assert.equal(h.notice.hidden, true);
    assert.equal(h.dialog.removed, true); assert.equal(h.focused, 'previous');
});

test('Escape dismisses the notice without navigating away from the form', () => {
    const h = harness(); let prevented = false;
    h.dialog.handlers.cancel({preventDefault() {prevented = true;}});
    assert.equal(prevented, true); assert.equal(h.closed, 1); assert.equal(h.notice.hidden, true);
});

test('unsupported dialog keeps the warning visible with a working dismissal button', () => {
    const h = harness({supported: false});
    assert.equal(h.notice.hidden, false); assert.equal(h.dialog.child, undefined);
    h.button.handlers.click(); assert.equal(h.notice.hidden, true); assert.equal(h.closed, 0);
});

test('failed modal opening restores the same visible notice instead of hiding it', () => {
    const h = harness({failToOpen: true});
    assert.equal(h.dialog.restoredBefore, h.notice); assert.equal(h.notice.hidden, false);
    assert.equal(h.dialog.removed, true);
    h.button.handlers.click(); assert.equal(h.notice.hidden, true);
});

test('ordinary pages without a notice are untouched', () => {
    const h = harness({present: false});
    assert.equal(h.dialog.child, undefined); assert.equal(h.button.hidden, true);
});
