const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/buzon.js', 'utf8');

function field(value = '') {
    return {value, handlers: {}, checked: false, addEventListener(name, handler) {this.handlers[name] = handler;},
        setCustomValidity(value) {this.error = value;}};
}
function fixture({from = null, to = null, disabilities = [], projects = null, window = {}} = {}) {
    vm.runInNewContext(source, {window, document: {
        addEventListener(_, handler) {handler();},
        getElementById(id) {return {'buzon-from': from, 'buzon-to': to, 'buzon-project-filter': projects}[id] || null;},
        querySelectorAll() {return disabilities;},
    }});
}

test('dates validate immediately, allow a single day and clear errors when corrected', () => {
    const from = field('2026-10-10'), to = field('2026-10-01');
    fixture({from, to});
    assert.match(to.error, /anterior/);
    assert.equal(to.min, '2026-10-10');
    assert.equal(from.max, '2026-10-01');
    to.value = '2026-10-10';
    to.handlers.input();
    assert.equal(to.error, '');
    from.value = '';
    to.value = '';
    from.handlers.input();
    assert.equal(to.min, '');
    assert.equal(from.max, '');
    assert.equal(to.error, '');
});

test('none excludes disabilities while multiple actual disabilities remain allowed', () => {
    const none = field('none'), visual = field('visual'), hearing = field('hearing');
    fixture({disabilities: [none, visual, hearing]});
    visual.checked = true;
    hearing.checked = true;
    hearing.handlers.change();
    assert.equal(visual.checked, true);
    none.checked = true;
    none.handlers.change();
    assert.equal(visual.checked, false);
    assert.equal(hearing.checked, false);
    visual.checked = true;
    visual.handlers.change();
    assert.equal(none.checked, false);
    assert.equal(visual.checked, true);
});

test('project filter supports multiple choices and pages work without optional controls or Select2', () => {
    const projects = {dataset: {placeholder: 'Todos los proyectos disponibles'}};
    let options;
    const jQuery = element => {assert.equal(element, projects); return {select2(value) {options = value;}};};
    jQuery.fn = {select2() {}};
    fixture({projects, window: {jQuery}});
    assert.equal(options.closeOnSelect, false);
    assert.equal(options.placeholder, projects.dataset.placeholder);
    fixture({projects});
    fixture();
});
