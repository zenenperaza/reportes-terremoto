const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const view = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
const start = view.indexOf('const beneficiaryValidationMessage =');
const end = view.indexOf('const updateSummary =', start);
const validate = vm.runInNewContext(view.slice(start, end) + '\nbeneficiaryValidationMessage;', {selectedIndicatorAgeRange: () => null});

test('new activity form marks name required and validates empty names before saving or voice save', () => {
    assert.match(view, /Nombre y apellido \*<input[^>]*id="beneficiary_full_name"\s+required/);
    const valid = {has_informed_consent: '1', full_name: 'MARÍA GONZÁLEZ', age: '34', sex: 'Mujer', is_recurrent: '0'};
    for (const full_name of ['', '   ', null, undefined]) assert.match(validate({...valid, full_name}), /nombre y apellido/);
    assert.equal(validate(valid), '');
    assert.match(view, /const validationMessage = beneficiaryValidationMessage\(beneficiary\)/);
    assert.match(view, /const missing = \['full_name', 'age', 'sex'\]/);
});
