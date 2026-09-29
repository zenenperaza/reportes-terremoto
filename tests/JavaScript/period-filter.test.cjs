const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const code = fs.readFileSync('public/js/period-filter.js', 'utf8');

test('period multi-select supports clearing, stays open, and bridges synthetic changes without recursion', () => {
    let options;
    let onChange;
    let nativeChanges = 0;
    const select = {dispatchEvent(event) {
        assert.equal(event.type, 'change');
        assert.equal(event.bubbles, true);
        nativeChanges++;
        onChange({originalEvent: event});
    }};
    const jquery = target => {
        assert.equal(target, select);
        return {select2(config) {options = config; return {on(name, handler) {onChange = handler;}};}};
    };
    jquery.fn = {select2() {}};
    vm.runInNewContext(code, {window: {jQuery: jquery}, document: {getElementById: () => select}, Event});
    assert.equal(options.placeholder, 'Todos los períodos');
    assert.equal(options.allowClear, true);
    assert.equal(options.closeOnSelect, false);
    onChange({});
    assert.equal(nativeChanges, 1);
});

test('native multiple selector remains available when Select2 is unavailable', () => {
    vm.runInNewContext(code, {window: {}, document: {getElementById: () => ({multiple: true})}});
});
