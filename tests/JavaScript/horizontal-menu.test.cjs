const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/horizontal-menu.js', 'utf8');

function harness(isMobile = false, layout = 'horizontal') {
    const classes = new Set();
    const iconClasses = new Set();
    const attributes = {};
    const handlers = {};
    const media = {matches: isMobile};
    let focused = false;
    const button = {
        setAttribute: (name, value) => { attributes[name] = value; },
        querySelector: () => ({classList: {toggle: (name, enabled) => enabled ? iconClasses.add(name) : iconClasses.delete(name)}}),
        addEventListener: (type, callback, capture) => {
            assert.equal(capture, true);
            handlers[type] = callback;
        },
        focus: () => { focused = true; },
    };
    vm.runInNewContext(source, {
        document: {
            documentElement: {getAttribute: () => layout},
            body: {classList: {toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name)}},
            getElementById: id => id === 'main-navigation' ? {} : button,
            addEventListener: (type, callback) => { handlers[type] = callback; },
        },
        window: {
            matchMedia: query => { assert.equal(query, '(max-width: 1024px)'); return media; },
            addEventListener: (type, callback) => { handlers[type] = callback; },
        },
    });
    return {
        classes, iconClasses, attributes, handlers,
        click() {
            let stopped = false;
            handlers.click({preventDefault() {}, stopImmediatePropagation() { stopped = true; }});
            assert.ok(stopped);
        },
        resize(mobile) { media.matches = mobile; handlers.resize(); },
        escape() { handlers.keydown({key: 'Escape'}); },
        focused: () => focused,
    };
}

test('desktop button hides and restores the navigation', () => {
    const state = harness();
    assert.equal(state.attributes['aria-expanded'], 'true');
    state.click();
    assert.ok(state.classes.has('horizontal-menu-hidden'));
    assert.equal(state.attributes['aria-expanded'], 'false');
    assert.equal(state.attributes['aria-label'], 'Mostrar menú');
    state.click();
    assert.ok(!state.classes.has('horizontal-menu-hidden'));
    assert.equal(state.attributes['aria-expanded'], 'true');
});

test('mobile uses template menu class and Escape closes it', () => {
    const state = harness(true);
    assert.equal(state.attributes['aria-expanded'], 'false');
    state.click();
    assert.ok(state.classes.has('menu'));
    assert.ok(!state.classes.has('horizontal-menu-hidden'));
    state.escape();
    assert.ok(!state.classes.has('menu'));
    assert.equal(state.attributes['aria-expanded'], 'false');
    assert.ok(state.focused());
});

test('resizing preserves desktop choice and resets mobile drawer on breakpoint changes', () => {
    const state = harness();
    state.click();
    state.resize(false);
    assert.ok(state.classes.has('horizontal-menu-hidden'));
    state.resize(true);
    assert.ok(!state.classes.has('horizontal-menu-hidden'));
    state.click();
    state.resize(true);
    assert.ok(state.classes.has('menu'));
    state.resize(false);
    assert.ok(!state.classes.has('menu'));
    assert.ok(state.classes.has('horizontal-menu-hidden'));
    state.resize(true);
    assert.equal(state.attributes['aria-expanded'], 'false');
});

test('does not replace handlers for other layouts', () => {
    assert.deepEqual(harness(false, 'vertical').handlers, {});
});

test('shows three lines when visible and the arrow when hidden on desktop and mobile', () => {
    for (const mobile of [false, true]) {
        const state = harness(mobile);
        const assertIcon = () => assert.equal(state.iconClasses.has('open'), state.attributes['aria-expanded'] === 'false');
        assertIcon();
        state.click();
        assertIcon();
        state.click();
        assertIcon();
        state.resize(!mobile);
        assertIcon();
        state.escape();
        assertIcon();
    }
});
