const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/theme-switcher.js', 'utf8');

function harness(savedTheme = null, hasButton = true) {
    const storage = new Map(savedTheme ? [['data-layout-mode', savedTheme]] : []);
    const attributes = {};
    const buttonAttributes = {};
    const iconClasses = new Set();
    let click;
    const logos = Array.from({length: 4}, () => ({
        attributes: {'data-logo-light': '/icons/asonacop-app.png?v=1', 'data-logo-dark': '/icons/asonacop-app-dark.png?v=2'},
        getAttribute(name) { return this.attributes[name]; },
        setAttribute(name, value) { this.attributes[name] = value; },
    }));
    const button = {
        setAttribute(name, value) { buttonAttributes[name] = value; },
        querySelector() { return {classList: {toggle(name, enabled) { enabled ? iconClasses.add(name) : iconClasses.delete(name); }}}; },
        addEventListener(event, callback) { assert.equal(event, 'click'); click = callback; },
    };
    vm.runInNewContext(source, {
        document: {
            documentElement: {getAttribute: name => attributes[name], setAttribute: (name, value) => { attributes[name] = value; }},
            querySelector: () => hasButton ? button : null,
            querySelectorAll: selector => { assert.equal(selector, 'img[data-logo-light][data-logo-dark]'); return logos; },
        },
        window: {sessionStorage: {getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value)}},
    });
    return {logos, storage, attributes, buttonAttributes, iconClasses, click: () => click({preventDefault() {}})};
}

function assertTheme(state, theme) {
    assert.equal(state.attributes['data-layout-mode'], theme);
    assert.equal(state.storage.get('data-layout-mode'), theme);
    assert.equal(state.buttonAttributes['aria-pressed'], theme === 'dark' ? 'true' : 'false');
    for (const logo of state.logos) {
        assert.equal(logo.attributes.src, logo.attributes[`data-logo-${theme}`]);
    }
}

test('uses light logos on first visit', () => assertTheme(harness(), 'light'));
test('restores dark logos on page load', () => assertTheme(harness('dark'), 'dark'));
test('switches every logo in both directions and preserves accessible toggle state', () => {
    const state = harness('light');
    state.click();
    assertTheme(state, 'dark');
    assert.ok(state.iconClasses.has('bx-sun'));
    state.click();
    assertTheme(state, 'light');
    assert.ok(state.iconClasses.has('bx-moon'));
});
test('does not alter guest pages without a theme toggle', () => {
    const state = harness(null, false);
    assert.deepEqual(state.attributes, {});
    assert.ok(state.logos.every(logo => !logo.attributes.src));
});
