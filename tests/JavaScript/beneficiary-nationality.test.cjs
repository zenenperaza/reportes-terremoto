const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const view = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
const model = fs.readFileSync('app/Models/Beneficiary.php', 'utf8');
const fields = Array.from(vm.runInNewContext(view.match(/const beneficiaryFields = (\[[\s\S]*?\]);/)[1]));

function inputs() {
    return Object.fromEntries(fields.map(field => [field, {value: 'Previous value', checked: false, classList: {remove() {}}}]));
}

test('nationality participates in the shared beneficiary payload and is prefilled for new people', () => {
    assert.ok(fields.includes('nationality'));
    assert.match(model, /DEFAULT_NATIONALITY = 'Venezolana'/);
    assert.match(view, /<select id="beneficiary_nationality">/);
    assert.doesNotMatch(view, /<input[^>]*id="beneficiary_nationality"/);
    assert.match(view, /old\('beneficiary.nationality', \\App\\Models\\Beneficiary::DEFAULT_NATIONALITY\)/);
    const start = view.indexOf('const inputValue =');
    const end = view.indexOf('const headerSignature =', start);
    const beneficiaryInputs = inputs();
    beneficiaryInputs.nationality.value = '  Colombiana  ';
    const result = vm.runInNewContext(view.slice(start, end) + '\nbeneficiaryRecord();', {beneficiaryFields: fields, beneficiaryInputs});
    assert.equal(result.nationality, 'Colombiana');
});

test('starting the next beneficiary resets nationality to Venezuelan instead of carrying it forward', () => {
    const start = view.indexOf('const clearBeneficiaryEntry =');
    const end = view.indexOf('const requiredHeaderFields =', start);
    const code = view.slice(start, end).replace('@json(\\App\\Models\\Beneficiary::DEFAULT_NATIONALITY)', '"Venezolana"');
    const beneficiaryInputs = inputs();
    beneficiaryInputs.nationality.value = 'Colombiana';
    const context = {beneficiaryFields: fields, beneficiaryInputs, beneficiaryEditId: 42, saveButton: {},
        syncPregnantLactatingField() {}, syncNationalityOptions() {}, select() {return {};}, recurrenceWarning: {}, voiceText: {}, voiceStatus: {}};
    vm.runInNewContext(code + '\nclearBeneficiaryEntry();', context);
    assert.equal(beneficiaryInputs.nationality.value, 'Venezolana');
    assert.equal(beneficiaryInputs.full_name.value, '');
    assert.equal(context.beneficiaryEditId, null);
});

test('editing loads the stored nationality and does not assume a value for historical unknown data', () => {
    const editStart = view.indexOf("edit.addEventListener('click'");
    const start = view.indexOf('beneficiaryFields.forEach(field => {', editStart);
    const end = view.indexOf('syncPregnantLactatingField();', start);
    for (const nationality of ['Colombiana', null]) {
        const beneficiaryInputs = inputs();
        vm.runInNewContext(view.slice(start, end), {beneficiaryFields: fields, beneficiaryInputs, beneficiary: {nationality}});
        assert.equal(beneficiaryInputs.nationality.value, nationality ?? '');
    }
});

test('legacy options are enabled only when editing their original beneficiary', () => {
    const start = view.indexOf('const syncNationalityOptions =');
    const end = view.indexOf('const pregnantLactatingField =', start);
    const options = [
        {value: 'Venezolana', disabled: false, dataset: {nationalityLegacy: '0'}},
        {value: 'Mongola', disabled: true, dataset: {nationalityLegacy: '1'}},
        {value: 'Japonesa', disabled: true, dataset: {nationalityLegacy: '1'}},
    ];
    const context = {beneficiaryInputs: {nationality: {options}}};
    vm.runInNewContext(view.slice(start, end) + '\nsyncNationalityOptions("Mongola");', context);
    assert.deepEqual(options.map(option => option.disabled), [false, false, true]);
    vm.runInNewContext('syncNationalityOptions(null);', context);
    assert.deepEqual(options.map(option => option.disabled), [false, true, true]);
});

test('create, personal edit and attention edit all send nationality in the existing payload', () => {
    const start = view.indexOf('let url = form.dataset.beneficiaryUrl;');
    const end = view.indexOf('isSaving = true;', start);
    for (const mode of ['create', 'personal', 'attention']) {
        const sent = [];
        const form = {dataset: {beneficiaryUrl: '/beneficiarios'}};
        if (mode === 'attention') form.dataset.beneficiaryUpdateUrl = '/beneficiarios/5/atencion';
        vm.runInNewContext('let data;\n' + view.slice(start, end), {
            form, beneficiaryFields: fields, beneficiary: {nationality: 'Colombiana'},
            beneficiaryEditId: mode === 'create' ? null : 5, createsNewReport: true,
            FormData: class {set(key, value) {sent.push([key, value]);}},
        });
        assert.deepEqual(sent.find(([key]) => key === (mode === 'personal' ? 'nationality' : 'beneficiary[nationality]')),
            [mode === 'personal' ? 'nationality' : 'beneficiary[nationality]', 'Colombiana']);
    }
});
