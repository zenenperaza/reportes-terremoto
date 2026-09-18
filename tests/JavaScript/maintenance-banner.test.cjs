const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/maintenance-banner.js', 'utf8');

function harness({present = true, observer = true} = {}) {
    let height = 88.2;
    const style = {};
    const listeners = {};
    let onResize;
    const banner = {getBoundingClientRect: () => ({height})};
    vm.runInNewContext(source, {
        document: {getElementById: () => present ? banner : null, body: {style: {setProperty: (key, value) => {style[key] = value;}}}},
        window: {
            ResizeObserver: observer ? class {
                constructor(callback) { onResize = callback; }
                observe(element) { assert.equal(element, banner); }
            } : undefined,
            addEventListener: (name, callback) => {listeners[name] = callback;},
        },
    });
    return {style, listeners, resize: (value) => { height = value; (onResize || listeners.resize)(); }};
}

test('reserves actual banner height and updates it when text wraps', () => {
    const state = harness();
    assert.equal(state.style['--maintenance-banner-height'], '89px');
    state.resize(164.5);
    assert.equal(state.style['--maintenance-banner-height'], '165px');
});
test('supports window resize without ResizeObserver', () => {
    const state = harness({observer: false});
    state.resize(120);
    assert.equal(state.style['--maintenance-banner-height'], '120px');
});
test('does nothing when the system reminder is absent', () => {
    const state = harness({present: false});
    assert.deepEqual(state.style, {});
    assert.deepEqual(state.listeners, {});
});
