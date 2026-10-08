const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/report-period-dates.js', 'utf8');
const all = {
    attention: {min: '2026-06-01', max: '2026-09-20'},
    registered: {min: '2026-06-30', max: '2026-10-05'},
};
const july = {
    attention: {min: '2026-06-01', max: '2026-07-27'},
    registered: {min: '2026-07-15', max: '2026-08-02'},
};
const empty = {attention: {min: null, max: null}, registered: {min: null, max: null}};

function element() {
    return {events: {}, attributes: {}, addEventListener(type, handler) {this.events[type] = handler;},
        setAttribute(name, value) {this.attributes[name] = value;}, getAttribute(name) {return this.attributes[name];},
        setCustomValidity(message) {this.validity = message;}, checkValidity() {return !this.validity;},
        reportValidity() {this.reportedInvalid = true;}, dispatchEvent(event) {this.events[event.type]?.(event);}};
}

function harness({periods = [], value = '', values = [], bounds = all, calendar = true, reported = '', beneficiary = false, services = false} = {}) {
    const select = {...element(), selectedOptions: periods.map(value => ({value}))};
    const error = {...element(), hidden: true};
    const retry = element();
    const orderError = {...element(), hidden: true};
    const submit = {...element(), disabled: false};
    const exportButton = element();
    const inputs = Array.from({length: services ? 2 : 4}, (_, index) => {
        const group = index < 2 ? 'attention' : 'registered';
        const help = {};
        const names = beneficiary || services ? ['from', 'to', 'included_from', 'included_to'] : ['attention_from', 'attention_to', 'registered_from', 'registered_to'];
        return {...element(), id: `date${index}`, name: names[index], value: values[index] ?? value,
            dataset: {dateGroup: group, dateMin: bounds[group].min || '', dateMax: bounds[group].max || ''},
            parentElement: {querySelector: () => help}, help, labels: [{textContent: 'Fecha'}],
        };
    });
    const form = {...element(), dataset: {dateBoundsUrl: '/fechas', dateAllowEqual: services ? '1' : '0'},
        querySelectorAll: selector => selector === '[type="submit"]' ? [submit] : inputs,
        querySelector: () => ({value: reported}),
        checkValidity: () => inputs.every(input => !input.validity),
    };
    const pickers = [];
    const window = calendar ? {flatpickr(input, options) {
        assert.equal(options.disableMobile, true);
        assert.equal(options.dateFormat, 'Y-m-d');
        const picker = {altInput: element(), close() {},
            set(config) {this.config = config;}, clear() {input.value = '';}, setDate(value) {input.value = value;}};
        pickers.push(picker);
        return picker;
    }} : {};
    const requests = [];
    const fetch = (url, options) => new Promise((resolve, reject) => requests.push({url, options, resolve, reject}));
    vm.runInNewContext(source, {document: {
        getElementById: id => ({'reporting-period': select, 'report-period-dates-error': error, 'report-period-dates-retry': retry, 'report-date-order-error': orderError})[id],
        querySelector: () => form, querySelectorAll: () => [exportButton],
    }, window, Event, fetch, AbortController, URLSearchParams});
    return {inputs, pickers, form, select, submit, error, retry, orderError, exportButton, requests,
        change(periods) {
            select.selectedOptions = periods.map(value => ({value}));
            return select.events.change();
        }, respond(bounds, request = requests.at(-1)) {
            request.resolve({ok: true, json: async () => bounds});
        },
    };
}

function blockedClick(h) {
    const event = {preventDefault() {this.prevented = true;}, stopImmediatePropagation() {this.stopped = true;}};
    h.exportButton.events.click(event);
    return event.prevented === true && event.stopped === true;
}

test('initial dates use separate actual server bounds without an extra request', () => {
    const h = harness();
    assert.equal(h.requests.length, 0);
    assert.equal(h.inputs[0].min, '2026-06-01');
    assert.equal(h.inputs[3].max, '2026-10-05');
    for (const picker of h.pickers) assert.equal(picker.config.enable[0](), true);
    assert.equal(h.pickers[0].altInput.attributes['aria-label'], 'Fecha');
    assert.match(h.inputs[0].help.textContent, /01\/06\/2026 al 20\/09\/2026/);
});

test('service dates accept real October period spanning November and clear September input', async () => {
    const h = harness({services: true, values: ['2026-09-07', ''], bounds: all});
    const pending = h.change(['2026-10']);
    assert.equal(blockedClick(h), true);
    h.respond({attention: {min: '2026-10-02', max: '2026-11-04'}});
    await pending;
    assert.equal(h.inputs[0].min, '2026-10-02');
    assert.equal(h.inputs[1].max, '2026-11-04');
    assert.equal(h.inputs[0].value, '');
    assert.match(h.inputs[0].help.textContent, /02\/10\/2026 al 04\/11\/2026/);
    h.inputs[0].value = '2026-11-02';
    h.inputs[1].value = '2026-11-03';
    assert.equal(blockedClick(h), false);
});

test('service dates allow a single-day period but still reject reversed dates', async () => {
    const h = harness({services: true});
    const pending = h.change(['2026-10']);
    h.respond({attention: {min: '2026-10-06', max: '2026-10-06'}});
    await pending;
    h.inputs[0].value = h.inputs[1].value = '2026-10-06';
    assert.equal(blockedClick(h), false);
    h.inputs[1].value = '2026-10-05';
    assert.equal(blockedClick(h), true);
});

test('period selection loads real dates across months and clears only incompatible date filters', async () => {
    const h = harness({value: '2026-08-01', reported: '1'});
    const pending = h.change(['2026-07']);
    assert.equal(h.submit.disabled, true);
    assert.equal(h.pickers[0].altInput.disabled, true);
    assert.equal(blockedClick(h), true);
    const params = new URLSearchParams(h.requests[0].url.split('?')[1]);
    assert.deepEqual(params.getAll('reporting_period[]'), ['2026-07']);
    assert.equal(params.get('reported'), '1');
    h.respond(july);
    await pending;
    assert.equal(h.inputs[0].min, '2026-06-01');
    assert.equal(h.inputs[0].max, '2026-07-27');
    assert.equal(h.inputs[0].value, '');
    assert.equal(h.inputs[2].min, '2026-07-15');
    assert.equal(h.inputs[2].max, '2026-08-02');
    assert.equal(h.inputs[2].value, '2026-08-01');
    assert.equal(h.submit.disabled, false);
    h.inputs[3].value = '2026-08-02';
    h.inputs[3].dispatchEvent(new Event('change'));
    assert.equal(blockedClick(h), false);
    assert.equal(h.error.hidden, true);
});

test('multiple periods use a continuous range, including days in intervening months', async () => {
    const h = harness({value: '2026-08-15'});
    const pending = h.change(['2026-07', '2026-09']);
    h.respond(all);
    await pending;
    assert.equal(h.inputs[0].value, '2026-08-15');
    assert.equal(h.pickers[0].config.enable[0](), true);
    assert.equal(h.inputs[0].validity, '');
    assert.deepEqual(new URLSearchParams(h.requests[0].url.split('?')[1]).getAll('reporting_period[]'), ['2026-07', '2026-09']);
});

test('export synchronization runs after all four fields have their final enabled state', async () => {
    const h = harness({value: '2026-07-20'});
    let synchronized = false;
    h.form.events.change = () => {
        synchronized = true;
        assert.equal(h.inputs.every(input => !input.disabled), true);
        assert.equal(h.inputs[2].max, '2026-08-02');
    };
    const pending = h.change(['2026-07']);
    h.respond(july);
    await pending;
    assert.equal(synchronized, true);
});

test('unassigned and clearing selection both query real records instead of removing bounds', async () => {
    const h = harness();
    const pending = h.change(['unassigned']);
    h.respond({attention: {min: '2026-06-29', max: '2026-06-29'}, registered: {min: '2026-06-30', max: '2026-06-30'}});
    await pending;
    assert.equal(h.inputs[0].max, '2026-06-29');
    const next = h.change([]);
    h.respond(all);
    await next;
    assert.equal(h.inputs[0].max, '2026-09-20');
    assert.deepEqual(new URLSearchParams(h.requests[1].url.split('?')[1]).getAll('reporting_period[]'), ['']);
});

test('empty periods disable dates but allow requesting the empty report', async () => {
    const h = harness({value: '2026-07-20'});
    const pending = h.change(['2028-02']);
    h.respond(empty);
    await pending;
    for (const input of h.inputs) {
        assert.equal(input.disabled, true);
        assert.equal(input.value, '');
        assert.match(input.help.textContent, /Sin fechas/);
    }
    assert.equal(h.pickers[0].altInput.disabled, true);
    assert.equal(h.submit.disabled, false);
});

test('failed requests stay blocked, and retry loads the currently selected periods', async () => {
    const h = harness();
    const pending = h.change(['2026-07']);
    h.requests[0].reject(new Error('Offline'));
    await pending;
    assert.equal(h.error.hidden, false);
    assert.equal(h.submit.disabled, true);
    let prevented = false;
    h.form.events.submit({preventDefault() {prevented = true;}});
    assert.equal(prevented, true);
    assert.equal(blockedClick(h), true);
    const retry = h.retry.events.click();
    h.respond(july);
    await retry;
    assert.equal(h.error.hidden, true);
    assert.equal(h.submit.disabled, false);
});

test('stale responses cannot replace a newer selection', async () => {
    const h = harness();
    const first = h.change(['2026-07']);
    const second = h.change([]);
    assert.equal(h.requests[0].options.signal.aborted, true);
    h.respond(all, h.requests[1]);
    await second;
    h.respond(july, h.requests[0]);
    await first;
    assert.equal(h.inputs[0].max, '2026-09-20');
    assert.equal(h.submit.disabled, false);
});

test('native fallback and calendar visible input enforce actual bounds on submission', () => {
    for (const calendar of [true, false]) {
        const h = harness({bounds: july, calendar});
        h.inputs[0].value = '2026-07-28';
        let prevented = false;
        h.form.events.submit({preventDefault() {prevented = true;}});
        assert.equal(prevented, true);
        assert.match(h.inputs[0].validity, /rango/);
        if (calendar) assert.match(h.pickers[0].altInput.validity, /rango/);
    }
});

test('a successful date refresh does not unlock filters while locations are still loading', async () => {
    const h = harness();
    h.form.dataset.locationsBlocked = '1';
    const pending = h.change(['2026-07']);
    h.respond(july);
    await pending;
    assert.equal(h.submit.disabled, true);
});

test('malformed date response remains blocked until retry succeeds', async () => {
    const h = harness();
    const pending = h.change(['2026-07']);
    h.respond({attention: {min: '2026-07-27', max: '2026-06-01'}});
    await pending;
    assert.equal(h.error.hidden, false);
    assert.equal(h.submit.disabled, true);
});

test('attention and registration reject inverted and equal endpoints before submit or Excel', () => {
    for (const calendar of [true, false]) {
        for (const beneficiary of [true, false]) {
            for (const pair of [[0, 1], [2, 3]]) {
                for (const end of ['2026-07-01', '2026-07-05']) {
                    const h = harness({calendar, beneficiary});
                    const [from, to] = pair;
                    h.inputs[from].value = '2026-07-05';
                    h.inputs[to].value = end;
                    h.inputs[to].dispatchEvent(new Event('change'));
                    assert.equal(h.orderError.hidden, false);
                    assert.match(h.inputs[to].validity, /de (atención|registro) «Desde» debe ser anterior a «Hasta»/);
                    if (calendar) assert.equal(h.pickers[to].altInput.validity, h.inputs[to].validity);
                    let prevented = false;
                    h.form.events.submit({preventDefault() {prevented = true;}});
                    assert.equal(prevented, true);
                    assert.equal(blockedClick(h), true);
                    assert.equal((calendar ? h.pickers[to].altInput : h.inputs[to]).reportedInvalid, true);
                }
            }
        }
    }
});

test('changing either endpoint or clearing it removes stale range errors', () => {
    for (const calendar of [true, false]) {
        for (const pair of [[0, 1], [2, 3]]) {
            const [from, to] = pair;
            const h = harness({calendar});
            h.inputs[from].value = '2026-07-05';
            h.inputs[to].value = '2026-07-01';
            h.inputs[to].dispatchEvent(new Event('change'));
            h.inputs[from].value = '2026-06-30';
            h.inputs[from].dispatchEvent(new Event('change'));
            assert.equal(h.inputs[to].validity, '');
            assert.equal(h.orderError.hidden, true);
            assert.equal(blockedClick(h), false);
            h.inputs[from].value = '2026-07-05';
            h.inputs[from].dispatchEvent(new Event('change'));
            assert.equal(h.orderError.hidden, false);
            h.inputs[to].value = '';
            h.inputs[to].dispatchEvent(new Event('change'));
            assert.equal(h.orderError.hidden, true);
            assert.equal(h.inputs[to].validity, '');
        }
    }
});

test('a typed calendar range is checked on blur and a valid range is allowed', () => {
    const h = harness({values: ['2026-07-01', '2026-07-05', '2026-07-10', '2026-07-15']});
    assert.equal(h.orderError.hidden, true);
    assert.equal(blockedClick(h), false);
    h.inputs[0].value = '2026-07-20';
    h.pickers[0].altInput.events.blur();
    assert.equal(h.orderError.hidden, false);
    h.inputs[1].value = '2026-07-25';
    h.pickers[1].altInput.events.blur();
    assert.equal(h.orderError.hidden, true);
});
