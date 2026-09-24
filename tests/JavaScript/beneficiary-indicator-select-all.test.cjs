const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const view = fs.readFileSync('resources/views/beneficiaries/summary.blade.php', 'utf8');
const bulkCode = view.slice(view.indexOf('const summarySelectAllValue ='), view.indexOf('const summaryIndicatorOptions ='));

test('changing reported status submits only the separate status form', () => {
    let onChange;
    let submissions = 0;
    const elements = {
        'beneficiary-reported-filter': {requestSubmit() {submissions++;}},
        summary_reported: {addEventListener(event, handler) {assert.equal(event, 'change'); onChange = handler;}},
    };
    const start = view.indexOf('const reportedFilterForm =');
    const end = view.indexOf('const summarySector =', start);
    assert.ok(start > 0 && end > start);
    vm.runInNewContext(view.slice(start, end), {summarySelect: id => elements[id]});
    onChange();
    assert.equal(submissions, 1);
});

function fixture(values = ['project:1', 'project:2', 'legacy:3']) {
    const handlers = {};
    class Option {
        constructor(text, value, defaultSelected = false, selected = false) {
            Object.assign(this, {text, value, selected, disabled: false});
        }
    }
    const select = {
        options: values.map(value => new Option(value, value)),
        get selectedOptions() {return this.options.filter(option => option.selected);},
        prepend(option) {this.options.unshift(option);},
        add(option) {this.options.push(option);},
        replaceChildren() {this.options = [];},
        addEventListener(name, handler) {handlers[name] = handler;},
        dispatchEvent(event) {handlers[event.type]?.(event); this.changes = (this.changes || 0) + 1;},
    };
    const context = vm.createContext({
        summaryIndicator: select, Option, Event,
        summarySector: {value: '', addEventListener(name, handler) {this.handler = handler;}},
        window: {}, syncBeneficiaryExportUrl() {},
    });
    vm.runInContext(bulkCode, context);
    return {select, context, selected: () => select.selectedOptions.map(option => option.value)};
}

test('select all selects concrete indicators, supports removing one and never submits the bulk action', () => {
    const {select, context, selected} = fixture();
    vm.runInContext('selectAllSummaryIndicators()', context);
    assert.deepEqual(selected(), ['project:1', 'project:2', 'legacy:3']);
    select.options.find(option => option.value === 'project:2').selected = false;
    select.dispatchEvent(new Event('change'));
    assert.deepEqual(selected(), ['project:1', 'legacy:3']);
    vm.runInContext('selectAllSummaryIndicators()', context);
    assert.equal(selected().length, 3);
});

test('native select fallback replaces the bulk action with actual enabled indicator values', () => {
    const {select, selected} = fixture();
    select.options.find(option => option.value === 'project:2').disabled = true;
    select.options[0].selected = true;
    select.dispatchEvent(new Event('change'));
    assert.deepEqual(selected(), ['project:1', 'legacy:3']);
    assert.equal(select.options[0].selected, false);
});

test('empty indicator lists disable the bulk action', () => {
    const {select, context, selected} = fixture([]);
    assert.equal(select.options[0].disabled, true);
    vm.runInContext('selectAllSummaryIndicators()', context);
    assert.deepEqual(selected(), []);
});

test('sector changes keep one bulk option and only select indicators available in that sector', () => {
    const {select, context, selected} = fixture();
    Object.assign(context, {
        summaryIndicatorOptions: [
            {value: 'project:1', label: 'One', sector_id: 1},
            {value: 'project:2', label: 'Two', sector_id: 2},
            {value: 'project:2', label: 'Duplicate', sector_id: 2},
            {value: 'legacy:3', label: 'Three', sector_id: 2},
        ],
    });
    const start = view.indexOf("summarySector.addEventListener('change'");
    const end = view.indexOf("document.addEventListener('DOMContentLoaded'", start);
    vm.runInContext(view.slice(start, end), context);
    context.summarySector.value = '2';
    context.summarySector.handler();
    vm.runInContext('selectAllSummaryIndicators()', context);
    assert.deepEqual(selected(), ['project:2', 'legacy:3']);
    assert.equal(select.options.length, 3);
    context.summarySector.value = '99';
    context.summarySector.handler();
    assert.equal(select.options.length, 1);
    assert.equal(select.options[0].disabled, true);
    assert.deepEqual(selected(), []);
});

test('Select2 intercepts the bulk option, updates the selection and closes the dropdown', () => {
    const {context, selected} = fixture();
    const handlers = {};
    const calls = [];
    const widget = {
        select2(options) {calls.push(options); return this;},
        on(name, handler) {handlers[name] = handler; return this;},
    };
    const jQuery = () => widget;
    jQuery.fn = {select2() {}};
    context.window.jQuery = jQuery;
    context.document = {addEventListener(name, handler) {handler();}};
    const start = view.indexOf("document.addEventListener('DOMContentLoaded'");
    vm.runInContext(view.slice(start, view.indexOf('</script>', start)), context);
    let prevented = false;
    handlers['select2:selecting']({
        params: {args: {data: {id: '__select_all_indicators__'}}},
        preventDefault() {prevented = true;},
    });
    assert.equal(prevented, true);
    assert.deepEqual(selected(), ['project:1', 'project:2', 'legacy:3']);
    assert.equal(calls.at(-1), 'close');
    handlers['select2:selecting']({
        params: {args: {data: {id: 'project:1'}}},
        preventDefault() {assert.fail('Individual options must remain selectable');},
    });
});
