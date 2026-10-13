const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/desktop-notifications.js', 'utf8');

function harness({permission = 'default', result = 'granted', supported = true, secure = true,
    storage = new Map(), storageFails = false, userId = '1', constructorFails = false,
    session = new Map([['sia-desktop:' + userId + ':opened', '1']]), initialEvents = [], allowed = true, ackOk = true,
    serviceWorker} = {}) {
    const events = {}, windowEvents = {}, notifications = [];
    let prompts = 0, focused = 0;
    function element() {
        return {textContent: '', disabled: false, attributes: {}, listeners: {}, children: [],
            replaceChildren() {this.children = []; this.textContent = '';}, appendChild(child) {this.children.push(child);},
            setAttribute(key, value) {this.attributes[key] = value;},
            addEventListener(name, callback) {this.listeners[name] = callback;}};
    }
    const toggle = element(), probe = element(), status = element(), testResult = element();
    testResult.hidden = true;
    const timers = new Map();
    let timerId = 0;
    const list = element(), badge = element(), markRead = element(), acknowledgments = [];
    const controls = {dataset: {userId, icon: '/icons/asonacop-app.png', allowed: allowed ? '1' : '0', readUrl: '/sistema/notificaciones/leidas'},
        querySelector: selector => ({'[data-desktop-toggle]': toggle, '[data-desktop-test]': probe, '[data-desktop-status]': status,
            '[data-desktop-test-result]': testResult,
            '[data-notification-list]': list, '[data-notification-count]': badge, '[data-notification-read]': markRead})[selector]};
    class Notification {
        static permission = permission;
        static async requestPermission() {prompts++; Notification.permission = result; return result;}
        constructor(title, options) {
            if (constructorFails) throw new Error('unavailable');
            this.title = title; this.options = options; this.closed = false;
            notifications.push(this);
        }
        close() {this.closed = true;}
    }
    const browser = {isSecureContext: secure, Notification: supported ? Notification : undefined,
            navigator: {serviceWorker},
            setTimeout: callback => {timers.set(++timerId, callback); return timerId;},
            clearTimeout: id => timers.delete(id),
            focus: () => {focused++;}, addEventListener: (name, callback) => {windowEvents[name] = callback;},
            localStorage: {
                getItem(key) {if (storageFails) throw new Error('restricted'); return storage.get(key) ?? null;},
                setItem(key, value) {if (storageFails) throw new Error('restricted'); storage.set(key, value);},
            }, sessionStorage: {
                getItem: key => session.get(key) ?? null,
                setItem: (key, value) => session.set(key, value),
            }};
    vm.runInNewContext(source, {
        document: {getElementById: id => id === 'desktop-notifications' ? controls
            : id === 'desktop-notification-events' ? {textContent: JSON.stringify(initialEvents)} : null,
            addEventListener: (name, callback) => {events[name] = callback;},
            createElement: element, querySelector: () => ({content: 'test-csrf'})},
        window: browser,
        fetch: async (url, options) => {acknowledgments.push({url, options}); return {ok: ackOk};},
    });
    return {toggle, probe, status, testResult, notifications, storage, session, Notification, list, badge, markRead, acknowledgments,
        expireTimers: () => {const pending = [...timers.values()]; timers.clear(); pending.forEach(callback => callback());},
        flush: () => new Promise(resolve => setImmediate(resolve)),
        notify: (...args) => browser.SiaDesktopNotifications.notify(...args),
        prompts: () => prompts, focused: () => focused,
        click: () => toggle.listeners.click(), test: () => probe.listeners.click(),
        emit: (state) => events['sia:maintenance-state']({detail: state}),
        focus: () => windowEvents.focus()};
}
const idle = {enabled: false, scheduled: false};
const scheduled = {enabled: false, scheduled: true, starts_at: '2026-10-09T20:10:00Z', message: 'Guarde su trabajo.'};
const blocked = {enabled: true, scheduled: false, starts_at: scheduled.starts_at};

test('permission is requested only after a click and granting it sends a confirmation', async () => {
    const page = harness();
    page.emit(scheduled);
    assert.equal(page.prompts(), 0);
    assert.equal(page.notifications.length, 0);
    await page.click();
    assert.equal(page.prompts(), 1);
    assert.equal(page.probe.disabled, false);
    assert.equal(page.toggle.attributes['aria-pressed'], 'true');
    assert.match(page.notifications[0].title, /Mantenimiento programado/);
    assert.equal(page.notifications[0].options.icon, '/icons/asonacop-app.png');
    assert.match(page.notifications[0].options.body, /Guarde su trabajo/);
});

test('activation without a pending maintenance shows a test and clicking it only focuses the existing page', async () => {
    const page = harness();
    await page.click();
    assert.match(page.notifications[0].title, /Notificaciones activadas/);
    page.notifications[0].onclick();
    assert.equal(page.focused(), 1);
    assert.equal(page.notifications[0].closed, true);
    page.test();
    assert.match(page.notifications[1].title, /prueba/);
    page.notifications[1].onshow();
});

test('schedule, reschedule, block, and unlock send one notification per state, not per poll', () => {
    const page = harness({permission: 'granted'});
    page.emit(idle);
    assert.equal(page.notifications.length, 0);
    page.emit(scheduled); page.emit(scheduled);
    assert.equal(page.notifications.length, 1);
    page.emit({...scheduled, starts_at: '2026-10-09T20:15:00Z'});
    assert.equal(page.notifications.length, 2);
    page.emit(blocked); page.emit(blocked);
    assert.equal(page.notifications.length, 3);
    page.emit(idle); page.emit(idle);
    assert.equal(page.notifications.length, 4);
    assert.match(page.notifications[3].options.body, /finalizó/);
    page.emit({enabled: true, scheduled: false});
    assert.equal(page.notifications.length, 5);
});

test('cancelling a schedule notifies users and repeated polls are quiet', () => {
    const page = harness({permission: 'granted'});
    page.emit(scheduled); page.emit(idle); page.emit(idle);
    assert.equal(page.notifications.length, 2);
    assert.match(page.notifications[1].options.body, /cancelado/);
});

test('shared browser storage suppresses duplicate schedule notices across tabs and reloads', () => {
    const storage = new Map();
    const first = harness({permission: 'granted', storage});
    const second = harness({permission: 'granted', storage});
    first.emit(scheduled); second.emit(scheduled);
    assert.equal(first.notifications.length, 1);
    assert.equal(second.notifications.length, 0);
    const reload = harness({permission: 'granted', storage});
    reload.emit(scheduled);
    assert.equal(reload.notifications.length, 0);
});

test('user can disable notifications without revoking browser permission; preference is per user', async () => {
    const storage = new Map();
    const page = harness({permission: 'granted', storage});
    await page.click();
    page.emit(scheduled); page.test();
    assert.equal(page.notifications.length, 0);
    assert.equal(page.probe.disabled, true);
    const reload = harness({permission: 'granted', storage});
    reload.emit(scheduled);
    assert.equal(reload.notifications.length, 0);
    const otherUser = harness({permission: 'granted', storage, userId: '2'});
    otherUser.emit(scheduled);
    assert.equal(otherUser.notifications.length, 1);
    await page.click();
    assert.equal(page.prompts(), 0);
    assert.equal(page.notifications.length, 1);
});

test('denied or dismissed permission never sends notifications or repeatedly requests permission automatically', async () => {
    for (const result of ['denied', 'default']) {
        const page = harness({result});
        await page.click();
        page.emit(scheduled);
        page.test();
        assert.equal(page.prompts(), 1);
        assert.equal(page.notifications.length, 0);
        assert.equal(page.probe.disabled, true);
    }
    const denied = harness({permission: 'denied'});
    await denied.click();
    assert.equal(denied.prompts(), 0);
    assert.match(denied.status.textContent, /configuración del navegador/);
});

test('unsupported browsers and insecure origins degrade without prompting', async () => {
    for (const options of [{supported: false}, {secure: false}]) {
        const page = harness(options);
        assert.equal(page.toggle.disabled, true);
        page.emit(scheduled);
        await page.click();
        assert.equal(page.notifications.length, 0);
        assert.equal(page.prompts(), 0);
        assert.match(page.status.textContent, /No disponibles/);
    }
});

test('restricted storage does not break notifications or deduplication within the page', () => {
    const page = harness({permission: 'granted', storageFails: true});
    page.emit(scheduled); page.emit(scheduled);
    assert.equal(page.notifications.length, 1);
});

test('OS/browser failures do not escape into the heartbeat, and permission revocation is respected', () => {
    const failed = harness({permission: 'granted', constructorFails: true});
    assert.doesNotThrow(() => failed.emit(scheduled));
    assert.match(failed.status.textContent, /No se pudo mostrar/);
    const page = harness({permission: 'granted'});
    page.Notification.permission = 'denied';
    page.focus();
    page.emit(scheduled);
    assert.equal(page.notifications.length, 0);
    assert.equal(page.probe.disabled, true);
});

test('invalid state events are ignored', () => {
    const page = harness({permission: 'granted'});
    for (const state of [null, {}, {...scheduled, starts_at: 'invalid'}]) page.emit(state);
    assert.equal(page.notifications.length, 0);
});

test('opening SIA notifies once per tab, not on every navigation or reload', () => {
    const session = new Map();
    const first = harness({permission: 'granted', session});
    assert.equal(first.notifications.length, 1);
    assert.match(first.notifications[0].title, /Bienvenido/);
    const nextPage = harness({permission: 'granted', session});
    assert.equal(nextPage.notifications.length, 0);
    const newTab = harness({permission: 'granted', session: new Map()});
    assert.equal(newTab.notifications.length, 1);
});

test('successful login triggers welcome even in an existing tab; cached event is not repeated', () => {
    const storage = new Map();
    const initialEvents = [{id: 'login-one', kind: 'opened'}];
    const page = harness({permission: 'granted', storage, initialEvents});
    assert.equal(page.notifications.length, 1);
    const cached = harness({permission: 'granted', storage, initialEvents});
    assert.equal(cached.notifications.length, 0);
});

test('CRUD, exports, and errors use fixed messages without accepting arbitrary desktop text', () => {
    const page = harness({permission: 'granted'});
    for (const kind of ['saved', 'updated', 'deleted', 'exported', 'success', 'error']) {
        assert.equal(page.notify(kind), true);
    }
    assert.equal(page.notifications.length, 6);
    assert.match(page.notifications[0].title, /Datos guardados/);
    assert.match(page.notifications[1].title, /Datos actualizados/);
    assert.match(page.notifications[2].title, /Datos eliminados/);
    assert.match(page.notifications[3].title, /Exportación preparada/);
    assert.equal(page.notify('MARIA V12345678'), false);
    assert.equal(page.notify('__proto__'), false);
    assert.equal(page.notifications.length, 6);
});

test('bootstrap actions suppress extra welcome and deduplicate identifiers; distinct operations still notify', () => {
    const initialEvents = [{id: 'save-1', kind: 'saved'}, {id: 'save-1', kind: 'saved'}, null];
    const page = harness({permission: 'granted', session: new Map(), initialEvents});
    assert.equal(page.notifications.length, 1);
    page.notify('saved', 'save-2');
    assert.equal(page.notifications.length, 2);
    const history = JSON.parse(page.storage.get('sia-desktop:1:last-actions'));
    assert.deepEqual(history, ['save-1', 'save-2']);
    for (let i = 0; i < 50; i++) page.notify('updated', 'edit-' + i);
    assert.equal(JSON.parse(page.storage.get('sia-desktop:1:last-actions')).length, 30);
});

test('action notifications honor denied permission, opt-out, and storage restrictions', async () => {
    const denied = harness({permission: 'denied'});
    assert.equal(denied.notify('saved'), false);
    const optedOut = harness({permission: 'granted'});
    await optedOut.click();
    assert.equal(optedOut.notify('deleted'), false);
    const restricted = harness({permission: 'granted', storageFails: true});
    restricted.notify('saved', 'restricted-save');
    restricted.notify('saved', 'restricted-save');
    assert.equal(restricted.notifications.length, 1);
});

const remote = (id = '1') => ({id, kind: 'record_created', title: 'Registro agregado por su equipo',
    body: 'Integrante del equipo agregó un registro.', created_at: '2026-10-09T20:00:00Z'});
const withInbox = items => ({...idle, can_receive_notifications: true, notification_count: items.length, notifications: items});

test('application permission gates activation, own actions, maintenance, and remote notices', async () => {
    const page = harness({permission: 'granted', allowed: false});
    await page.click();
    page.notify('saved');
    page.emit({...scheduled, can_receive_notifications: false, notifications: [remote()], notification_count: 1});
    assert.equal(page.notifications.length, 0);
    assert.equal(page.prompts(), 0);
    assert.equal(page.toggle.disabled, true);
    assert.equal(page.badge.hidden, true);
    assert.match(page.status.textContent, /Recibir notificaciones/);
});

test('remote notices show once and acknowledge only displayed IDs with session and CSRF', async () => {
    const page = harness({permission: 'granted'});
    page.emit(withInbox([remote('10'), remote('11')]));
    await page.flush();
    assert.equal(page.notifications.length, 2);
    assert.equal(page.acknowledgments.length, 1);
    assert.deepEqual(JSON.parse(page.acknowledgments[0].options.body), {ids: ['10', '11']});
    assert.equal(page.acknowledgments[0].options.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(page.acknowledgments[0].options.credentials, 'same-origin');
    assert.equal(page.badge.hidden, true);
    page.emit(withInbox([remote('10')]));
    await page.flush();
    assert.equal(page.notifications.length, 2);
});

test('failed acknowledgments retry without repeating desktop messages', async () => {
    const page = harness({permission: 'granted', ackOk: false});
    page.emit(withInbox([remote()]));
    await page.flush();
    assert.equal(page.badge.textContent, '1');
    page.emit(withInbox([remote()]));
    await page.flush();
    assert.equal(page.notifications.length, 1);
    assert.equal(page.acknowledgments.length, 2);
});

test('denied OS permission still shows the authorized in-app inbox and permits manual acknowledgment', async () => {
    const page = harness({permission: 'denied'});
    const item = {...remote(), body: '<img src=x onerror=alert(1)> agregó un registro.'};
    page.emit(withInbox([item]));
    assert.equal(page.notifications.length, 0);
    assert.equal(page.acknowledgments.length, 0);
    assert.equal(page.list.children[0].children[1].textContent, item.body);
    assert.equal(page.badge.textContent, '1');
    assert.equal(page.markRead.hidden, false);
    page.markRead.listeners.click();
    await page.flush();
    assert.equal(page.acknowledgments.length, 1);
    assert.equal(page.markRead.hidden, true);
});

test('revoking the application permission clears the inbox and disables further notifications', async () => {
    const page = harness({permission: 'denied'});
    page.emit(withInbox([remote()]));
    page.emit({...idle, can_receive_notifications: false, notification_count: 0, notifications: []});
    assert.equal(page.badge.hidden, true);
    assert.equal(page.list.children.length, 0);
    assert.equal(page.toggle.disabled, true);
    page.Notification.permission = 'granted';
    assert.equal(page.notify('saved'), false);
});

test('large backlogs show at most three desktop notices per poll and retain the rest in the inbox', async () => {
    const page = harness({permission: 'granted'});
    page.emit(withInbox(Array.from({length: 8}, (_, i) => remote(String(i + 1)))));
    await page.flush();
    assert.equal(page.notifications.length, 3);
    assert.equal(page.list.children.length, 5);
    assert.equal(page.badge.textContent, '5');
});

test('desktop test reports display events, preserves feedback across polls, and uses a new tag each time', async () => {
    const page = harness({permission: 'granted'});
    const first = page.test();
    assert.equal(page.probe.disabled, true);
    assert.equal(page.testResult.hidden, false);
    assert.match(page.testResult.textContent, /Enviando/);
    await page.test(); // Ignore double clicks while the first attempt is pending.
    assert.equal(page.notifications.length, 1);
    page.notifications[0].onshow();
    await first;
    assert.equal(page.probe.disabled, false);
    assert.match(page.testResult.textContent, /confirmó la notificación/);
    const feedback = page.testResult.textContent;
    page.emit(idle); page.focus();
    assert.equal(page.testResult.textContent, feedback);
    const second = page.test();
    assert.notEqual(page.notifications[1].options.tag, page.notifications[0].options.tag);
    assert.equal(page.notifications[1].options.renotify, true);
    assert.equal(page.notifications[1].options.requireInteraction, true);
    page.notifications[1].onshow();
    await second;
    assert.equal(page.prompts(), 0);
});

test('desktop test prefers persistent PWA notification and reports only API acceptance, not screen visibility', async () => {
    const sent = [];
    const page = harness({permission: 'granted', serviceWorker: {getRegistration: async () => ({
        active: {}, showNotification: async (title, options) => {sent.push({title, options});},
    })}});
    await page.test();
    assert.equal(sent.length, 1);
    assert.equal(page.notifications.length, 0);
    assert.equal(sent[0].options.requireInteraction, true);
    assert.match(page.testResult.textContent, /Prueba enviada al sistema/);
    assert.match(page.testResult.textContent, /Si no ve el aviso/);
    assert.equal(page.probe.disabled, false);
});

test('rejected PWA notifications fall back to desktop and asynchronous display errors are visible', async () => {
    const page = harness({permission: 'granted', serviceWorker: {getRegistration: async () => ({
        active: {}, showNotification: async () => {throw new Error('unavailable');},
    })}});
    const attempt = page.test();
    await page.flush();
    assert.equal(page.notifications.length, 1);
    page.notifications[0].onerror();
    await attempt;
    assert.match(page.testResult.textContent, /no pudo mostrar la prueba/);
    assert.match(page.testResult.className, /text-danger/);
    page.emit(idle);
    assert.match(page.testResult.textContent, /no pudo mostrar la prueba/);
    assert.equal(page.probe.disabled, false);
});

test('constructor failures provide persistent test feedback without breaking the form', async () => {
    const page = harness({permission: 'granted', constructorFails: true});
    await page.test();
    assert.match(page.testResult.textContent, /No se pudo enviar la prueba/);
    page.emit(idle);
    assert.match(page.testResult.textContent, /No se pudo enviar la prueba/);
    assert.equal(page.probe.disabled, false);
});

test('desktop API silence times out honestly and allows another attempt', async () => {
    const page = harness({permission: 'granted'});
    const attempt = page.test();
    page.expireTimers();
    await attempt;
    assert.match(page.testResult.textContent, /no confirmó su visualización/);
    assert.equal(page.probe.disabled, false);
    // A delayed error must still be reported, not replaced by success.
    page.notifications[0].onerror();
    assert.match(page.testResult.textContent, /no pudo mostrar la prueba/);
});

test('a stalled worker send times out without duplicate fallback or false success', async () => {
    const page = harness({permission: 'granted', serviceWorker: {getRegistration: async () => ({
        active: {}, showNotification: () => new Promise(() => {}),
    })}});
    const attempt = page.test();
    await page.flush();
    page.expireTimers();
    await attempt;
    assert.match(page.testResult.textContent, /no confirmó el envío/);
    assert.equal(page.notifications.length, 0);
    assert.equal(page.probe.disabled, false);
});

test('the test never requests permissions or sends notices when disabled or unauthorized', async () => {
    for (const options of [{permission: 'denied'}, {allowed: false}, {secure: false}]) {
        const page = harness(options);
        await page.test();
        assert.equal(page.notifications.length, 0);
        assert.equal(page.prompts(), 0);
        assert.equal(page.testResult.hidden, true);
    }
});

test('late callbacks from an earlier attempt do not overwrite the current test result', async () => {
    const page = harness({permission: 'granted'});
    const first = page.test();
    page.expireTimers();
    await first;
    const second = page.test();
    page.notifications[1].onshow();
    await second;
    const feedback = page.testResult.textContent;
    page.notifications[0].onerror();
    assert.equal(page.testResult.textContent, feedback);
});
