const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/service-report-table.js', 'utf8');

function harness({ids = ['3', '8'], canExport = true, hasTable = true, hasLibrary = true} = {}) {
    const state = {submitted: 0, inputs: [], options: null};
    const nodes = {
        'service-report-table': hasTable ? {} : null,
        'service-report-export': canExport ? {addEventListener: (_, handler) => {state.click = handler;}} : null,
        'service-report-export-form': {submit: () => {state.submitted++;}},
        'service-report-export-selection': {
            replaceChildren: () => {state.inputs = [];}, appendChild: input => {state.inputs.push(input);}
        }
    };
    const context = {document: {
        addEventListener: (_, handler) => handler(), getElementById: id => nodes[id], createElement: () => ({})
    }};
    if (hasLibrary) context.DataTable = function (element, options) {
        assert.equal(element, nodes['service-report-table']);
        state.options = options;
        return {rows: selector => {
            assert.equal(selector.search, 'applied');
            assert.equal(selector.page, 'all');
            return {nodes: () => ({toArray: () => ids.map(id => ({dataset: {serviceAssignment: id}}))})};
        }};
    };
    vm.runInNewContext(source, context);
    return state;
}

test('table enables Spanish search, paging and sorting, excluding actions', () => {
    const state = harness();
    assert.equal(state.options.pageLength, 15);
    assert.equal(state.options.language.search, 'Buscar:');
    assert.equal(state.options.columnDefs[0].targets, 10);
    assert.equal(state.options.columnDefs[0].searchable, false);
    assert.equal(state.options.responsive.details.type, 'inline');
    assert.equal(state.options.responsive.details.target, 0);
    assert.equal(state.options.columnDefs[1].className, 'dtr-control');
});

test('Excel submits all matching pages and replaces previous IDs on each click', () => {
    const state = harness();
    let prevented = 0;
    const event = {preventDefault: () => {prevented++;}};
    state.click(event);
    state.click(event);
    assert.equal(prevented, 2);
    assert.equal(state.submitted, 2);
    assert.deepEqual(state.inputs.map(input => [input.name, input.value]), [['assignment_ids_json', '["3","8"]']]);
});

test('empty search exports no rows, not the entire report', () => {
    const state = harness({ids: []});
    state.click({preventDefault() {}});
    assert.equal(state.inputs[0].value, '[]');
    assert.equal(state.submitted, 1);
});

test('large exports use a single JSON field, avoiding PHP input variable limits', () => {
    const ids = Array.from({length: 1500}, (_, index) => String(index + 1));
    const state = harness({ids});
    state.click({preventDefault() {}});
    assert.equal(state.inputs.length, 1);
    assert.deepEqual(JSON.parse(state.inputs[0].value), ids);
});

test('permission denied or unavailable dependencies do not create export handlers', () => {
    for (const options of [{canExport: false}, {hasTable: false}, {hasLibrary: false}]) {
        assert.equal(harness(options).click, undefined);
    }
});
