const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/system-live-maintenance.js', 'utf8');
const serverTime = Date.parse('2026-10-08T15:00:00Z');

function node() {
    const classes = new Set();
    return {hidden: true, textContent: '', classList: {toggle(name, enabled) {enabled ? classes.add(name) : classes.delete(name);}},
        classes, getBoundingClientRect: () => ({height: 119.2}), set innerHTML(value) {throw new Error('Unsafe HTML');}};
}

function harness({administrator = false, oldBanner = false, skew = 0, first = {}} = {}) {
    let time = serverTime + skew;
    let response = {enabled: false, scheduled: true, starts_at: '2026-10-08T15:05:00Z', server_now: '2026-10-08T15:00:00Z', message: 'Guarde su trabajo.', ...first};
    let failure = false;
    let status = 200;
    const requests = [];
    const intervals = {};
    const events = {};
    const dispatched = [];
    const fields = {'[data-live-title]': node(), '[data-live-message]': node(), '[data-live-countdown]': node()};
    const notice = {...node(), dataset: {administrator: administrator ? '1' : '0', statusUrl: '/sistema/estado'}, querySelector: selector => fields[selector]};
    const old = oldBanner ? {...node(), hidden: false} : null;
    const count = node(), footerCount = node(), connection = node(), body = node();
    const offsets = {};
    body.style = {setProperty: (key, value) => {offsets[key] = value;}};
    const document = {
        body, hidden: false,
        getElementById: id => id === 'system-live-notice' ? notice : id === 'system-maintenance-banner' ? old : null,
        querySelector: selector => selector === 'meta[name="csrf-token"]' ? {content: 'test-csrf'} : selector === '[data-connected-count]' ? count : selector === '[data-live-connection]' ? connection : null,
        querySelectorAll: selector => selector === '[data-connected-count]' ? [count, footerCount] : [],
        addEventListener: (name, callback) => {events[name] = callback;},
        dispatchEvent: event => {dispatched.push(event);},
    };
    vm.runInNewContext(source, {
        document, Date: class extends Date {static now() {return time;}}, AbortController,
        CustomEvent: class {constructor(type, options) {this.type = type; this.detail = options.detail;}},
        fetch: async (url, options) => {
            requests.push({url, options});
            if (failure) throw new Error('offline');
            return {status, ok: status === 200, json: async () => response};
        },
        setInterval: (callback, ms) => {intervals[ms] = callback;}, setTimeout: () => 1, clearTimeout: () => {},
        window: {addEventListener: (name, callback) => {events[name] = callback;}},
    });
    return {notice, old, fields, count, footerCount, connection, body, offsets, requests, intervals, events, dispatched,
        advance: ms => {time += ms;}, respond: data => {response = {...response, ...data};},
        fail: enabled => {failure = enabled;}, status: value => {status = value;},
        flush: () => new Promise(resolve => setImmediate(resolve))};
}

test('shows live countdown without reloading, using server time despite a skewed PC clock', async () => {
    const state = harness({skew: 12 * 60 * 60 * 1000});
    await state.flush();
    assert.equal(state.notice.hidden, false);
    assert.equal(state.fields['[data-live-countdown]'].textContent, 'Tiempo para guardar: 5:00');
    assert.equal(state.fields['[data-live-message]'].textContent, 'Guarde su trabajo.');
    assert.equal(state.offsets['--maintenance-banner-height'], '120px');
    state.advance(61000);
    state.intervals[1000]();
    assert.equal(state.fields['[data-live-countdown]'].textContent, 'Tiempo para guardar: 3:59');
    assert.equal(state.requests[0].options.method, 'POST');
    assert.equal(state.requests[0].options.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(state.requests[0].options.credentials, 'same-origin');
    assert.equal(state.dispatched[0].type, 'sia:maintenance-state');
    assert.equal(state.dispatched[0].detail.scheduled, true);
});

test('deadline checks server state and announces lock without discarding the open page', async () => {
    const state = harness();
    await state.flush();
    state.respond({enabled: true, scheduled: false, server_now: '2026-10-08T15:05:00Z'});
    state.advance(300000);
    state.intervals[1000]();
    await state.flush();
    assert.equal(state.notice.classes.has('is-blocked'), true);
    assert.match(state.fields['[data-live-message]'].textContent, /No cierre esta página/);
    assert.equal(state.requests.length, 2);
});

test('cancellation removes notice, rescheduling updates it, messages are text not HTML', async () => {
    const state = harness();
    await state.flush();
    state.respond({scheduled: false, starts_at: null});
    await state.intervals[15000]();
    assert.equal(state.notice.hidden, true);
    assert.equal(state.body.classes.has('has-maintenance-banner'), false);
    state.respond({scheduled: true, starts_at: '2026-10-08T15:10:00Z', message: '<img src=x onerror=alert(1)>'});
    await state.events.focus();
    assert.equal(state.notice.hidden, false);
    assert.equal(state.fields['[data-live-countdown]'].textContent, 'Tiempo para guardar: 10:00');
    assert.equal(state.fields['[data-live-message]'].textContent, '<img src=x onerror=alert(1)>');
});

test('non-administrators receive automatic footer count updates', async () => {
    const state = harness({first: {connected_users: 7, scheduled: false}});
    await state.flush();
    assert.equal(state.footerCount.textContent, '7');
    assert.equal(state.notice.hidden, true);
    state.respond({connected_users: 3});
    state.intervals[15000]();
    await state.flush();
    assert.equal(state.footerCount.textContent, '3');
});

test('failed polling keeps previous warning and reports that the count may be stale', async () => {
    const state = harness({administrator: true, first: {connected_users: 7}});
    await state.flush();
    assert.equal(state.count.textContent, '7');
    assert.equal(state.footerCount.textContent, '7');
    state.fail(true);
    await state.intervals[15000]();
    assert.equal(state.count.textContent, '7');
    assert.equal(state.notice.hidden, false);
    assert.match(state.connection.textContent, /desactualizado/);
    state.fail(false);
    state.respond({connected_users: 3});
    await state.events.online();
    assert.equal(state.count.textContent, '3');
    assert.equal(state.footerCount.textContent, '3');
});

test('existing admin lock banner is retained then hidden on unlock, without duplicate notices', async () => {
    const state = harness({administrator: true, oldBanner: true, first: {enabled: true, scheduled: false}});
    await state.flush();
    assert.equal(state.notice.hidden, true);
    assert.equal(state.old.hidden, false);
    state.respond({enabled: false});
    await state.intervals[15000]();
    assert.equal(state.old.hidden, true);
    assert.equal(state.body.classes.has('has-maintenance-banner'), false);
});

test('expired sessions stop polling without navigating away or clearing unsaved work', async () => {
    const state = harness();
    await state.flush();
    state.status(419);
    await state.intervals[15000]();
    const requests = state.requests.length;
    await state.events.focus();
    assert.equal(state.requests.length, requests);
    assert.equal(state.notice.hidden, false);
    assert.match(state.connection.textContent, /sesión expiró/);
});
