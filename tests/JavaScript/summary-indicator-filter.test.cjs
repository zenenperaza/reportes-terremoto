const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/summary-indicator-filter.js', 'utf8');

test('summary controls support multiple selections without auto-submitting reports', () => {
    const elements = [{dataset: {placeholder: 'Todos (sin filtro)'}}, {dataset: {placeholder: 'Todos'}}];
    const calls = [];
    const jQuery = element => ({select2(options) {calls.push({element, options});}});
    jQuery.fn = {select2() {}};
    vm.runInNewContext(source, {window: {jQuery}, document: {
        addEventListener(name, handler) {assert.equal(name, 'DOMContentLoaded'); handler();},
        querySelectorAll(selector) {assert.equal(selector, '[data-summary-indicator-filter]'); return elements;},
    }});
    assert.equal(calls.length, 2);
    assert.equal(calls[0].element, elements[0]);
    assert.equal(calls[0].options.closeOnSelect, false);
    assert.equal(calls[0].options.placeholder, 'Todos (sin filtro)');
});

test('pages without Select2 or summary controls remain usable', () => {
    for (const window of [{}, {jQuery: {fn: {}}}]) {
        vm.runInNewContext(source, {window, document: {
            addEventListener(_, handler) {handler();}, querySelectorAll() {throw new Error('No plugin available');},
        }});
    }
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
