const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const view = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
const caregiverFields = ['caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship'];

function harness(age = '') {
    const beneficiaryInputs = Object.fromEntries(caregiverFields.map(field => [field, {value: 'Dato previo'}]));
    beneficiaryInputs.age = {value: age};
    const section = {hidden: true};
    const start = view.indexOf('const syncCaregiverFields =');
    const end = view.indexOf('const syncNationalityOptions =', start);
    const sync = vm.runInNewContext(view.slice(start, end) + '\nsyncCaregiverFields;', {
        beneficiaryInputs, select(id) {assert.equal(id, 'beneficiary-caregiver-fields'); return section;},
    });
    return {beneficiaryInputs, section, sync};
}

test('age 0 through 17 enables optional caregiver fields; adults clear them but temporarily empty or invalid ages preserve typed data', () => {
    for (const age of ['0', '1', '17', '18', '34', '', ' ', '-1', '17.5', 'abc']) {
        const h = harness(age); h.sync();
        const minor = ['0', '1', '17'].includes(age);
        assert.equal(h.section.hidden, !minor);
        caregiverFields.forEach(field => {
            assert.equal(h.beneficiaryInputs[field].required, false);
            assert.equal(h.beneficiaryInputs[field].disabled, !minor);
            assert.equal(h.beneficiaryInputs[field].value, ['18', '34'].includes(age) ? '' : 'Dato previo');
        });
    }
});

test('changing from minor to adult clears caregiver data and changing back keeps fields optional', () => {
    const h = harness('17'); h.sync();
    h.beneficiaryInputs.age.value = '18'; h.sync();
    h.beneficiaryInputs.age.value = '17'; h.sync();
    caregiverFields.forEach(field => {assert.equal(h.beneficiaryInputs[field].value, ''); assert.equal(h.beneficiaryInputs[field].required, false);});
});

test('client validation allows blank or missing caregiver fields while beneficiary name age and sex remain required', () => {
    const start = view.indexOf('const beneficiaryValidationMessage =');
    const end = view.indexOf('const updateSummary =', start);
    const validate = vm.runInNewContext(view.slice(start, end) + '\nbeneficiaryValidationMessage;', {selectedIndicatorAgeRange: () => null});
    const person = {has_informed_consent: '1', full_name: 'Persona', age: '17', sex: 'Mujer', is_recurrent: '0',
        caregiver_national_id: '00123456', caregiver_full_name: 'Cuidadora', caregiver_relationship: 'Madre'};
    assert.equal(validate(person), '');
    caregiverFields.forEach(field => assert.equal(validate({...person, [field]: '   '}), ''));
    const withoutCaregiver = Object.fromEntries(Object.entries(person).filter(([key]) => !caregiverFields.includes(key)));
    assert.equal(validate(withoutCaregiver), '');
    assert.equal(validate({...withoutCaregiver, caregiver_national_id: '00123456'}), '');
    assert.match(validate({...withoutCaregiver, full_name: ''}), /nombre y apellido/);
    assert.match(validate({...withoutCaregiver, age: ''}), /edad/);
    assert.match(validate({...withoutCaregiver, sex: ''}), /sexo/);
    assert.equal(validate({...person, age: '18', caregiver_national_id: '', caregiver_full_name: '', caregiver_relationship: ''}), '');
});

test('caregiver values participate in all payloads and reset with the next beneficiary', () => {
    const fields = Array.from(vm.runInNewContext(view.match(/const beneficiaryFields = (\[[\s\S]*?\]);/)[1]));
    caregiverFields.forEach(field => assert.ok(fields.includes(field)));
    assert.match(view, /beneficiaryInputs.age.addEventListener\('input', syncCaregiverFields\)/);
    assert.match(view, /syncPregnantLactatingField\(\);\s*syncCaregiverFields\(\);\s*beneficiaryEditId = beneficiary.id/);
});

test('caregiver relationship is a select with a placeholder and legacy options are enabled only for their owner', () => {
    assert.match(view, /<select id="beneficiary_caregiver_relationship" disabled>/);
    assert.match(view, /<option value="">Seleccione el parentesco<\/option>/);
    assert.doesNotMatch(view, /<input[^>]*id="beneficiary_caregiver_relationship"/);
    const start = view.indexOf('const syncCaregiverRelationshipOptions =');
    const end = view.indexOf('const pregnantLactatingField =', start);
    const options = [
        {value: 'Madre', disabled: false, dataset: {caregiverRelationshipLegacy: '0'}},
        {value: 'Vecina', disabled: true, dataset: {caregiverRelationshipLegacy: '1'}},
        {value: 'Asistente social', disabled: true, dataset: {caregiverRelationshipLegacy: '1'}},
    ];
    const context = {beneficiaryInputs: {caregiver_relationship: {options}}};
    vm.runInNewContext(view.slice(start, end) + '\nsyncCaregiverRelationshipOptions("Vecina");', context);
    assert.deepEqual(options.map(option => option.disabled), [false, false, true]);
    vm.runInNewContext('syncCaregiverRelationshipOptions(null);', context);
    assert.deepEqual(options.map(option => option.disabled), [false, true, true]);
});
