const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function harness() {
    const element = (value = '') => ({value, dataset: {}, handlers: {},
        setAttribute() {}, addEventListener(name, fn) {(this.handlers[name] ||= []).push(fn);},
        dispatch(type) {return Promise.all((this.handlers[type] || []).map(fn => fn()));}});
    const form = element(), project = element('1'), status = element();
    const inputs = Object.fromEntries(['national_id', 'full_name', 'age', 'sex', 'is_recurrent'].map(name => [name, element()]));
    const requests = [], timers = new Map(); let nextTimer = 0;
    const window = {};
    vm.runInNewContext(fs.readFileSync('public/js/beneficiary-recurrence.js', 'utf8'), {window, AbortController,
        setTimeout(fn) {timers.set(++nextTimer, fn); return nextTimer;}, clearTimeout(id) {timers.delete(id);},
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({url, options, resolve, reject})),
    });
    const api = window.BeneficiaryRecurrence.create({form, project, inputs, status, url: '/recurrencia', csrf: 'TEST'});
    return {...api, form, project, inputs, status, requests,
        async respond(result, request = requests.at(-1), ok = true) {request.resolve({ok, json: async () => result}); await new Promise(resolve => setImmediate(resolve));},
        flush() {const pending = [...timers.values()]; timers.clear(); pending.forEach(fn => fn());},
    };
}

test('new registrations calculate recurrence; one project-scoped POST sends no indicator or location', async () => {
    const h = harness(); assert.equal(h.inputs.is_recurrent.disabled, false); assert.equal(h.inputs.is_recurrent.readOnly, false);
    assert.equal(h.inputs.is_recurrent.dataset.voiceExcluded, '1');
    h.inputs.national_id.value = 'V12345678'; const pending = h.check();
    const sent = JSON.parse(h.requests[0].options.body);
    assert.deepEqual(sent, {proyecto_id: '1', national_id: 'V12345678', full_name: '', age: '', sex: ''});
    assert.equal(h.requests[0].options.method, 'POST'); assert.equal(h.requests[0].options.cache, 'no-store');
    assert.equal(h.form.dataset.beneficiaryRecurrencePending, '1');
    await h.respond({is_recurrent: true}); assert.equal(await pending, true);
    assert.equal(h.inputs.is_recurrent.value, '1'); assert.match(h.status.textContent, /mismo proyecto/);
    assert.equal(h.form.dataset.beneficiaryRecurrencePending, '0');
});

test('changing projects resets recurrence and ignores the old project response', async () => {
    const h = harness(); h.inputs.national_id.value = '12345678'; h.check(); const old = h.requests[0];
    h.project.value = '2'; await h.project.dispatch('change'); assert.equal(old.options.signal.aborted, true);
    assert.equal(h.inputs.is_recurrent.value, '0'); assert.equal(h.requests.length, 2);
    await h.respond({is_recurrent: false}); await h.respond({is_recurrent: true}, old);
    assert.equal(h.inputs.is_recurrent.value, '0'); assert.match(h.status.textContent, /No recurrente/);
});

test('no-ID fallback waits for name age and sex, including age zero', async () => {
    const h = harness(); h.inputs.full_name.value = 'ANA PÉREZ'; h.inputs.age.value = '0';
    await h.check(); assert.equal(h.requests.length, 0);
    h.inputs.sex.value = 'Mujer'; const pending = h.check(); await h.respond({is_recurrent: true}); await pending;
    assert.equal(h.inputs.is_recurrent.value, '1');
    h.inputs.full_name.value = ''; await h.check(); assert.equal(h.inputs.is_recurrent.value, '0');
});

test('typing is debounced and saving can await the shared current request', async () => {
    const h = harness(); h.inputs.national_id.value = '12345678'; await h.inputs.national_id.dispatch('input');
    assert.equal(h.requests.length, 0); h.flush(); assert.equal(h.requests.length, 1);
    let done = false; const pending = h.check().then(result => {done = true; return result;});
    await Promise.resolve(); assert.equal(done, false); assert.equal(h.requests.length, 1);
    await h.respond({is_recurrent: true}); assert.equal(await pending, true);
    await h.check(); assert.equal(h.requests.length, 1);
});

test('network errors do not silently mark someone as new and are retryable', async () => {
    const h = harness(); h.inputs.national_id.value = '12345678'; const first = h.check();
    await h.respond({}, h.requests[0], false); assert.equal(await first, false); assert.match(h.status.textContent, /No se pudo/);
    const retry = h.check(); await h.respond({is_recurrent: true}); assert.equal(await retry, true);
});

test('reset/edit invalidates old checks; correcting an existing attendance does not reclassify it', async () => {
    const h = harness(); h.inputs.national_id.value = '12345678'; const pending = h.check();
    h.form.dataset.beneficiaryEditing = '1'; await h.form.dispatch('beneficiary-entry-reset');
    h.inputs.is_recurrent.value = '0'; await h.respond({is_recurrent: true}); assert.equal(await pending, false);
    await h.check(); assert.equal(h.requests.length, 1); assert.equal(h.inputs.is_recurrent.value, '0');
    assert.equal(h.inputs.is_recurrent.disabled, false);
    h.form.dataset.beneficiaryEditing = '0'; await h.form.dispatch('beneficiary-entry-reset');
    assert.equal(h.inputs.is_recurrent.disabled, false);
    assert.equal(h.inputs.is_recurrent.dataset.voiceExcluded, '1');
});

test('automatic Yes remains editable and saving keeps a manual No for the same person and project', async () => {
    const h = harness(); h.inputs.national_id.value = '12345678';
    assert.equal(h.isManual(), false);
    const pending = h.check(); await h.respond({is_recurrent: true}); await pending;
    assert.equal(h.inputs.is_recurrent.value, '1'); assert.equal(h.inputs.is_recurrent.disabled, false);
    h.inputs.is_recurrent.value = '0'; await h.inputs.is_recurrent.dispatch('change');
    assert.equal(h.isManual(), true);
    assert.match(h.status.textContent, /Selección manual/);
    assert.equal(await h.check(), true); assert.equal(h.inputs.is_recurrent.value, '0');
    assert.equal(h.requests.length, 1);
});

test('a pending automatic response cannot overwrite a manual choice made while loading', async () => {
    const h = harness(); h.inputs.national_id.value = '12345678'; const pending = h.check();
    h.inputs.is_recurrent.value = '0'; await h.inputs.is_recurrent.dispatch('change');
    await h.respond({is_recurrent: true}); assert.equal(await pending, true);
    assert.equal(h.inputs.is_recurrent.value, '0'); assert.match(h.status.textContent, /Selección manual/);
    assert.match(h.status.textContent, /registros anteriores/);
});

test('manual overrides do not leak to a different person or project', async () => {
    for (const target of ['person', 'project']) {
        const h = harness(); h.inputs.national_id.value = '12345678'; let pending = h.check();
        await h.respond({is_recurrent: true}); await pending;
        h.inputs.is_recurrent.value = '0'; await h.inputs.is_recurrent.dispatch('change');
        if (target === 'person') {h.inputs.national_id.value = '87654321'; await h.inputs.national_id.dispatch('input');}
        else {h.project.value = '2'; await h.project.dispatch('change');}
        pending = h.check(); await h.respond({is_recurrent: true}); await pending;
        assert.equal(h.isManual(), false);
        assert.equal(h.inputs.is_recurrent.value, '1'); assert.doesNotMatch(h.status.textContent, /Selección manual/);
    }
});

test('resetting the next beneficiary removes a manual choice even when identity is entered again', async () => {
    const h = harness(); h.inputs.national_id.value = '12345678';
    h.inputs.is_recurrent.value = '0'; await h.inputs.is_recurrent.dispatch('change');
    await h.form.dispatch('beneficiary-entry-reset');
    assert.equal(h.isManual(), false);
    const pending = h.check(); await h.respond({is_recurrent: true}); await pending;
    assert.equal(h.inputs.is_recurrent.value, '1');
});

test('a same-person input event or retry does not discard a manual choice', async () => {
    const h = harness(); h.inputs.national_id.value = '12345678';
    h.inputs.is_recurrent.value = '1'; await h.inputs.is_recurrent.dispatch('change');
    await h.inputs.national_id.dispatch('input'); const pending = h.check();
    await h.respond({}, h.requests[0], false); assert.equal(await pending, false);
    const retry = h.check(); await h.respond({is_recurrent: false}); assert.equal(await retry, true);
    assert.equal(h.inputs.is_recurrent.value, '1'); assert.match(h.status.textContent, /Selección manual/);
});

test('voice and ID-autofill paths invoke recurrence; save awaits the check before posting', () => {
    const blade = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
    assert.match(blade, /sync: \(\) => \{syncPregnantLactatingField\(\); syncCaregiverFields\(\); void checkPossibleRecurrence\(\);\}/);
    assert.match(blade, /form.addEventListener\('beneficiary-autofill',[\s\S]*?void checkPossibleRecurrence\(\)/);
    assert.match(blade, /async function saveBeneficiary\(\)[\s\S]*?await checkPossibleRecurrence\(\)[\s\S]*?beneficiary.is_recurrent = beneficiaryInputs.is_recurrent.value/);
    assert.match(blade, /beneficiary.recurrence_manual = recurrence.isManual\(\)/);
    assert.match(blade, /data.set\('beneficiary\[recurrence_manual\]', beneficiary.recurrence_manual \? '1' : '0'\)/);
});
