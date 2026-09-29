const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
test('opening a parent never opens its active child and child clicks leave the parent open', () => {
    const panels = [];
    const triggers = [];
    function panel(id, children = []) {
        const classes = new Set(['show']);
        const trigger = {expanded: 'true', classList: {add() {}}, getAttribute: () => id, setAttribute(name, value) {this.expanded = value;}};
        const value = {id, classes, classList: {remove(name) {classes.delete(name);}}, querySelectorAll: () => children,
            addEventListener(name, handler) {this.show = handler;}};
        panels.push(value); triggers.push(trigger);
        return value;
    }
    const projects = panel('projects'), settings = panel('settings');
    const parent = panel('configuration', [projects, settings]);
    let ready;
    vm.runInNewContext(fs.readFileSync('public/js/navigation-disclosure.js', 'utf8'), {document: {
        readyState: 'loading', addEventListener(name, callback) {ready = callback;},
        getElementById() {return {querySelectorAll: selector => selector === '.collapse' ? panels : triggers};},
    }});
    ready();
    assert.ok(panels.every(p => !p.classes.has('show')));
    assert.ok(triggers.every(t => t.expanded === 'false'));
    projects.classes.add('show');
    parent.show({target: parent});
    assert.equal(projects.classes.has('show'), false);
    parent.classes.add('show');
    projects.show({target: projects});
    parent.show({target: projects});
    assert.equal(parent.classes.has('show'), true);
});
