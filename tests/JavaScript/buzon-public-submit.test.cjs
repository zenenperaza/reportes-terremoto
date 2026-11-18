const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/buzon-public-submit.js', 'utf8');

function harness({present = true, hasButton = true, hasStatus = true} = {}) {
    const button = {textContent: 'Enviar opinión', disabled: false};
    const status = {hidden: true, textContent: ''};
    const events = {}, attributes = {};
    const form = {
        querySelector(selector) {return selector.includes('submit') ? (hasButton ? button : null) : (hasStatus ? status : null);},
        addEventListener(name, callback) {events[name] = callback;},
        setAttribute(name, value) {attributes[name] = value;},
        removeAttribute(name) {delete attributes[name];},
    };
    const windowEvents = {};
    vm.runInNewContext(source, {
        document: {addEventListener(name, callback) {callback();}, querySelector() {return present ? form : null;}},
        window: {addEventListener(name, callback) {windowEvents[name] = callback;}},
    });
    const submit = (defaultPrevented = false) => {
        const event = {defaultPrevented, preventDefault() {this.defaultPrevented = true;}};
        events.submit(event);
        return event;
    };
    return {button, status, attributes, submit, windowEvents};
}

test('permits the first native submit and prevents subsequent submits', () => {
    const h = harness();
    assert.equal(h.submit().defaultPrevented, false);
    assert.equal(h.button.disabled, true);
    assert.equal(h.button.textContent, 'Enviando…');
    assert.equal(h.attributes['aria-busy'], 'true');
    assert.equal(h.status.hidden, false);
    assert.match(h.status.textContent, /Enviando su opinión/);
    assert.equal(h.submit().defaultPrevented, true);
    assert.equal(h.submit().defaultPrevented, true);
    assert.doesNotMatch(source, /fetch\(|\.submit\(|requestSubmit\(|registrada satisfactoriamente|innerHTML/);
});

test('does not disable the form if another handler has cancelled submission', () => {
    const h = harness();
    h.submit(true);
    assert.equal(h.button.disabled, false);
    assert.equal(h.submit().defaultPrevented, false);
});

test('restores controls after returning through browser back-forward cache', () => {
    const h = harness();
    h.submit();
    h.windowEvents.pageshow();
    assert.equal(h.button.disabled, false);
    assert.equal(h.button.textContent, 'Enviar opinión');
    assert.equal(h.attributes['aria-busy'], undefined);
    assert.equal(h.status.hidden, true);
    assert.equal(h.status.textContent, '');
    assert.equal(h.submit().defaultPrevented, false);
});

test('is safe with missing widget, button or optional status', () => {
    assert.doesNotThrow(() => harness({present: false}));
    assert.doesNotThrow(() => harness({hasButton: false}));
    const h = harness({hasStatus: false});
    assert.doesNotThrow(() => {h.submit(); h.windowEvents.pageshow();});
});
