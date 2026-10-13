const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/service-worker.js', 'utf8');

function harness(windows) {
    const handlers = {}, opened = [];
    let closed = false;
    vm.runInNewContext(source, {URL, self: {
        location: {origin: 'https://sia.example'},
        addEventListener: (name, callback) => {handlers[name] = callback;},
        clients: {matchAll: async () => windows, openWindow: async url => {opened.push(url);}},
    }});
    return {opened, closed: () => closed, click: async () => {
        let pending;
        handlers.notificationclick({notification: {close: () => {closed = true;}}, waitUntil: task => {pending = task;}});
        await pending;
    }};
}

test('clicking a persistent notification focuses SIA without navigating or reloading unsaved forms', async () => {
    const focused = [];
    const page = harness([
        {url: 'https://another.example/', focus: () => {throw Error('wrong origin');}},
        {url: 'https://sia.example/reportes/nuevo', focused: true, focus: () => focused.push('form'), navigate: () => {throw Error('form lost');}},
    ]);
    await page.click();
    assert.deepEqual(focused, ['form']);
    assert.deepEqual(page.opened, []);
    assert.equal(page.closed(), true);
});

test('notification click opens only the SIA root when no SIA window remains', async () => {
    const page = harness([{url: 'https://another.example/', focus: () => {throw Error('wrong origin');}}]);
    await page.click();
    assert.deepEqual(page.opened, ['/']);
});
