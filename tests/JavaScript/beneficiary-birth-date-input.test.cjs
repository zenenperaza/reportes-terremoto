const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/beneficiary-birth-date-input.js', 'utf8');

function element(value = '') {
    return {value, dataset: {}, selectionStart: 0, selectionEnd: 0, validityMessage: '', handlers: {},
        classList: {remove() {}},
        setCustomValidity(message) {this.validityMessage = message;},
        setSelectionRange(start, end) {this.selectionStart = start; this.selectionEnd = end;},
        addEventListener(name, callback) {(this.handlers[name] ||= []).push(callback);},
        dispatchEvent(event) {for (const handler of this.handlers[event.type] || []) handler(event);},
    };
}
function harness(initial = '') {
    const window = {};
    vm.runInNewContext(source, {window, Event});
    const input = element(), canonical = element(initial);
    let reference = '2026-11-25';
    const control = window.SiaBirthDateInput.mount(input, canonical, () => reference);
    return {input, canonical, control,
        reference(value) {reference = value; control.sync();},
        enter(value, position = value.length) {
            input.value = value; input.setSelectionRange(position, position);
            input.dispatchEvent(new Event('input'));
        },
        type(char) {
            const start = input.selectionStart;
            input.value = input.value.slice(0, start) + char + input.value.slice(input.selectionEnd);
            input.setSelectionRange(start + char.length, start + char.length);
            input.dispatchEvent(new Event('input'));
        },
    };
}

test('typing just eight digits inserts slashes and only exposes a complete ISO date', () => {
    const h = harness();
    const steps = ['0', '03/', '03/0', '03/02/', '03/02/2', '03/02/20', '03/02/201', '03/02/2014'];
    [...'03022014'].forEach((digit, index) => {
        h.type(digit);
        assert.equal(h.input.value, steps[index]);
        assert.equal(h.canonical.value, index === 7 ? '2014-02-03' : '');
        assert.equal(h.input.selectionStart, h.input.value.length);
        h.control.sync();
        assert.equal(h.input.value, steps[index], 'age/recurrence refresh must not erase a partial date');
    });
    assert.equal(h.canonical.dataset.manualRevision, '8');
    assert.equal(h.input.validityMessage, '');
});

test('invalid calendar dates are rejected, including century leap-year edge cases', () => {
    const h = harness();
    for (const date of ['31042014', '29021900', '00012000', '01002000', '01010000', '29022023', '31132000']) {
        h.enter(date); assert.equal(h.canonical.value, ''); assert.match(h.input.validityMessage, /válida/);
    }
    for (const [text, iso] of [['29022000', '2000-02-29'], ['29022024', '2024-02-29'], ['01011900', '1900-01-01']]) {
        h.enter(text); assert.equal(h.canonical.value, iso); assert.equal(h.input.validityMessage, '');
    }
});

test('pasted formatted dates and ISO dates use the same manual display and canonical value', () => {
    const h = harness();
    for (const value of ['03/02/2014', '2014-02-03', '03022014']) {
        h.enter(value); assert.equal(h.input.value, '03/02/2014'); assert.equal(h.canonical.value, '2014-02-03');
    }
    h.enter('abc03022014999'); assert.equal(h.input.value, '03/02/2014');
});

test('backspace immediately after an automatic slash deletes a digit instead of getting stuck', () => {
    const h = harness(); h.enter('0302');
    let prevented = false;
    h.input.dispatchEvent({type: 'beforeinput', inputType: 'deleteContentBackward', preventDefault() {prevented = true;}});
    assert.equal(prevented, true); assert.equal(h.input.value, '03/0'); assert.equal(h.input.selectionStart, 4);
    h.enter('03');
    h.input.dispatchEvent({type: 'beforeinput', inputType: 'deleteContentBackward', preventDefault() {}});
    assert.equal(h.input.value, '0'); assert.equal(h.input.selectionStart, 1);
});

test('editing a selected day preserves the rest of the date and the caret', () => {
    const h = harness(); h.enter('03022014'); h.input.setSelectionRange(0, 2); h.type('15');
    assert.equal(h.input.value, '15/02/2014'); assert.equal(h.canonical.value, '2014-02-15');
    assert.equal(h.input.selectionStart, 3);
});

test('birth dates after the attention date are invalid and changing that date revalidates', () => {
    const h = harness(); h.enter('26112026');
    assert.match(h.input.validityMessage, /posterior/);
    h.reference('2026-11-26'); assert.equal(h.input.validityMessage, '');
    h.reference('2026-11-24'); assert.match(h.input.validityMessage, /posterior/);
});

test('lookup, dictation and editing synchronize their ISO dates into the manual display', () => {
    const h = harness('2000-01-15'); assert.equal(h.input.value, '15/01/2000');
    h.canonical.value = '2010-02-03'; h.canonical.dispatchEvent(new Event('change'));
    assert.equal(h.input.value, '03/02/2010');
    h.canonical.value = '2012-06-07'; h.control.sync(); assert.equal(h.input.value, '07/06/2012');
    h.canonical.value = ''; h.control.sync(); assert.equal(h.input.value, '');
});

test('reset clears partial and invalid dates as well as completed ones', () => {
    const h = harness();
    for (const value of ['03', '31042014', '03022014']) {
        h.enter(value); h.control.clear(); h.control.sync();
        assert.equal(h.input.value, ''); assert.equal(h.canonical.value, ''); assert.equal(h.input.validityMessage, '');
    }
});

test('the report age calculation uses the ISO value and leaves partial manual dates intact', () => {
    const h = harness(), age = element();
    const attention = {value: '2026-11-25'};
    const blade = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
    const calculation = blade.slice(blade.indexOf('        const syncBirthAge ='), blade.indexOf('        const syncCaregiverFields ='));
    const recalculate = vm.runInNewContext(calculation + '\nsyncBirthAge;', {
        birthDateInput: h.control, beneficiaryInputs: {birth_date: h.canonical, age},
        form: {querySelector() {return attention;}},
    });
    h.canonical.addEventListener('input', recalculate);
    h.enter('26111990'); assert.equal(age.value, 35);
    attention.value = '2026-11-26'; recalculate(); assert.equal(age.value, 36);
    h.enter('03'); assert.equal(age.value, ''); assert.equal(h.input.value, '03/');
    h.enter('31042014'); assert.equal(age.value, ''); assert.match(h.input.validityMessage, /válida/);
});
