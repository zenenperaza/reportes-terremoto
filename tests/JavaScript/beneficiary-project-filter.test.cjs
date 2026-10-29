const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/beneficiary-project-filter.js', 'utf8');

function harness({plugin = true, ids = ['1', '2', '3'], selected = [], disabled = []} = {}) {
    const action = {value: '__all__', selected: false, disabled: true};
    const options = [action, ...ids.map(value => ({value, selected: selected.includes(value), disabled: disabled.includes(value)}))];
    const native = {};
    const calls = [];
    const jquery = {};
    const surface = () => ({events: {}, addEventListener(name, handler) {(this.events[name] ||= []).push(handler);},
        fire(name, event = {}) {this.events[name]?.forEach(handler => handler(event));}});
    const scope = surface(), main = surface(), reported = surface(), exportButton = surface();
    const status = {};
    const contexts = [{replaceChildren(...inputs) {this.inputs = inputs;}}, {replaceChildren(...inputs) {this.inputs = inputs;}}];
    const periods = {replaceChildren() {this.cleared = true;}};
    let submissions = 0;
    scope.requestSubmit = () => {submissions++; scope.fire('submit');};
    const element = {
        dataset: {placeholder: 'Todos los proyectos'}, options,
        querySelector(selector) {assert.equal(selector, '[data-project-select-all]'); return action;},
        addEventListener(name, handler, capture) {native[name] = handler; assert.equal(capture, true);},
        dispatchEvent(event) {native[event.type]?.(event); jquery['change.beneficiaryProjects']?.({originalEvent: event});},
        form: scope,
    };
    const control = {
        select2(settings) {calls.push(settings); return this;},
        on(name, handler) {jquery[name] = handler; return this;},
        trigger(name) {assert.equal(name, 'change.select2'); return this;},
    };
    const jQuery = target => {assert.equal(target, element); return control;};
    jQuery.fn = plugin ? {select2() {}} : {};
    vm.runInNewContext(source, {window: {jQuery}, Event, document: {
        addEventListener(name, handler) {assert.equal(name, 'DOMContentLoaded'); handler();},
        getElementById: id => ({'beneficiary-project-scope': scope, 'beneficiary-report-filters': main,
            'beneficiary-reported-filter': reported, 'beneficiary-export-button': exportButton,
            'summary-project-status': status, 'reported-periods': periods})[id],
        createElement(name) {assert.equal(name, 'input'); return {};},
        querySelectorAll(selector) {return selector === '[data-project-scope-inputs]' ? contexts : [element];},
    }});
    return {element, action, options, calls, nativeChange: () => element.dispatchEvent(new Event('change')),
        jqueryChange: () => jquery['change.beneficiaryProjects']({}), submit: () => scope.fire('submit'),
        close: () => jquery['select2:close.beneficiaryProjects'](), submissions: () => submissions,
        main, reported, exportButton, status, contexts, periods,
        selected: () => options.filter(option => option.selected && !option.disabled).map(option => option.value)};
}

test('defaults to all projects without preselecting IDs and supports independent removal', () => {
    const h = harness();
    assert.deepEqual(h.selected(), []);
    assert.equal(h.calls[0].placeholder, 'Todos los proyectos');
    assert.equal(h.calls[0].multiple, true);
    assert.equal(h.calls[0].allowClear, true);
    assert.equal(h.calls[0].closeOnSelect, false);
    h.action.selected = true;
    h.jqueryChange();
    assert.deepEqual(h.selected(), ['1', '2', '3']);
    h.options[2].selected = false;
    h.jqueryChange();
    assert.deepEqual(h.selected(), ['1', '3']);
    h.submit();
    assert.deepEqual(h.selected(), ['1', '3']);
});

test('select all never submits its sentinel and excludes disabled projects', () => {
    const h = harness({disabled: ['3'], selected: ['2']});
    h.action.selected = true;
    h.nativeChange();
    assert.deepEqual(h.selected(), ['1', '2']);
    assert.equal(h.action.selected, false);
    h.options.forEach(option => {option.selected = false;});
    h.jqueryChange();
    assert.deepEqual(h.selected(), []);
    h.action.selected = true;
    h.submit();
    assert.deepEqual(h.selected(), ['1', '2']);
});

test('native multiple select and select-all still work without Select2', () => {
    const h = harness({plugin: false});
    assert.equal(h.calls.length, 0);
    h.action.selected = true;
    h.nativeChange();
    assert.deepEqual(h.selected(), ['1', '2', '3']);
    h.options[1].selected = false;
    h.submit();
    assert.deepEqual(h.selected(), ['2', '3']);
});

test('empty project list keeps select-all disabled', () => {
    const h = harness({ids: []});
    assert.equal(h.action.disabled, true);
});

test('multiple selections update scope only when the dropdown closes, not on each item', () => {
    const h = harness();
    h.close();
    assert.equal(h.submissions(), 0);
    h.action.selected = true;
    h.jqueryChange();
    h.options[2].selected = false;
    h.jqueryChange();
    assert.equal(h.submissions(), 0);
    assert.deepEqual(h.contexts[0].inputs.map(input => input.value), ['1', '3']);
    h.close();
    assert.equal(h.submissions(), 1);
    assert.match(h.status.textContent, /Actualizando filtros/);
    h.close();
    assert.equal(h.submissions(), 1);
});

test('clearing projects reloads all projects and removes scope IDs from both forms', () => {
    const h = harness({selected: ['1', '3']});
    h.options.forEach(option => {option.selected = false;});
    h.jqueryChange();
    assert.equal(h.contexts[0].inputs.length, 0);
    assert.equal(h.contexts[1].inputs.length, 0);
    h.close();
    assert.equal(h.submissions(), 1);
});

test('generation and Excel cannot use stale dependent filters after projects change', () => {
    for (const target of ['main', 'exportButton']) {
        const h = harness();
        h.options[1].selected = true;
        h.jqueryChange();
        const event = {preventDefault() {this.prevented = true;}, stopImmediatePropagation() {this.stopped = true;}};
        h[target].fire(target === 'main' ? 'submit' : 'click', event);
        assert.equal(event.prevented, true);
        assert.equal(event.stopped, true);
        assert.equal(h.submissions(), 1);
    }
});

test('reporting status preserves projects and drops stale periods only if projects changed', () => {
    const h = harness({selected: ['2']});
    h.reported.fire('submit');
    assert.equal(h.periods.cleared, undefined);
    assert.deepEqual(h.contexts[1].inputs.map(input => input.value), ['2']);
    h.options[1].selected = true;
    h.jqueryChange();
    h.reported.fire('submit');
    assert.equal(h.periods.cleared, true);
    assert.deepEqual(h.contexts[1].inputs.map(input => input.value), ['1', '2']);
});
