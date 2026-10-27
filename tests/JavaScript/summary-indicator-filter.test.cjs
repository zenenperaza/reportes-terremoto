const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/summary-indicator-filter.js', 'utf8');

function harness({plugin = true, ids = ['1', '2', '3'], selected = ['2'], disabled = []} = {}) {
    const action = {value: '__all__', selected: false, disabled: true};
    const options = [action, ...ids.map(value => ({value, selected: selected.includes(value), disabled: disabled.includes(value)}))];
    const native = {};
    const calls = [];
    let jqueryChange;
    let submit;
    const element = {
        dataset: {placeholder: 'Todos (sin filtro)'}, options,
        querySelector(selector) {assert.equal(selector, '[data-summary-select-all]'); return action;},
        addEventListener(name, handler) {native[name] = handler;},
        dispatchEvent(event) {native[event.type]?.(event); jqueryChange?.({originalEvent: event});},
        form: {addEventListener(name, handler) {assert.equal(name, 'submit'); submit = handler;}},
    };
    const control = {
        select2(settings) {calls.push(settings); return this;},
        on(name, handler) {assert.equal(name, 'change.summaryIndicators'); jqueryChange = handler; return this;},
        trigger(name) {assert.equal(name, 'change.select2'); return this;},
    };
    const jQuery = target => {assert.equal(target, element); return control;};
    jQuery.fn = plugin ? {select2() {}} : {};
    vm.runInNewContext(source, {window: {jQuery}, Event, document: {
        addEventListener(name, handler) {assert.equal(name, 'DOMContentLoaded'); handler();},
        querySelectorAll(selector) {assert.equal(selector, '[data-summary-indicator-filter]'); return [element];},
    }});
    return {element, action, options, calls, nativeChange: () => element.dispatchEvent(new Event('change')),
        jqueryChange: () => jqueryChange({}), submit: () => submit(),
        selected: () => options.filter(option => option.selected && !option.disabled).map(option => option.value)};
}

test('summary controls support multiple selections without auto-submitting reports', () => {
    const h = harness();
    assert.equal(h.calls.length, 1);
    assert.equal(h.calls[0].multiple, true);
    assert.equal(h.calls[0].allowClear, true);
    assert.equal(h.calls[0].closeOnSelect, false);
    assert.equal(h.calls[0].placeholder, 'Todos (sin filtro)');
    assert.equal(h.action.disabled, false);
    assert.deepEqual(h.selected(), ['2']);
});

test('first option selects every available summary and users can then remove any individual selection', () => {
    const h = harness();
    h.action.selected = true;
    h.jqueryChange();
    assert.deepEqual(h.selected(), ['1', '2', '3']);
    assert.equal(h.action.selected, false);
    h.options[2].selected = false;
    h.jqueryChange();
    assert.deepEqual(h.selected(), ['1', '3']);
    h.submit();
    assert.deepEqual(h.selected(), ['1', '3']);
});

test('select all excludes disabled options, does not submit the action ID, and can be used repeatedly', () => {
    const h = harness({disabled: ['3']});
    h.action.selected = true;
    h.nativeChange();
    assert.deepEqual(h.selected(), ['1', '2']);
    h.options.forEach(option => {option.selected = false;});
    h.jqueryChange();
    assert.deepEqual(h.selected(), []);
    h.action.selected = true;
    h.jqueryChange();
    assert.deepEqual(h.selected(), ['1', '2']);
    assert.equal(h.action.selected, false);
});

test('native multiple selection and select all still work without Select2', () => {
    const h = harness({plugin: false});
    assert.equal(h.calls.length, 0);
    h.action.selected = true;
    h.nativeChange();
    assert.deepEqual(h.selected(), ['1', '2', '3']);
    h.options[1].selected = false;
    h.submit();
    assert.deepEqual(h.selected(), ['2', '3']);
});

test('form submission resolves a pending select-all action before any IDs are submitted', () => {
    const h = harness();
    h.action.selected = true;
    h.submit();
    assert.deepEqual(h.selected(), ['1', '2', '3']);
    assert.equal(h.action.selected, false);
});

test('empty summary catalog keeps select-all disabled', () => {
    const h = harness({ids: [], selected: []});
    assert.equal(h.action.disabled, true);
    assert.deepEqual(h.selected(), []);
});

test('pages without Select2 or summary controls remain usable', () => {
    for (const window of [{}, {jQuery: {fn: {}}}]) {
        vm.runInNewContext(source, {window, document: {
            addEventListener(_, handler) {handler();}, querySelectorAll() {return [];},
        }});
    }
});

test('shared filter renders a native multiselect with select-all first and disabled until JavaScript starts', () => {
    const view = fs.readFileSync('resources/views/reports/partials/summary-indicator-filter.blade.php', 'utf8');
    assert.match(view, /<select[^>]*name="summary_indicator_ids\[\]"[^>]*multiple/);
    assert.match(view, /<select[^>]*>\s*<option value="__all__" data-summary-select-all disabled>Seleccionar todos<\/option>/);
});

test('summary field is never introduced into the new activity entry form', () => {
    const form = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
    assert.equal(form.includes('summary-indicator-filter'), false);
    assert.equal(form.includes('summary_indicator_ids'), false);
    const controller = fs.readFileSync('app/Http/Controllers/ReportController.php', 'utf8');
    const start = controller.indexOf('public function create(');
    const end = controller.indexOf('public function edit(', start);
    assert.equal(controller.slice(start, end).includes('SummaryIndicator'), false);
});
