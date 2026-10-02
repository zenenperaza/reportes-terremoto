const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const code = fs.readFileSync('public/js/associated-indicators.js', 'utf8');

function element(tag) {
    const handlers = {};
    const classes = new Set();
    return {
        tag, children: [], checked: false, textContent: '',
        append(...children) {
            children.forEach(child => {
                if (child.parentElement) child.parentElement.children = child.parentElement.children.filter(item => item !== child);
                child.parentElement = this;
                this.children.push(child);
            });
        },
        replaceChildren(...children) {this.children.forEach(child => {child.parentElement = null;}); this.children = []; this.append(...children);},
        classList: {toggle(name, on) {on ? classes.add(name) : classes.delete(name);}, contains(name) {return classes.has(name);}},
        addEventListener(name, handler) {handlers[name] = handler;},
        change() {handlers.change?.();},
    };
}
function fixture() {
    const container = element('div'), count = element('p'), panel = element('section');
    const home = element('div');
    home.append(panel);
    panel.querySelector = selector => selector === '#associated-indicator-options' ? container : count;
    panel.querySelectorAll = () => container.children.map(label => label.children[0].children[0]).filter(input => input.checked);
    const window = {};
    vm.runInNewContext(code, {window, document: {createElement: element}, Event});
    return {picker: window.createAssociatedIndicatorPicker(panel), panel, container, count, window, home};
}
const items = [1, 2].map(id => ({id, code: `IND-${id}`, title: '<b>Descripción completa</b>', unit: 'Personas', ageFrom: 0, ageTo: 17}));

test('checkboxes support multiple choices, safe descriptions, deselection and counts', () => {
    const {picker, panel, container, count} = fixture();
    picker.render(items);
    assert.equal(panel.hidden, false);
    assert.equal(container.children.length, 2);
    assert.equal(container.children[0].children[1].textContent, '<b>Descripción completa</b>');
    assert.match(count.textContent, /1 registro/);
    for (const card of container.children) {
        const input = card.children[0].children[0];
        assert.equal(input.name, 'associated_indicator_ids[]');
        input.checked = true;
        input.change();
        assert.equal(card.classList.contains('is-selected'), true);
    }
    assert.equal(picker.selectedIds().join(','), '1,2');
    assert.match(count.textContent, /3 registro/);
    const input = container.children[0].children[0].children[0];
    input.checked = false;
    input.change();
    assert.equal(picker.selectedIds().join(','), '2');
    assert.match(count.textContent, /2 registro/);
});

test('changing indicator removes old choices and empty catalogs hide the panel', () => {
    const {picker, panel, container} = fixture();
    picker.render(items, [1, 999]);
    assert.equal(picker.selectedIds().join(','), '1');
    picker.render([items[1]]);
    assert.equal(picker.selectedIds().length, 0);
    picker.render([]);
    assert.equal(panel.hidden, true);
    assert.equal(container.children.length, 0);
});

test('edit forms without a picker keep normal behavior', () => {
    const {window} = fixture();
    const picker = window.createAssociatedIndicatorPicker(null);
    picker.render(items);
    assert.equal(picker.selectedIds().length, 0);
});

test('associated selections are part of the group signature and use the submitted form', () => {
    const view = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
    assert.ok(view.includes("if (field === 'associated_indicator_ids[]') return [field, associatedPicker.selectedIds()];"));
    assert.ok(view.includes("if (!createsNewReport) data.set('report_id', activeReportId);"));
    assert.ok(view.includes('associatedPrincipal === activity.value ? associatedPicker.selectedIds() : []'));
});

test('associated indicators are excluded from principal choices, including sector counts and searches', () => {
    const {window} = fixture();
    const catalog = [
        {id: 1, sectorProjectId: 10, isAssociated: false},
        {id: 2, sectorProjectId: 10, isAssociated: true},
        {id: 3, sectorProjectId: 20, isAssociated: false},
    ];
    assert.equal(window.primaryIndicatorOptions(catalog, '10').map(item => item.id).join(','), '1');
    // A saved associated record remains editable without revealing other associated choices.
    assert.equal(window.primaryIndicatorOptions(catalog, '10', 2).map(item => item.id).join(','), '1,2');
    assert.equal(window.primaryIndicatorOptions(catalog, '20').map(item => item.id).join(','), '3');
});

test('primary clicks reveal associates; deselecting or changing the primary clears all selections', () => {
    const {window, picker, panel, container} = fixture();
    const catalog = [
        {id: 10, associatedIds: [1, 2]},
        {id: 20, associatedIds: [2]},
        ...items,
    ];
    let sync;
    const activity = {value: '', dispatchEvent(event) {assert.equal(event.type, 'change'); sync();}};
    const view = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
    const start = view.indexOf('let associatedPrincipal = null;');
    const end = view.indexOf('const selectedIndicatorAgeRange =', start);
    vm.runInNewContext(view.slice(start, end) + '\nexpose(syncAssociatedIndicators);', {
        activity, project: {value: '1'}, projectIndicators: {'1': catalog}, associatedPicker: picker,
        selectedIndicator: () => catalog.find(item => String(item.id) === activity.value),
        expose(fn) {sync = fn;},
    });
    sync();
    assert.equal(panel.hidden, true);
    window.togglePrimaryIndicator(activity, 10);
    assert.equal(panel.hidden, false);
    assert.equal(container.children.length, 2);
    container.children.forEach(card => {card.children[0].children[0].checked = true;});
    assert.equal(picker.selectedIds().join(','), '1,2');
    window.togglePrimaryIndicator(activity, 10);
    assert.equal(activity.value, '');
    assert.equal(panel.hidden, true);
    assert.equal(container.children.length, 0);
    assert.equal(picker.selectedIds().length, 0);
    window.togglePrimaryIndicator(activity, 10);
    assert.equal(picker.selectedIds().length, 0);
    container.children[0].children[0].children[0].checked = true;
    window.togglePrimaryIndicator(activity, 20);
    assert.equal(container.children.length, 1);
    assert.equal(picker.selectedIds().length, 0);
});

test('branches move beside the principal without nesting checkboxes inside its button or losing selection', () => {
    const {picker, panel, container, home} = fixture();
    const grid = element('div');
    const branch = element('div');
    const principal = element('button');
    branch.append(principal);
    grid.append(branch);
    picker.render(items, [2]);
    picker.mount(branch);
    assert.equal(panel.parentElement, branch);
    assert.equal(principal.children.length, 0);
    assert.equal(home.children.length, 0);
    assert.equal(picker.selectedIds().join(','), '2');
    picker.park();
    grid.replaceChildren();
    assert.equal(panel.parentElement, home);
    assert.equal(picker.selectedIds().join(','), '2');
    const replacement = element('div');
    grid.append(replacement);
    picker.mount(replacement);
    assert.equal(panel.parentElement, replacement);
    assert.equal(container.children.length, 2);
    assert.equal(picker.selectedIds().join(','), '2');
    picker.park();
    picker.render([]);
    assert.equal(panel.hidden, true);
    assert.equal(picker.selectedIds().length, 0);
});

test('search keeps the selected principal visible and parks branches before rebuilding cards', () => {
    const view = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
    assert.ok(view.includes("String(item.id) === String(activity.value) || !query"));
    assert.ok(view.indexOf('associatedPicker.park();') < view.indexOf('indicatorCardGrid.replaceChildren();'));
    assert.ok(view.includes('associatedPicker.mount(branch);'));
});
