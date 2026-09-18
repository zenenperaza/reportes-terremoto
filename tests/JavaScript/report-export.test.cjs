const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/report-export.js', 'utf8');

function element() {
    return {
        children: [], rows: [], innerHTML: '', value: '',
        get textContent() {
            return this.text ?? this.innerHTML.replace(/<[^>]*>/g, '').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
        },
        set textContent(value) { this.text = value; },
        appendChild(child) { this.children.push(child); return child; },
        replaceChildren() { this.children = []; },
        createTHead() { return this.appendChild(element()); },
        createTBody() { return this.appendChild(element()); },
        insertRow() { const row = element(); this.rows.push(row); return row; },
        insertCell() { return this.appendChild(element()); },
    };
}

function harness({rows, ok = true, popupBlocked = false} = {}) {
    const state = {disabled: 0, enabled: 0, completed: 0, exports: [], printed: 0};
    const status = element();
    const popup = {closed: false, document: {head: element(), body: element(), createElement: element},
        focus() {}, setTimeout(callback) { callback(); }, print() { state.printed++; }, close() { this.closed = true; }};
    const buttons = () => ({disable() { state.disabled++; }, enable() { state.enabled++; }});
    buttons.exportData = options => {
        const data = {header: ['Nombre', 'Servicios'], body: [['solo página actual', 1]]};
        options.customizeData(data);
        return data;
    };
    const dt = {
        ajax: {url: () => 'https://app.test/reportes'}, buttons,
        table: () => ({container: () => ({querySelector: () => ({value: 'kit recién escrito'})})}),
        search: () => 'búsqueda anterior', order: () => [[3, 'desc']],
        columns: () => ({indexes: () => ({toArray: () => [0, 1]})}),
        column: index => ({dataSrc: () => ['name', 'services'][index]}),
    };
    const window = {location: {href: 'https://app.test/reportes'}, open: () => popupBlocked ? null : popup};
    const native = {};
    for (const type of ['copyHtml5', 'csvHtml5', 'excelHtml5', 'pdfHtml5']) {
        native[type] = {action(event, table, node, config, done) {
            state.exports.push({type, data: table.buttons.exportData(config.exportOptions)});
            done();
        }};
    }
    vm.runInNewContext(source, {
        window, document: {getElementById: () => status, createElement: element}, URL,
        DataTable: {ext: {buttons: native}},
        fetch: async url => { state.url = url; return {ok, json: async () => ({data: rows})}; },
    });
    return {state, status, popup, run: format => window.reportExportAction(format, {state_id: '7', from: '2026-09-01', reported: '0'})
        .call({}, {}, dt, {}, {title: 'Consolidado', exportOptions: {columns: ':not(.no-export)'}}, () => state.completed++)};
}

for (const format of ['copy', 'csv', 'excel', 'pdf', 'print']) {
    test(`${format} exports every match with current search, filters and order`, async () => {
        const {run, state, popup} = harness({rows: Array.from({length: 68}, (_, i) => ({name: i, services: 2}))});
        await run(format);
        assert.equal(state.url.searchParams.get('search[value]'), 'kit recién escrito');
        assert.equal(state.url.searchParams.get('state_id'), '7');
        assert.equal(state.url.searchParams.get('from'), '2026-09-01');
        assert.equal(state.url.searchParams.get('reported'), '0');
        assert.equal(state.url.searchParams.get('order[0][column]'), '3');
        assert.equal(state.url.searchParams.get('order[0][dir]'), 'desc');
        assert.equal(state.url.searchParams.get('export_type'), format);
        assert.equal(state.url.searchParams.has('start'), false);
        if (format === 'print') {
            assert.equal(popup.document.body.children[2].children[1].rows.length, 68);
            assert.equal(state.printed, 1);
        } else {
            assert.equal(state.exports[0].data.body.length, 68);
            assert.equal(state.exports[0].data.body[67][0], 67);
        }
        assert.equal(state.disabled, 1);
        assert.equal(state.enabled, 1);
        assert.equal(state.completed, 1);
    });
}

test('export errors never fall back to the visible page', async () => {
    const {run, state, status} = harness({ok: false});
    await run('excel');
    assert.equal(state.exports.length, 0);
    assert.match(status.textContent, /No se pudo/);
    assert.equal(state.enabled, 1);
});

test('empty results produce an empty export', async () => {
    const {run, state} = harness({rows: []});
    await run('csv');
    assert.equal(state.exports[0].data.body.length, 0);
});

test('spreadsheet formulas are escaped and line breaks remain readable', async () => {
    const {run, state} = harness({rows: [{name: '<strong>=1+1</strong>', services: 'Uno<br>Dos'}]});
    await run('csv');
    assert.equal(state.exports[0].data.body[0][0], "'=1+1");
    assert.equal(state.exports[0].data.body[0][1], 'Uno | Dos');
});

test('changed permissions and blocked print windows give an error', async () => {
    const changed = harness({rows: [{services: 2}]});
    await changed.run('excel');
    assert.equal(changed.state.exports.length, 0);
    assert.match(changed.status.textContent, /permisos/);
    const blocked = harness({rows: [], popupBlocked: true});
    await blocked.run('print');
    assert.match(blocked.status.textContent, /ventanas emergentes/);
});
