const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/general-report-locations.js', 'utf8');

function element(value = '') {
    return {value, disabled: false, hidden: true, options: [], selectedOptions: [], handlers: {},
        addEventListener(name, callback) { this.handlers[name] = callback; },
        replaceChildren(...options) { this.options = options; this.value = options[0]?.value || ''; },
        add(option) { this.options.push(option); },
    };
}

function harness(useSelect2 = false) {
    const state = element(), municipality = element('11'), parish = element('111');
    state.selectedOptions = [{value: '1'}, {value: '2'}];
    const submit = element(), error = element(), retry = element(), form = element();
    form.dataset = {locationsUrl: '/informes-generales/ubicaciones'};
    form.querySelector = () => submit;
    const elements = {'general-report-filters': form, 'general_state_id': state, 'general_municipality_id': municipality,
        'general_parish_id': parish, 'general-locations-error': error, 'general-locations-retry': retry};
    const requests = [];
    let select2Options;
    const window = {};
    if (useSelect2) {
        window.jQuery = target => ({select2(options) {
            assert.equal(target, state);
            select2Options = options;
            return {on(name, callback) { state.handlers[name] = callback; }};
        }});
        window.jQuery.fn = {select2() {}};
    }
    vm.runInNewContext(source, {
        document: {getElementById: id => elements[id], addEventListener: (name, callback) => callback()},
        window, URLSearchParams, AbortController,
        Option: function (text, value) { this.text = text; this.value = value; },
        fetch: (url, options) => new Promise(resolve => requests.push({url, options,
            finish: (data, ok = true) => resolve({ok, json: async () => data})})),
    });
    return {state, municipality, parish, submit, error, retry, form, requests, select2Options};
}

const all = {municipalities: [{id: 11, name: 'Municipio A'}, {id: 22, name: 'Municipio B'}],
    parishes: [{id: 111, name: 'Parroquia A'}, {id: 222, name: 'Parroquia B'}]};

test('multiple states are sent together and dependent selections are reset', async () => {
    const h = harness(true);
    assert.equal(h.select2Options.closeOnSelect, false);
    const pending = h.state.handlers.change();
    const params = new URL(h.requests[0].url, 'https://app.test').searchParams;
    assert.deepEqual(params.getAll('state_id[]'), ['1', '2']);
    assert.equal(params.has('municipality_id'), false);
    assert.equal(h.submit.disabled, true);
    h.requests[0].finish(all);
    await pending;
    assert.equal(h.municipality.value, '');
    assert.equal(h.parish.value, '');
    assert.equal(h.municipality.options.length, 3);
    assert.equal(h.parish.options.length, 3);
    assert.equal(h.submit.disabled, false);
});

test('municipality narrows parishes and is preserved while parish selection is cleared', async () => {
    const h = harness();
    h.municipality.value = '22';
    const pending = h.municipality.handlers.change();
    assert.equal(new URL(h.requests[0].url, 'https://app.test').searchParams.get('municipality_id'), '22');
    h.requests[0].finish({...all, parishes: [all.parishes[1]]});
    await pending;
    assert.equal(h.municipality.value, '22');
    assert.equal(h.parish.value, '');
    assert.equal(h.parish.options[1].value, '222');
});

test('clearing states requests all locations and stale responses cannot overwrite the latest selection', async () => {
    const h = harness();
    const first = h.state.handlers.change();
    h.state.selectedOptions = [];
    const second = h.state.handlers.change();
    assert.equal(h.requests[0].options.signal.aborted, true);
    assert.equal(new URL(h.requests[1].url, 'https://app.test').searchParams.has('state_id[]'), false);
    h.requests[1].finish(all);
    await second;
    h.requests[0].finish({municipalities: [], parishes: []});
    await first;
    assert.equal(h.municipality.options.length, 3);
});

test('failed loading prevents submitting an incomplete filter and retry restores controls', async () => {
    const h = harness();
    const pending = h.state.handlers.change();
    h.requests[0].finish({}, false);
    await pending;
    assert.equal(h.error.hidden, false);
    assert.equal(h.submit.disabled, true);
    let prevented = false;
    h.form.handlers.submit({preventDefault() { prevented = true; }});
    assert.equal(prevented, true);
    const retry = h.retry.handlers.click();
    h.requests[1].finish(all);
    await retry;
    assert.equal(h.error.hidden, true);
    assert.equal(h.submit.disabled, false);
    assert.equal(h.municipality.disabled, false);
});
