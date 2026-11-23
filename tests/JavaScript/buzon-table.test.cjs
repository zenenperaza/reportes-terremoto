const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const fields = ['opinion_date', 'project', 'place_name', 'opinion', 'feedback_type', 'full_name', 'user_name', 'origin'];
function harness({canExport = true, rows = [], ok = true} = {}) {
    let config, delegated, savedBody, done = 0, disabled = 0, enabled = 0, accepted = false;
    const requests = [];
    const status = {hidden: true, textContent: ''};
    const fallback = {setAttribute(name) {this[name] = true;}};
    const card = {addEventListener(name, handler) {if (name === 'submit') delegated = handler;}};
    const element = {dataset: {url: 'https://example.test/buzon', filters: JSON.stringify({project_ids: [2, 3], from: '2026-11-01', to: '2026-11-30', opinion: 'bad'}), canExport: canExport ? '1' : '0'}, closest: () => card};
    const table = {buttons: () => ({disable() {disabled++;}, enable() {enabled++;}}),
        table: () => ({container: () => ({querySelector: () => ({value: 'Carga'})})}),
        search: () => 'older search', order: () => [[0, 'desc']]};
    function DataTable(target, options) {assert.equal(target, element); config = options; return table;}
    DataTable.render = {text: () => value => String(value).replaceAll('<', '&lt;')};
    const nativeAction = function (event, dt, node, options, resolve) {
        const data = {body: [['only the visible page']]};
        options.exportOptions.customizeData(data);
        savedBody = data.body;
        resolve();
    };
    DataTable.ext = {buttons: {excelHtml5: {action: nativeAction}, pdfHtml5: {action: nativeAction}}};
    const window = {location: {href: 'https://example.test/buzon'}, confirm: () => accepted};
    vm.runInNewContext(fs.readFileSync('public/js/buzon-table.js', 'utf8'), {
        window, DataTable, URL,
        fetch: async (url, options) => {requests.push({url, options}); return {ok, json: async () => ({data: rows})};},
        document: {addEventListener(name, fn) {fn();}, getElementById: id => id === 'buzon-table' ? element : status, querySelector: () => fallback},
    });
    return {config, requests, status, fallback, get body() {return savedBody;}, get disabled() {return disabled;}, get enabled() {return enabled;}, get done() {return done;},
        confirm(value) {accepted = value;}, submit(event) {delegated(event);},
        async export(format) {const button = config.layout.topStart[1].buttons.find(item => item.extend === format + 'Html5'); await button.action({}, table, {}, button, () => done++);}};
}

test('server pagination, responsive layout, search and local fallback are configured', () => {
    const h = harness();
    assert.equal(h.config.serverSide, true);
    assert.equal(h.config.processing, true);
    assert.equal(h.config.pageLength, 20);
    assert.equal(h.config.responsive.details.target, 0);
    assert.equal(h.config.layout.topEnd, 'search');
    const request = {};
    h.config.ajax.data(request);
    assert.deepEqual(JSON.parse(JSON.stringify(request.project_ids)), [2, 3]);
    assert.equal(request.opinion, 'bad');
    assert.equal(h.config.columns[8].searchable, false);
    assert.equal(h.config.columns[5].render('<img>'), '&lt;img>');
    assert.equal(h.fallback.hidden, true);
    assert.equal(harness({canExport: false}).config.layout.topStart, 'pageLength');
});

test('Excel fetches ALL matches, carries filters and latest search, and protects formula-like cells', async () => {
    const rows = Array.from({length: 31}, () => Object.fromEntries(fields.map(field => [field, 'Texto'])));
    rows[0].full_name = ' =HYPERLINK("bad")';
    const h = harness({rows});
    await h.export('excel');
    assert.equal(h.body.length, 31);
    assert.equal(h.body[0][5], '\' =HYPERLINK("bad")');
    const {url, options} = h.requests[0];
    assert.deepEqual(url.searchParams.getAll('project_ids[]'), ['2', '3']);
    assert.equal(url.searchParams.get('search[value]'), 'Carga');
    assert.equal(url.searchParams.get('export_type'), 'excel');
    assert.equal(url.searchParams.get('opinion'), 'bad');
    assert.equal(url.searchParams.get('from'), '2026-11-01');
    assert.equal(url.searchParams.get('order[0][dir]'), 'desc');
    assert.equal(url.searchParams.has('length'), false);
    assert.equal(options.credentials, 'same-origin');
    assert.equal(options.cache, 'no-store');
    assert.equal(h.enabled, 1);
    assert.equal(h.done, 1);
});

test('PDF keeps literal text without interpreting HTML and uses landscape A3', async () => {
    const rows = [Object.fromEntries(fields.map(field => [field, '<img src=x onerror=alert(1)>']))];
    const h = harness({rows});
    const button = h.config.layout.topStart[1].buttons[1];
    assert.equal(button.orientation, 'landscape');
    assert.equal(button.pageSize, 'A3');
    await h.export('pdf');
    assert.equal(h.body[0][0], '<img src=x onerror=alert(1)>');
    assert.equal(h.requests[0].url.searchParams.get('export_type'), 'pdf');
});

test('failed authorization or invalid payload produces no export and restores buttons', async () => {
    for (const options of [{ok: false}, {rows: [{invalid: 'data'}]}]) {
        const h = harness(options);
        await h.export('excel');
        assert.equal(h.body, undefined);
        assert.equal(h.disabled, 1);
        assert.equal(h.enabled, 1);
        assert.equal(h.done, 1);
        assert.match(h.status.textContent, /No se pudo|no es válida/);
    }
});

test('delete confirmation is delegated across redraws and repeated submissions are blocked', () => {
    const h = harness();
    const button = {disabled: false};
    const form = {dataset: {}, querySelector: () => button};
    let prevented = 0;
    const event = {target: {closest: () => form}, defaultPrevented: false, preventDefault() {prevented++;}};
    h.submit(event);
    assert.equal(prevented, 1);
    assert.equal(button.disabled, false);
    h.confirm(true);
    h.submit(event);
    assert.equal(button.disabled, true);
    assert.equal(form.dataset.deleting, '1');
    h.submit(event);
    assert.equal(prevented, 2);
    h.submit({...event, defaultPrevented: true});
    assert.equal(prevented, 2);
});
