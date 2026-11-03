const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/beneficiary-id-autofill.js', 'utf8');
function element(extra = {}) {
    return {value: '', dataset: {}, attributes: {}, events: {},
        addEventListener(name, handler) {this.events[name] = handler;},
        dispatchEvent(event) {this.events[event.type]?.(event);},
        setAttribute(name, value) {this.attributes[name] = value;}, ...extra};
}
function harness({enabled = true} = {}) {
    const input = element({dataset: {beneficiaryLookup: '/beneficiarios/buscar-cedula', lookupEnabled: enabled ? '1' : '0'}});
    const form = element(), status = element();
    const fields = Object.fromEntries(['full_name', 'age', 'sex', 'nationality', 'phone', 'disability', 'ethnicity', 'pregnant_lactating',
        'caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship', 'is_recurrent', 'has_informed_consent'].map(name => [name, element()]));
    for (const [name, values] of Object.entries({sex: ['', 'Mujer', 'Hombre'], nationality: ['', 'Venezolana', 'Colombiana'],
        disability: ['', 'Ninguna', 'Auditiva'], ethnicity: ['', 'Ninguna'], pregnant_lactating: ['', 'Ninguna', 'Sí'],
        caregiver_relationship: ['', 'Madre', 'Padre', 'Abuela', 'Otro']})) {
        fields[name].options = values.map(value => ({value, disabled: false}));
    }
    let timer = null;
    const requests = [];
    vm.runInNewContext(source, {Event, AbortController, document: {
        addEventListener(_, callback) {callback();},
        querySelector(selector) {return selector === '[data-beneficiary-lookup]' ? input : {content: 'TEST-CSRF'};},
        getElementById: id => ({'report-form': form, 'beneficiary-lookup-status': status})[id] || fields[id.replace('beneficiary_', '')],
    }, setTimeout(callback) {timer = callback; return 1;}, clearTimeout() {timer = null;},
    fetch: (url, options) => new Promise((resolve, reject) => requests.push({url, options, resolve, reject})),
    });
    return {input, form, status, fields, requests,
        type(value) {input.value = value; input.dispatchEvent(new Event('input'));},
        blur() {return input.events.blur();},
        flush() {const pending = timer; timer = null; return pending?.();},
        reset() {form.dispatchEvent(new Event('beneficiary-entry-reset'));},
        async respond(result, request = requests.at(-1), ok = true) {request.resolve({ok, json: async () => result}); await new Promise(resolve => setImmediate(resolve));},
    };
}
const person = {full_name: 'MARÍA PÉREZ', age: 34, sex: 'Mujer', nationality: 'Colombiana', phone: '04140000000',
    disability: 'Ninguna', ethnicity: 'Ninguna', pregnant_lactating: 'Ninguna', caregiver_national_id: null, caregiver_full_name: null, caregiver_relationship: null};
const match = profile => ({matched: true, ambiguous: false, beneficiary: profile});

test('lookup debounces typing, posts only the ID and never copies consent or recurrence', async () => {
    const h = harness(); h.fields.is_recurrent.value = '0'; h.fields.has_informed_consent.value = '0';
    h.type('12'); h.flush(); assert.equal(h.requests.length, 0);
    h.type('V-12.345.678'); assert.equal(h.requests.length, 0); assert.equal(h.form.dataset.beneficiaryLookupPending, '1');
    h.flush();
    assert.equal(h.requests.length, 1);
    assert.equal(h.requests[0].options.method, 'POST');
    assert.equal(h.requests[0].options.cache, 'no-store');
    assert.deepEqual(JSON.parse(h.requests[0].options.body), {national_id: 'V12345678'});
    await h.respond(match({...person, is_recurrent: true, has_informed_consent: true, proyecto_id: 999}));
    assert.equal(h.fields.full_name.value, 'MARÍA PÉREZ'); assert.equal(h.fields.nationality.value, 'Colombiana');
    assert.equal(h.fields.is_recurrent.value, '0'); assert.equal(h.fields.has_informed_consent.value, '0');
    assert.equal(h.form.dataset.beneficiaryLookupPending, '0');
    h.blur(); assert.equal(h.requests.length, 1);
});

test('blur runs immediately and manual corrections made while loading are preserved', async () => {
    const h = harness(); h.type('12345678'); const pending = h.blur();
    h.fields.phone.value = 'CORRECCIÓN MANUAL';
    await h.respond(match(person)); await pending;
    assert.equal(h.fields.phone.value, 'CORRECCIÓN MANUAL'); assert.equal(h.fields.full_name.value, 'MARÍA PÉREZ');
});

test('changing IDs clears only unchanged autofilled values and ignores old asynchronous responses', async () => {
    const h = harness(); h.type('12345678'); h.flush(); await h.respond(match(person));
    h.fields.full_name.value = 'NOMBRE CORREGIDO';
    h.type('87654321'); assert.equal(h.fields.full_name.value, 'NOMBRE CORREGIDO'); assert.equal(h.fields.phone.value, '');
    h.flush(); const old = h.requests.at(-1);
    h.type('99887766'); h.flush(); const latest = h.requests.at(-1);
    assert.equal(old.options.signal.aborted, true);
    await h.respond(match({...person, phone: 'CORRECTO'}), latest);
    await h.respond(match({...person, phone: 'ANTIGUO'}), old);
    assert.equal(h.fields.phone.value, 'CORRECTO');
});

test('unknown or conflicting IDs do not fill fields and never block manual entry', async () => {
    for (const result of [{matched: false, ambiguous: false}, {matched: false, ambiguous: true}]) {
        const h = harness(); h.fields.full_name.value = 'Dato manual'; h.type('12345678'); h.flush();
        await h.respond(result);
        assert.equal(h.fields.full_name.value, 'Dato manual'); assert.equal(h.form.dataset.beneficiaryLookupPending, '0');
        assert.match(h.status.textContent, /manualmente/);
    }
});

test('reset and edit invalidate in-flight queries so a previous beneficiary cannot populate the next one', async () => {
    const h = harness(); h.type('12345678'); h.flush(); const request = h.requests[0];
    h.input.value = ''; h.reset(); await h.respond(match(person), request);
    assert.equal(h.fields.full_name.value, '');
    h.form.dataset.beneficiaryEditing = '1'; h.reset(); h.input.value = '12345678'; h.blur();
    assert.equal(h.requests.length, 1);
});

test('missing permissions disable lookup without disabling manual entry; network failures can be retried', async () => {
    const disabled = harness({enabled: false}); disabled.type('12345678'); disabled.flush(); disabled.blur();
    assert.equal(disabled.requests.length, 0);
    const h = harness(); h.type('12345678'); h.flush(); await h.respond({}, h.requests[0], false);
    assert.equal(h.form.dataset.beneficiaryLookupPending, '0'); assert.match(h.status.textContent, /manualmente/);
    const pending = h.blur(); await h.respond(match(person)); await pending;
    assert.equal(h.fields.full_name.value, 'MARÍA PÉREZ');
});

test('minor caregiver data is included and unexpected nationality options are left blank for review', async () => {
    const h = harness(); h.type('12345678'); h.flush();
    await h.respond(match({...person, age: 17, nationality: 'Nacionalidad no disponible', caregiver_national_id: '00123456', caregiver_full_name: 'CUIDADORA', caregiver_relationship: 'Madre'}));
    assert.equal(h.fields.caregiver_national_id.value, '00123456');
    assert.equal(h.fields.caregiver_full_name.value, 'CUIDADORA'); assert.equal(h.fields.nationality.value, '');
    assert.equal(h.fields.caregiver_relationship.value, 'Madre');
});

test('autofilling a previous free-text relationship leaves the selector blank for a current valid choice', async () => {
    const h = harness(); h.type('12345678'); h.flush();
    await h.respond(match({...person, age: 17, caregiver_relationship: 'Vecina de confianza'}));
    assert.equal(h.fields.caregiver_relationship.value, '');
});

test('voice queries immediately and waits for the same in-flight ID without duplicate requests', async () => {
    const h = harness(); h.type('12345678'); h.flush();
    let finished = false;
    const pending = h.form.beneficiaryLookup.forVoice('12345678').then(result => {finished = true; return result;});
    await Promise.resolve(); assert.equal(finished, false); assert.equal(h.requests.length, 1);
    await h.respond(match(person)); const result = await pending;
    assert.equal(result.status, 'matched'); assert.ok(result.fields.includes('full_name'));
    await h.form.beneficiaryLookup.forVoice('12345678'); assert.equal(h.requests.length, 1);
});

test('voice uses existing permissions, supports no-ID, and ignores cancelled queries', async () => {
    const disabled = harness({enabled: false});
    assert.equal((await disabled.form.beneficiaryLookup.forVoice('12345678')).status, 'disabled');
    assert.equal(disabled.input.value, '12345678'); assert.equal(disabled.requests.length, 0);
    const h = harness(); const pending = h.form.beneficiaryLookup.forVoice('12345678');
    assert.equal(h.requests.length, 1); await h.respond(match(person)); await pending;
    await h.form.beneficiaryLookup.forVoice(''); assert.equal(h.fields.full_name.value, '');
    const stale = h.form.beneficiaryLookup.forVoice('87654321'); h.reset();
    await h.respond(match(person)); assert.equal((await stale).status, 'cancelled');
    assert.equal(h.fields.full_name.value, '');
});

test('voice distinguishes ID-change clearing from manual corrections during the following lookup', async () => {
    const h = harness(); const first = h.form.beneficiaryLookup.forVoice('12345678');
    await h.respond(match(person)); await first;
    const second = h.form.beneficiaryLookup.forVoice('87654321');
    h.fields.phone.value = 'MANUAL'; await h.respond({matched: false, ambiguous: false});
    const result = await second;
    assert.ok(result.fields.includes('age')); assert.ok(result.fields.includes('full_name'));
    assert.equal(result.fields.includes('phone'), false);
});
