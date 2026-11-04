const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/general-project-filter.js', 'utf8');

function harness({plugin = true, selected = [], ids = ['1', '2', '3']} = {}) {
    const surface = () => ({handlers: {}, addEventListener(name, fn) {(this.handlers[name] ||= []).push(fn);},
        fire(name, event = {}) {(this.handlers[name] || []).forEach(fn => fn(event));}});
    const action = {value: '__all__', disabled: true, selected: false};
    const options = [action, ...ids.map(value => ({value, disabled: false, selected: selected.includes(value)}))];
    const scope = surface(), main = surface(), reported = surface(), exportButton = surface(), status = {};
    const hiddenReported = {value: ''}, reportedSelect = {value: '1'}, contexts = [0, 1].map(() => ({replaceChildren(...inputs) {this.inputs = inputs;}}));
    let submissions = 0, settings; const handlers = {};
    scope.querySelector = () => hiddenReported;
    scope.requestSubmit = () => {submissions++; scope.fire('submit');};
    const element = {...surface(), dataset: {placeholder: 'Todos los proyectos'}, options,
        querySelector: () => action, dispatchEvent(event) {this.fire(event.type, event); handlers['change.generalProjects']?.({originalEvent: event});}};
    const control = {select2(value) {settings = value; return this;}, on(name, fn) {handlers[name] = fn;}, trigger() {}};
    const jQuery = () => control; jQuery.fn = plugin ? {select2() {}} : {};
    vm.runInNewContext(source, {window: {jQuery}, Event, document: {
        addEventListener(_, fn) {fn();}, querySelector: () => element,
        querySelectorAll: () => contexts, createElement: () => ({}),
        getElementById: id => ({'general-project-scope': scope, 'general-report-filters': main,
            'general-reported-filter': reported, 'general-report-export': exportButton,
            'general-project-status': status, 'general_reported': reportedSelect})[id],
    }});
    return {element, action, options, main, reported, exportButton, contexts, hiddenReported, reportedSelect,
        get settings() {return settings;}, get submissions() {return submissions;},
        change() {element.dispatchEvent(new Event('change'));}, close() {handlers['select2:close.generalProjects']();},
        submit() {scope.fire('submit');}, ids: () => options.filter(o => o.selected && !o.disabled).map(o => o.value)};
}

test('default all projects, native fallback and explicit select-all never submit the sentinel', () => {
    const h = harness({plugin: false}); assert.deepEqual(h.ids(), []);
    h.action.selected = true; h.change(); assert.deepEqual(h.ids(), ['1', '2', '3']); assert.equal(h.action.selected, false);
    h.options[2].selected = false; h.submit(); assert.deepEqual(h.ids(), ['1', '3']);
    assert.deepEqual(h.contexts[0].inputs.map(i => i.value), ['1', '3']);
});
test('multiselect remains open while choosing projects and refreshes only on close', () => {
    const h = harness(); assert.equal(h.settings.multiple, true); assert.equal(h.settings.closeOnSelect, false);
    assert.equal(h.settings.placeholder, 'Todos los proyectos'); h.close(); assert.equal(h.submissions, 0);
    h.action.selected = true; h.change(); h.options[2].selected = false; h.change(); assert.equal(h.submissions, 0);
    h.close(); assert.equal(h.submissions, 1); assert.equal(h.hiddenReported.value, '1'); h.close(); assert.equal(h.submissions, 1);
});
test('clearing projects restores all projects and clears both dependent hidden scopes', () => {
    const h = harness({selected: ['1', '2']}); h.options.forEach(o => {o.selected = false;}); h.change();
    assert.deepEqual(h.contexts[0].inputs, []); assert.deepEqual(h.contexts[1].inputs, []); h.close(); assert.equal(h.submissions, 1);
});
test('stale project choices cannot generate or export results with previous dependent filters', () => {
    for (const target of ['main', 'exportButton', 'reported']) {
        const h = harness(); h.options[1].selected = true; h.change();
        const event = {preventDefault() {this.prevented = true;}, stopImmediatePropagation() {this.stopped = true;}};
        h[target].fire(target === 'exportButton' ? 'click' : 'submit', event);
        assert.equal(event.prevented, true); assert.equal(event.stopped, true); assert.equal(h.submissions, 1);
        assert.deepEqual(h.contexts[1].inputs.map(i => i.value), ['1']);
    }
});
test('reporting state is preserved on project submission and unchanged scope does not block other forms', () => {
    const h = harness({selected: ['1']}); h.reportedSelect.value = '0'; h.submit(); assert.equal(h.hiddenReported.value, '0');
    const event = {preventDefault() {throw new Error('unexpected prevention');}};
    h.reported.fire('submit', event); assert.equal(h.submissions, 0);
});
test('empty catalogs disable select-all; unrelated pages are untouched', () => {
    assert.equal(harness({ids: []}).action.disabled, true);
    vm.runInNewContext(source, {document: {addEventListener(_, fn) {fn();}, querySelector() {return null;}}});
});
