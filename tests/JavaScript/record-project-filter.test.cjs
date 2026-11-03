const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/record-project-filter.js', 'utf8');

function harness({plugin = true, ids = ['1', '2', '3'], disabled = []} = {}) {
    const action = {value: '__all__', selected: false, disabled: true};
    const options = [action, ...ids.map(value => ({value, selected: false, disabled: disabled.includes(value)}))];
    const native = {}; let submit, jqueryChange, settings;
    const element = {dataset: {placeholder: 'Todos los proyectos'}, options,
        querySelector() {return action;}, addEventListener(name, fn) {native[name] = fn;},
        dispatchEvent(event) {native[event.type]?.(event); jqueryChange?.({originalEvent: event});},
        form: {addEventListener(_, fn) {submit = fn;}},
    };
    const control = {select2(value) {settings = value; return this;},
        on(_, fn) {jqueryChange = fn;}, trigger(name) {assert.equal(name, 'change.select2');}};
    const jQuery = () => control; jQuery.fn = plugin ? {select2() {}} : {};
    vm.runInNewContext(source, {window: {jQuery}, Event, document: {
        addEventListener(_, fn) {fn();}, querySelectorAll() {return [element];},
    }});
    return {action, options, settings, change: () => element.dispatchEvent(new Event('change')),
        jqueryChange: () => jqueryChange({}), submit: () => submit(),
        selected: () => options.filter(option => option.selected && !option.disabled).map(option => option.value)};
}

test('project multiselect defaults to all without preselection and stays open for multiple choices', () => {
    const h = harness(); assert.deepEqual(h.selected(), []);
    assert.equal(h.settings.placeholder, 'Todos los proyectos'); assert.equal(h.settings.multiple, true);
    assert.equal(h.settings.allowClear, true); assert.equal(h.settings.closeOnSelect, false);
});
test('select-all chooses available project IDs and users can remove any project afterwards', () => {
    const h = harness({disabled: ['3']}); h.action.selected = true; h.jqueryChange();
    assert.deepEqual(h.selected(), ['1', '2']); assert.equal(h.action.selected, false);
    h.options[1].selected = false; h.jqueryChange(); h.submit(); assert.deepEqual(h.selected(), ['2']);
});
test('native fallback and submit never send the select-all sentinel', () => {
    const h = harness({plugin: false}); h.action.selected = true; h.change(); assert.deepEqual(h.selected(), ['1', '2', '3']);
    h.options.forEach(option => {option.selected = false;}); h.action.selected = true; h.submit();
    assert.deepEqual(h.selected(), ['1', '2', '3']); assert.equal(h.action.selected, false);
});
test('empty catalog disables select-all and pages without project controls remain usable', () => {
    assert.equal(harness({ids: []}).action.disabled, true);
    vm.runInNewContext(source, {window: {}, document: {addEventListener(_, fn) {fn();}, querySelectorAll() {return [];}}});
});
