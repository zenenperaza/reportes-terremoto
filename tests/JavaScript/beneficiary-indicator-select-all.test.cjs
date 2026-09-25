const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const view = fs.readFileSync('resources/views/beneficiaries/summary.blade.php', 'utf8');
const code = fs.readFileSync('public/js/beneficiary-indicator-picker.js', 'utf8');

test('changing reported status submits only the separate status form', () => {
    let onChange;
    let submissions = 0;
    const elements = {
        'beneficiary-reported-filter': {requestSubmit() {submissions++;}},
        summary_reported: {addEventListener(event, handler) {assert.equal(event, 'change'); onChange = handler;}},
    };
    const start = view.indexOf('const reportedFilterForm =');
    const end = view.indexOf('const beneficiaryFilterForm =', start);
    assert.ok(start > 0 && end > start);
    vm.runInNewContext(view.slice(start, end), {summarySelect: id => elements[id]});
    onChange();
    assert.equal(submissions, 1);
});

function element(extra = {}) {
    const handlers = {};
    const classes = new Set();
    return Object.assign({
        value: '', hidden: false, disabled: false, checked: false, textContent: '', dataset: {},
        classList: {toggle(name, on) {on ? classes.add(name) : classes.delete(name);}, contains: name => classes.has(name)},
        addEventListener(name, handler) {(handlers[name] ||= []).push(handler);},
        dispatchEvent(event) {(handlers[event.type] || []).forEach(handler => handler(event));},
        attributes: {},
        getAttribute(name) {return this.attributes[name];},
        setAttribute(name, value) {this.attributes[name] = value;},
    }, extra);
}

function fixture({values = ['project:1', 'project:2', 'legacy:3'], selected = [], sectorValue = '', groupSizes = [values.length]} = {}) {
    const cards = values.map((value, i) => {
        const input = element({value, checked: selected.includes(value)});
        return element({input,
            dataset: {sectors: JSON.stringify(i === 0 ? ['1'] : ['2']), search: i === 0 ? 'NNA Orientación legal' : `SMAPS ${value}`},
            querySelector: () => input,
        });
    });
    let offset = 0;
    const groups = groupSizes.map(size => {
        const members = cards.slice(offset, offset + size);
        offset += size;
        const control = element(), count = element();
        return element({control, count, querySelectorAll: () => members,
            querySelector: selector => selector === '[data-group-all]' ? control : count});
    });
    const group = groups[0], groupCount = group.count;
    const picker = element({querySelectorAll: selector => selector === '[data-indicator-card]' ? cards : groups});
    const elements = {'summary-indicator-picker': picker, summary_sector_id: element({value: sectorValue})};
    for (const id of ['search', 'all', 'clear', 'selection', 'empty', 'panel', 'toggle']) elements[`summary-indicator-${id}`] = element();
    let changes = 0;
    picker.addEventListener('change', event => {if (event.bubbles) changes++;});
    vm.runInNewContext(code, {document: {getElementById: id => elements[id]}, window: {}, Event});
    const action = (id, type = 'click', value) => {
        const target = elements[id.startsWith('summary_') ? id : `summary-indicator-${id}`];
        if (value !== undefined) target.value = value;
        if (id === 'all' && type === 'click') {
            target.checked = !target.checked;
            type = 'change';
        }
        target.dispatchEvent(new Event(type));
    };
    const selectGroup = (index, checked) => {
        groups[index].control.checked = checked;
        groups[index].control.dispatchEvent(new Event('change'));
    };
    return {cards, group, groups, groupCount, picker, elements, action, selectGroup, changes: () => changes,
        selected: () => cards.filter(card => !card.input.disabled && card.input.checked).map(card => card.input.value)};
}

test('select all marks real indicators, allows removing one and notifies the export form', () => {
    const f = fixture();
    f.action('all');
    assert.deepEqual(f.selected(), ['project:1', 'project:2', 'legacy:3']);
    assert.equal(f.changes(), 1);
    f.cards[1].input.checked = false;
    f.picker.dispatchEvent(new Event('change'));
    assert.deepEqual(f.selected(), ['project:1', 'legacy:3']);
    assert.equal(f.elements['summary-indicator-selection'].textContent, '2 indicadores seleccionados');
    f.action('clear');
    assert.deepEqual(f.selected(), []);
    assert.equal(f.elements['summary-indicator-selection'].textContent, 'Todos los indicadores (sin filtro)');
});

test('search ignores accents and preserves hidden selections; select all includes search-hidden cards', () => {
    const f = fixture();
    f.action('search', 'input', 'orientacion');
    assert.deepEqual(f.cards.map(card => card.hidden), [false, true, true]);
    assert.equal(f.groupCount.textContent, '1');
    f.action('all');
    assert.equal(f.selected().length, 3);
    f.action('search', 'input', 'no existe');
    assert.equal(f.group.hidden, true);
    assert.equal(f.elements['summary-indicator-empty'].hidden, false);
    assert.equal(f.selected().length, 3);
    f.action('search', 'input', '');
    assert.equal(f.group.hidden, false);
});

test('sector changes disable and uncheck unavailable cards, keeping applicable selections', () => {
    const f = fixture();
    f.action('all');
    f.action('summary_sector_id', 'change', '2');
    assert.deepEqual(f.selected(), ['project:2', 'legacy:3']);
    assert.equal(f.cards[0].input.disabled, true);
    assert.equal(f.cards[0].input.checked, false);
    f.action('clear');
    f.action('all');
    assert.deepEqual(f.selected(), ['project:2', 'legacy:3']);
    f.action('summary_sector_id', 'change', '99');
    assert.deepEqual(f.selected(), []);
    assert.equal(f.elements['summary-indicator-all'].disabled, true);
    f.action('summary_sector_id', 'change', '');
    assert.equal(f.elements['summary-indicator-all'].disabled, false);
    assert.deepEqual(f.selected(), []);
});

test('initial selection is preserved and empty catalogs disable bulk actions', () => {
    const f = fixture({selected: ['project:2'], sectorValue: '2'});
    assert.deepEqual(f.selected(), ['project:2']);
    assert.equal(f.cards[1].classList.contains('is-selected'), true);
    const empty = fixture({values: []});
    assert.equal(empty.elements['summary-indicator-all'].disabled, true);
    assert.equal(empty.elements['summary-indicator-clear'].disabled, true);
    assert.equal(empty.elements['summary-indicator-empty'].hidden, false);
});

test('collapse fallback opens and closes without changing selection', () => {
    const f = fixture({selected: ['project:1']});
    f.action('toggle');
    assert.equal(f.elements['summary-indicator-panel'].classList.contains('show'), true);
    assert.equal(f.elements['summary-indicator-toggle'].getAttribute('aria-expanded'), 'true');
    f.action('toggle');
    assert.equal(f.elements['summary-indicator-panel'].classList.contains('show'), false);
    assert.deepEqual(f.selected(), ['project:1']);
});

test('Enter in search does not accidentally submit the report', () => {
    const f = fixture();
    const event = new Event('keydown', {cancelable: true});
    event.key = 'Enter';
    f.elements['summary-indicator-search'].dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
});

test('each group selects and clears only its own indicators, syncing partial and global states', () => {
    const f = fixture({groupSizes: [2, 1]});
    f.selectGroup(0, true);
    assert.deepEqual(f.selected(), ['project:1', 'project:2']);
    assert.equal(f.groups[0].control.checked, true);
    assert.equal(f.groups[1].control.checked, false);
    assert.equal(f.elements['summary-indicator-all'].indeterminate, true);
    assert.equal(f.changes(), 1);
    f.cards[0].input.checked = false;
    f.picker.dispatchEvent(new Event('change'));
    assert.equal(f.groups[0].control.indeterminate, true);
    assert.equal(f.groups[0].control.checked, false);
    f.action('all');
    assert.deepEqual(f.selected(), ['project:1', 'project:2', 'legacy:3']);
    assert.equal(f.groups.every(group => group.control.checked && !group.control.indeterminate), true);
    assert.equal(f.elements['summary-indicator-all'].indeterminate, false);
    f.selectGroup(0, false);
    assert.deepEqual(f.selected(), ['legacy:3']);
    assert.equal(f.groups[1].control.checked, true);
    f.action('all');
    f.action('all');
    assert.deepEqual(f.selected(), []);
    assert.equal(f.groups.every(group => !group.control.checked && !group.control.indeterminate), true);
});

test('group selection includes search-hidden cards but never indicators outside the selected sector', () => {
    const f = fixture({groupSizes: [2, 1]});
    f.action('search', 'input', 'orientacion');
    f.selectGroup(0, true);
    assert.deepEqual(f.selected(), ['project:1', 'project:2']);
    f.action('summary_sector_id', 'change', '2');
    assert.deepEqual(f.selected(), ['project:2']);
    assert.equal(f.groups[0].control.checked, true);
    f.selectGroup(0, false);
    f.selectGroup(0, true);
    assert.deepEqual(f.selected(), ['project:2']);
    f.action('summary_sector_id', 'change', '1');
    assert.equal(f.groups[1].control.disabled, true);
    assert.equal(f.groups[1].control.checked, false);
    f.action('summary_sector_id', 'change', '99');
    assert.equal(f.groups.every(group => group.control.disabled && !group.control.indeterminate), true);
});
