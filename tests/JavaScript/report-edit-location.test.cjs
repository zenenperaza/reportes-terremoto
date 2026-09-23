const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const view = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');

test('initializing formal-location mode preserves the already selected saved location', () => {
    const start = view.indexOf('const syncCommunityMode =');
    const end = view.indexOf("communityState.addEventListener('change'", start);
    assert.ok(start > 0 && end > start);
    const placeName = {value: 'Lugar guardado', required: false};
    const state = {value: '1'}, municipality = {value: '2'}, parish = {value: '3'};
    const formalPlaceFields = {hidden: true}, communityLocationFields = {hidden: false};
    const context = {
        placeName, formalPlaceFields, communityLocationFields,
        communityLocationToggle: {checked: false},
        communityState: {}, communityMunicipality: {}, communityParish: {}, communityLatitude: {}, communityLongitude: {},
        clearCommunityLocation() {placeName.value = ''; state.value = municipality.value = parish.value = '';},
        syncPlaceLocation() {
            if (placeName.value === 'Lugar guardado') {
                state.value = '1'; municipality.value = '2'; parish.value = '3';
            }
        },
        syncCommunityLocation() {throw new Error('A saved formal location must not be regenerated');},
    };
    vm.runInNewContext(view.slice(start, end) + '\nsyncCommunityMode();', context);
    assert.equal(placeName.value, 'Lugar guardado');
    assert.equal(state.value, '1');
    assert.equal(municipality.value, '2');
    assert.equal(parish.value, '3');
    assert.equal(formalPlaceFields.hidden, false);
    assert.equal(communityLocationFields.hidden, true);
    assert.equal(placeName.required, true);
});

test('individual edit submits the full form to its dedicated endpoint before any personal-only update', () => {
    const start = view.indexOf('let url = form.dataset.beneficiaryUrl;');
    const end = view.indexOf('isSaving = true;', start);
    const code = view.slice(start, end);
    const forms = [];
    const form = {dataset: {beneficiaryUrl: '/beneficiarios', beneficiaryUpdateUrl: '/beneficiarios/3/atencion'}};
    const context = {
        form, beneficiaryEditId: 3, beneficiaryFields: ['full_name'], beneficiary: {full_name: 'PERSONA'},
        FormData: class {constructor(source) {this.source = source; this.fields = {}; forms.push(this);} set(key, value) {this.fields[key] = value;}},
    };
    const destination = vm.runInNewContext('let data;\n' + code + '\nurl;', context);
    assert.equal(destination, '/beneficiarios/3/atencion');
    assert.equal(forms.length, 1);
    assert.equal(forms[0].source, form);
    assert.equal(forms[0].fields['beneficiary[full_name]'], 'PERSONA');
    assert.equal(forms[0].fields._method, 'PUT');
});
