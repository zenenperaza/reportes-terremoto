const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/beneficiary-voice.js', 'utf8');
const window = {};
vm.runInNewContext(source, {window});
const {parse, createCoordinator} = window.BeneficiaryVoice;
const plain = result => JSON.parse(JSON.stringify(result));

test('ID-first phrase, implicit name, punctuation and old name-first phrases are supported', () => {
    for (const phrase of [
        'Cédula V18456782, María González, 34 años, mujer, teléfono 0414 123 4567, sin discapacidad, no indígena, lactante.',
        'Cédula V18456782 nombre María González edad 34 años mujer teléfono 0414 123 4567 sin discapacidad no indígena lactante.',
        'María González, 34 años, mujer, cédula V18456782, teléfono 0414 123 4567, sin discapacidad, no indígena, lactante.',
        'Cédula V18456782 María González 34 años mujer teléfono 0414 123 4567 sin discapacidad no indígena lactante.',
    ]) {
        assert.deepEqual(plain(parse(phrase)), {values: {national_id: 'V18456782', full_name: 'MARÍA GONZÁLEZ', age: '34', sex: 'Mujer',
            phone: '04141234567', disability: 'Ninguna', ethnicity: 'Ninguna', pregnant_lactating: 'Sí'}, issues: [], saveRequested: false});
    }
});

test('V/E prefixes and individual spoken digits preserve zeros; ages support words', () => {
    const result = parse('Cédula extranjera cero uno dos tres cuatro cinco seis, edad treinta y cuatro años, sexo mujer, teléfono cero cuatro uno cuatro uno dos tres cuatro cinco seis siete');
    assert.equal(result.values.national_id, 'E0123456');
    assert.equal(result.values.phone, '04141234567');
    assert.equal(result.values.age, '34');
    for (const [words, expected] of [['cero', '0'], ['diecisiete', '17'], ['veintidós', '22'], ['ciento veinte', '120']]) {
        assert.equal(parse(`edad ${words} años`).values.age, expected);
    }
    assert.equal(parse('cédula uve uno ocho cuatro cinco seis siete ocho dos').values.national_id, 'V18456782');
    assert.ok(parse('cédula dieciocho millones').issues.length);
    assert.equal(parse('cédula dieciocho millones').values.national_id, undefined);
});

test('minor caregiver fields cannot replace beneficiary ID, name, age or sex', () => {
    const result = parse('Sin cédula, nombre Ana Pérez, edad 12 años, mujer, cédula del cuidador V12345678, nombre del cuidador Carmen Pérez, parentesco madre.');
    assert.deepEqual(plain(result.values), {national_id: '', full_name: 'ANA PÉREZ', age: '12', sex: 'Mujer',
        caregiver_national_id: 'V12345678', caregiver_full_name: 'CARMEN PÉREZ', caregiver_relationship: 'Madre'});
    assert.equal(parse('nombre del cuidador Juan Pérez, 45 años, hombre').values.age, undefined);
    assert.equal(parse('cédula cuidadora E0123456').values.national_id, undefined);
    assert.equal(parse('parentesco tutora legal').values.caregiver_relationship, 'Tutor/a legal');
});

test('enums use available choices, explicit negatives and nationality; headers and consent are never returned', () => {
    const result = parse('nacionalidad colombiana, discapacidad visual, etnia Wayúu, no lactante, no recurrente', {nationalities: ['Venezolana', 'Colombiana'],
        ethnicities: ['Ninguna', 'Wayúu'], disabilities: ['Ninguna', 'Discapacidad Visual']});
    assert.deepEqual(plain(result.values), {nationality: 'Colombiana', disability: 'Discapacidad Visual', ethnicity: 'Wayúu', pregnant_lactating: 'No', is_recurrent: '0'});
    assert.ok(parse('parentesco vecina').issues.length);
    for (const key of ['proyecto_id', 'report_date', 'has_informed_consent']) assert.equal(result.values[key], undefined);
});

test('only an explicit final save command is actionable', () => {
    for (const phrase of ['guardar beneficiario', 'guarda el beneficiario.', 'Cédula 18456782, guardar beneficiario']) assert.equal(parse(phrase).saveRequested, true);
    for (const phrase of ['guardar', 'no guardar beneficiario', 'no quiero guardar beneficiario', 'guardar beneficiario después']) assert.equal(parse(phrase).saveRequested, false);
});

function harness(lookup) {
    const inputs = Object.fromEntries(['national_id', 'full_name', 'age', 'sex', 'phone', 'caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship']
        .map(name => [name, {value: ''}]));
    const states = [];
    const coordinator = createCoordinator({inputs, lookup, pending: value => states.push(value)});
    return {inputs, states, ...coordinator};
}

test('dictation does not overwrite the editable recurrence select excluded from voice in new entries', async () => {
    const h = harness(async () => ({status: 'not-found', fields: []}));
    h.inputs.is_recurrent = {value: '0', readOnly: false, dataset: {voiceExcluded: '1'}};
    const result = await h.apply('nombre Ana Pérez, edad 34 años, mujer, recurrente');
    assert.equal(h.inputs.is_recurrent.value, '0');
    assert.equal(result.recognized.includes('is_recurrent'), false);
});

test('a minor can be dictated without caregiver fields', async () => {
    const h = harness(async () => ({status: 'not-found', fields: []}));
    const result = await h.apply('Sin cédula, nombre Ana Pérez, edad 12 años, mujer');
    assert.equal(result.canSave, true);
    assert.equal(h.inputs.age.value, '12');
    assert.equal(h.inputs.caregiver_national_id.value, '');
    assert.equal(h.inputs.caregiver_full_name.value, '');
    assert.equal(h.inputs.caregiver_relationship.value, '');
});

test('lookup finishes before explicit dictated corrections are applied', async () => {
    let complete;
    const h = harness(() => new Promise(resolve => {complete = resolve;}));
    const pending = h.apply('Cédula 18456782, edad 34 años, teléfono 04141234567');
    assert.equal(h.inputs.age.value, ''); assert.equal(h.states.at(-1), true);
    h.inputs.full_name.value = 'NOMBRE GUARDADO'; h.inputs.age.value = '30'; h.inputs.phone.value = 'ANTIGUO';
    complete({status: 'matched', fields: ['full_name', 'age', 'phone']});
    const result = await pending;
    assert.equal(h.inputs.full_name.value, 'NOMBRE GUARDADO'); assert.equal(h.inputs.age.value, '34');
    assert.equal(h.inputs.phone.value, '04141234567'); assert.equal(result.canSave, true); assert.equal(h.states.at(-1), false);
});

test('manual corrections during lookup and between cumulative voice results are preserved', async () => {
    let complete;
    const h = harness(() => new Promise(resolve => {complete = resolve;}));
    const pending = h.apply('Cédula 18456782, teléfono 04141234567');
    h.inputs.phone.value = 'MANUAL'; complete({status: 'matched', fields: []}); await pending;
    assert.equal(h.inputs.phone.value, 'MANUAL');
    const direct = harness(async () => ({status: 'not-found', fields: []}));
    await direct.apply('nombre Ana Pérez, edad 34 años'); direct.inputs.age.value = '35';
    await direct.apply('nombre Ana Pérez, edad 34 años, mujer'); assert.equal(direct.inputs.age.value, '35');
    await direct.apply('nombre Ana Pérez, edad 36 años, mujer'); assert.equal(direct.inputs.age.value, '36');
});

test('reset prevents stale voice results from populating the next beneficiary', async () => {
    let complete;
    const h = harness(() => new Promise(resolve => {complete = resolve;}));
    const pending = h.apply('Cédula 18456782, nombre Ana Pérez'); h.cancel();
    complete({status: 'not-found', fields: []}); assert.equal((await pending).cancelled, true);
    assert.equal(h.inputs.full_name.value, ''); assert.equal(h.states.at(-1), false);
});

test('conflicting lookup and parse errors prevent automatic saving; absent ID is explicitly cleared', async () => {
    for (const status of ['ambiguous', 'error']) {
        const h = harness(async () => ({status, fields: []}));
        assert.equal((await h.apply('Cédula 18456782, guardar beneficiario')).canSave, false);
    }
    const calls = [], h = harness(async id => {calls.push(id); return {status: 'disabled', fields: []};});
    assert.equal((await h.apply('cédula ilegible')).canSave, false);
    h.inputs.national_id.value = '12345678'; await h.apply('Sin cédula, nombre Ana Pérez');
    assert.equal(h.inputs.national_id.value, ''); assert.deepEqual(calls, ['']);
});

function uiHarness() {
    const target = (extra = {}) => ({value: '', dataset: {}, handlers: {}, classList: {add() {}, remove() {}, toggle() {}},
        addEventListener(name, fn) {(this.handlers[name] ||= []).push(fn);},
        dispatchEvent(event) {return Promise.all((this.handlers[event.type] || []).map(fn => fn(event)));},
        setAttribute() {}, focus() {}, ...extra});
    const names = ['national_id', 'full_name', 'age', 'sex', 'nationality', 'phone', 'disability', 'ethnicity', 'pregnant_lactating', 'caregiver_relationship'];
    const inputs = Object.fromEntries(names.map(name => [name, target()]));
    for (const [name, values] of Object.entries({sex: ['Mujer', 'Hombre'], nationality: ['Venezolana', 'Colombiana'],
        disability: ['Ninguna'], ethnicity: ['Ninguna'], pregnant_lactating: ['Ninguna', 'Sí', 'No'], caregiver_relationship: ['Madre', 'Padre']})) {
        inputs[name].options = values.map(value => ({value}));
    }
    const elements = Object.fromEntries(['beneficiary-voice-toggle', 'beneficiary-voice-panel', 'beneficiary-voice-text',
        'beneficiary-voice-status', 'beneficiary-voice-unavailable', 'beneficiary-voice-apply', 'beneficiary-voice-clear'].map(id => [id, target()]));
    let engine, saved = 0, starts = 0;
    const form = target(), doc = target(), win = target({BeneficiaryVoice: window.BeneficiaryVoice});
    win.SpeechRecognition = class {
        constructor() {engine = this;}
        start() {starts++; this.onstart();}
        stop() {this.onend();}
        abort() {this.onend();}
    };
    const blade = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
    const block = blade.slice(blade.indexOf('        const voiceToggle ='), blade.indexOf('        const clearBeneficiaryEntry ='));
    vm.runInNewContext(block, {window: win, document: doc, form, beneficiaryFields: names, beneficiaryInputs: inputs,
        select: id => elements[id], syncPregnantLactatingField() {}, syncCaregiverFields() {}, checkPossibleRecurrence() {}, saveBeneficiary: async () => {saved++;}});
    const speech = (text, isFinal = true) => {const result = [{transcript: text}]; result.isFinal = isFinal; return engine.onresult({results: [result]});};
    return {form, inputs, elements, doc, win, speech, get saved() {return saved;}, get starts() {return starts;},
        click: id => elements[`beneficiary-voice-${id}`].dispatchEvent({type: 'click'})};
}

test('recognition previews interim speech; a final save waits for lookup and applies corrections first', async () => {
    const h = uiHarness(); let complete, calls = 0;
    h.form.beneficiaryLookup = {forVoice: () => {calls++; return new Promise(resolve => {complete = resolve;});}};
    await h.click('toggle');
    await h.speech('Cédula 1845', false); assert.equal(calls, 0); assert.equal(h.inputs.national_id.value, '');
    const pending = h.speech('Cédula 18456782, edad 34 años, guardar beneficiario');
    assert.equal(calls, 1); assert.equal(h.saved, 0); assert.equal(h.form.dataset.beneficiaryVoicePending, '1');
    h.inputs.age.value = '30'; complete({status: 'matched', fields: ['age']}); await pending;
    assert.equal(h.inputs.age.value, '34'); assert.equal(h.saved, 1); assert.equal(h.form.dataset.beneficiaryVoicePending, '0');
});

test('text apply never saves; reset discards late microphone callbacks and pending commands', async () => {
    const h = uiHarness(); h.elements['beneficiary-voice-text'].value = 'nombre Ana Pérez, guardar beneficiario';
    await h.click('apply'); await new Promise(resolve => setImmediate(resolve)); assert.equal(h.saved, 0);
    let complete;
    h.form.beneficiaryLookup = {forVoice: () => new Promise(resolve => {complete = resolve;}), cancel() {}};
    await h.click('toggle'); const pending = h.speech('Cédula 18456782, nombre Carmen Pérez, guardar beneficiario');
    await h.form.dispatchEvent({type: 'beneficiary-entry-reset'}); h.inputs.full_name.value = '';
    complete({status: 'not-found', fields: []}); await pending;
    await h.speech('nombre Registro Antiguo, guardar beneficiario');
    assert.equal(h.inputs.full_name.value, ''); assert.equal(h.saved, 0); assert.equal(h.starts, 1);
});

test('ambiguous ID cannot voice-save and hiding the page stops dictation without restarting', async () => {
    const h = uiHarness(); h.form.beneficiaryLookup = {forVoice: async () => ({status: 'ambiguous', fields: []}), cancel() {}};
    await h.click('toggle'); await h.speech('Cédula 18456782, guardar beneficiario'); assert.equal(h.saved, 0);
    await h.click('toggle'); h.doc.hidden = true; await h.doc.dispatchEvent({type: 'visibilitychange'});
    await h.speech('nombre Registro Antiguo'); assert.equal(h.inputs.full_name.value, ''); assert.equal(h.starts, 2);
});
