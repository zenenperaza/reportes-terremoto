const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/general-reported-filter.js', 'utf8');

function harness() {
    const form = {submissions: 0, addEventListener(event, handler) {this.submit = handler;}, requestSubmit() {this.submissions++; this.submit();}};
    const reported = {addEventListener(event, handler) {this.change = handler;}};
    const periods = {selectedOptions: [{value: '2026-08'}, {value: '2026-09'}], addEventListener(event, handler) {this.change = handler;}};
    const container = {replaceChildren(...children) {this.children = children;}};
    const elements = {'general-reported-filter': form, 'general_reported': reported, 'reporting-period': periods, 'general-reported-periods': container};
    vm.runInNewContext(source, {document: {getElementById: id => elements[id], createElement: () => ({})}});
    return {form, reported, periods, container};
}

test('changing status submits only the status form with all currently selected periods', () => {
    const h = harness();
    h.reported.change();
    assert.equal(h.form.submissions, 1);
    assert.deepEqual(h.container.children.map(input => input.value), ['2026-08', '2026-09']);
    assert.ok(h.container.children.every(input => input.type === 'hidden' && input.name === 'reporting_period[]'));
});

test('clearing periods keeps an explicit all-periods value for the apply-status button', () => {
    const h = harness();
    h.periods.selectedOptions = [];
    h.periods.change();
    h.form.submit();
    assert.equal(h.container.children.length, 1);
    assert.equal(h.container.children[0].value, '');
    assert.equal(h.form.submissions, 0);
});

test('new periods and unassigned are preserved without triggering a status reload', () => {
    const h = harness();
    h.periods.selectedOptions = [{value: 'unassigned'}, {value: '2026-09'}];
    h.periods.change();
    assert.equal(h.form.submissions, 0);
    assert.deepEqual(h.container.children.map(input => input.value), ['unassigned', '2026-09']);
});
