const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const code = fs.readFileSync('public/js/record-state-filter.js', 'utf8');

test('state selector supports multiple choices without closing and can clear all', () => {
    let options;
    const select = {multiple: true};
    const jQuery = element => {
        assert.equal(element, select);
        return {select2(config) {options = config;}};
    };
    jQuery.fn = {select2() {}};
    vm.runInNewContext(code, {window: {jQuery}, document: {getElementById: () => select}});
    assert.equal(options.placeholder, 'Todos los estados');
    assert.equal(options.closeOnSelect, false);
    assert.equal(options.allowClear, true);
    assert.equal(options.width, '100%');
});

test('native multiple selection remains available without Select2', () => {
    vm.runInNewContext(code, {window: {}, document: {getElementById: () => ({multiple: true})}});
});
