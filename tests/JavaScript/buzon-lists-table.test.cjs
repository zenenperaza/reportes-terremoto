const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/buzon-lists-table.js', 'utf8');

function harness({missingTable = false, missingLibrary = false} = {}) {
    let config;
    const element = {dataset: {url: '/buzon/listados', filters: JSON.stringify({project_ids: [2, 3]})}};
    const total = {textContent: 'Total de listados: 25'};
    const fallback = {hidden: false, setAttribute(name) {this[name] = true;}};
    function DataTable(target, options) {assert.equal(target, element); config = options;}
    DataTable.render = {text: () => text => String(text).replaceAll('<', '&lt;'), number: () => value => String(value)};
    vm.runInNewContext(source, {
        ...(missingLibrary ? {} : {DataTable}),
        document: {addEventListener(_, callback) {callback();},
            getElementById: id => id === 'buzon-lists-table' ? (missingTable ? null : element) : total,
            querySelector: () => fallback},
    });
    return {config, total, fallback, draw(filtered, all) {
        config.drawCallback.call({api: () => ({page: {info: () => ({recordsDisplay: filtered, recordsTotal: all})}})});
    }};
}

test('list search, pagination, ordering and responsive columns use the authorized server endpoint', () => {
    const h = harness();
    assert.equal(h.config.serverSide, true); assert.equal(h.config.processing, true);
    assert.equal(h.config.pageLength, 20); assert.equal(h.config.orderMulti, false);
    assert.deepEqual(Array.from(h.config.lengthMenu), [20, 50, 100]);
    assert.equal(h.config.ajax.url, '/buzon/listados');
    const request = {draw: 1}; h.config.ajax.data(request);
    assert.deepEqual(Array.from(request.project_ids), [2, 3]); assert.equal(request.draw, 1);
    assert.equal(h.config.layout.topEnd, 'search'); assert.equal(h.config.layout.bottomEnd, 'paging');
    assert.equal(h.config.columns[5].orderable, false); assert.equal(h.config.columns[5].searchable, false);
    assert.equal(h.config.columns[2].render('<img src=x>'), '&lt;img src=x>');
    assert.equal(h.config.columns[0].render('25', 'display'), '#25');
    assert.equal(h.config.columns[0].render('<img>', 'display'), '#NaN');
    assert.equal(h.fallback.hidden, true);
});

test('the footer shows the full total and updates after searching, including no results', () => {
    const h = harness();
    h.draw(25, 25); assert.equal(h.total.textContent, 'Total de listados: 25');
    h.draw(2, 25); assert.equal(h.total.textContent, 'Total de listados: 2 (de 25 disponibles)');
    h.draw(0, 25); assert.equal(h.total.textContent, 'Total de listados: 0 (de 25 disponibles)');
    h.draw(0, 0); assert.equal(h.total.textContent, 'Total de listados: 0');
});

test('when the library is unavailable the normal table, total and Laravel pagination remain', () => {
    for (const options of [{missingLibrary: true}, {missingTable: true}]) {
        const h = harness(options); assert.equal(h.config, undefined); assert.equal(h.fallback.hidden, false);
        assert.equal(h.total.textContent, 'Total de listados: 25');
    }
});
