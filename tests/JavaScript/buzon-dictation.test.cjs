const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/buzon-dictation.js', 'utf8');

function node(extra = {}) {
    return {events: {}, attributes: {}, textContent: '', disabled: false, hidden: false,
        addEventListener(type, handler) {(this.events[type] ||= []).push(handler);},
        fire(type, event = {}) {this.events[type]?.forEach(handler => handler(event));},
        dispatchEvent(event) {this.fire(event.type, event);},
        setAttribute(key, value) {this.attributes[key] = value;},
        classList: {toggle() {}, add() {}}, ...extra};
}

function harness({supported = true, prefixed = false, secure = true, writable = true, startThrows = false, stopThrows = false, constructorThrows = false, withData = false} = {}) {
    const status = node(), preview = node();
    const fields = {comment: node({value: '', maxLength: 10000, readOnly: false}), improvement: node({value: '', maxLength: 10000, readOnly: false})};
    const labels = {comment: node({textContent: 'Dictar comentario'}), improvement: node({textContent: 'Dictar mejora'})};
    const transcript = node({value: '', maxLength: 4000});
    const apply = node(), clear = node(), transcriptPanel = node({open: false});
    const names = ['comment', 'improvement'];
    if (withData) {names.push('datos'); labels.datos = node({textContent: 'Dictar datos'});}
    const buttons = Object.fromEntries(names.map(name => [name, node({
        dataset: {buzonVoiceTarget: name}, querySelector: () => labels[name],
    })]));
    const save = node();
    const panel = node({querySelector: selector => ({
        '[data-buzon-voice-status]': status,
        '[data-buzon-voice-preview]': preview,
        '[data-buzon-data-transcript]': withData ? transcript : null, '[data-buzon-voice-apply]': withData ? apply : null,
        '[data-buzon-voice-clear]': withData ? clear : null, '[data-buzon-transcript-panel]': withData ? transcriptPanel : null,
    })[selector]});
    if (withData) Object.assign(fields, {
        proyecto_id: node({value: '', options: [{value: '7', textContent: 'FUN-01 — FUNDANA', dataset: {voiceAliases: '["FUN-01","FUNDANA"]'}}]}),
        opinion_date: node({value: '2026-10-30', max: '2026-10-30'}),
        full_name: node({value: ''}), age: node({value: ''}), gender: node({value: ''}),
        phone: node({value: ''}), place_name: node({value: ''}), afro_descendant: node({value: ''}), feedback_type: node({value: ''}),
    });
    const disabilities = ['none', 'visual', 'hearing', 'motor'].map(value => node({value, checked: false}));
    const opinions = ['very_good', 'neutral', 'bad'].map(value => node({value, checked: false}));
    const form = node({elements: {namedItem: name => fields[name]},
        querySelector: () => writable ? panel : null,
        querySelectorAll: selector => selector === '[type="submit"]' ? [save]
            : selector === 'input[name="disabilities[]"]' ? disabilities
            : selector === 'input[name="opinion"]' ? opinions : Object.values(buttons),
    });
    const instances = [];
    class Recognition {
        constructor() {if (constructorThrows) throw new Error('Unavailable'); instances.push(this); this.stops = 0; this.aborts = 0;}
        start() {if (startThrows) throw new Error('Denied'); this.onstart?.();}
        stop() {this.stops++; if (stopThrows) throw new Error('Stopped');}
        abort() {this.aborts++;}
        results(values, resultIndex = 0) {
            this.onresult({resultIndex, results: values.map(([transcript, isFinal]) => Object.assign([{transcript}], {isFinal}))});
        }
    }
    const window = node({isSecureContext: secure});
    if (supported) window[prefixed ? 'webkitSpeechRecognition' : 'SpeechRecognition'] = Recognition;
    const document = node({hidden: false, querySelector: () => form});
    document.addEventListener = (type, handler) => {if (type === 'DOMContentLoaded') handler(); else (document.events[type] ||= []).push(handler);};
    if (withData) vm.runInNewContext(fs.readFileSync('public/js/buzon-voice-data.js', 'utf8'), {window});
    vm.runInNewContext(source, {window, document, Event});
    return {status, preview, fields, buttons, labels, form, save, instances, window, document, transcript, apply, clear, transcriptPanel, disabilities, opinions,
        start(name = 'comment') {buttons[name].fire('click'); return instances.at(-1);},
    };
}

test('microphone starts only on button click without a consent checkbox and only one field can listen at once', () => {
    const h = harness();
    assert.equal(h.instances.length, 0);
    assert.equal(h.buttons.comment.disabled, false);
    assert.equal(h.status.hidden, true);
    const r = h.start();
    assert.equal(r.lang, 'es-VE');
    assert.equal(r.continuous, true);
    assert.equal(r.interimResults, true);
    assert.equal(h.buttons.comment.attributes['aria-pressed'], 'true');
    assert.equal(h.buttons.improvement.disabled, true);
    h.start('improvement');
    assert.equal(h.instances.length, 1);
});

test('interim previews never overwrite a field; final results append once and preserve manual edits', () => {
    const h = harness();
    h.fields.comment.value = 'Texto previo.';
    const r = h.start();
    r.results([['Necesitamos', false]]);
    assert.equal(h.fields.comment.value, 'Texto previo.');
    assert.equal(h.preview.textContent, 'Necesitamos');
    r.results([['Necesitamos más información.', true]]);
    r.results([['Necesitamos más información.', true]]);
    assert.equal(h.fields.comment.value, 'Texto previo. Necesitamos más información.');
    h.fields.comment.value += '\nCorrección manual.';
    r.results([['Necesitamos más información.', true], ['Gracias.', true]], 1);
    assert.equal(h.fields.comment.value, 'Texto previo. Necesitamos más información.\nCorrección manual. Gracias.');
    assert.equal(h.fields.improvement.value, '');
});

test('stop waits for the final result before enabling a different field, and later sessions do not duplicate text', () => {
    const h = harness();
    const r = h.start();
    h.start();
    assert.equal(r.stops, 1);
    assert.equal(h.buttons.comment.disabled, true);
    r.results([['Comentario final.', true]]);
    r.onend();
    assert.equal(h.buttons.improvement.disabled, false);
    const next = h.start('improvement');
    next.results([['Mejorar el horario.', true]]);
    next.onend();
    assert.equal(h.fields.comment.value, 'Comentario final.');
    assert.equal(h.fields.improvement.value, 'Mejorar el horario.');
    const last = h.start();
    last.results([['Otro comentario.', true]]);
    assert.equal(h.fields.comment.value, 'Comentario final. Otro comentario.');
});

test('hiding the page discards late results while preserving finalized text', () => {
    const h = harness();
    const r = h.start(); r.results([['Texto confirmado.', true]]);
    h.document.hidden = true; h.document.fire('visibilitychange');
    assert.equal(r.aborts, 1);
    r.results([['No debe añadirse.', true]], 1);
    r.onend();
    assert.equal(h.fields.comment.value, 'Texto confirmado.');
    assert.equal(h.buttons.comment.disabled, false);
});

test('HTML and spoken save commands remain literal text and cannot submit the form', () => {
    const h = harness();
    let submits = 0; h.form.addEventListener('submit', () => submits++);
    const r = h.start(); r.results([['<script>Guardar buzón</script>', true]]); r.onend();
    assert.equal(h.fields.comment.value, '<script>Guardar buzón</script>');
    assert.equal(submits, 0);
});

test('exceeding maximum length keeps the full rejected phrase in the preview without truncating existing text', () => {
    const h = harness(); h.fields.comment.maxLength = 10; h.fields.comment.value = 'Previo';
    const r = h.start(); r.results([['Demasiado largo.', true]]);
    assert.equal(h.fields.comment.value, 'Previo');
    assert.equal(h.preview.textContent, 'Demasiado largo.');
    assert.match(h.status.textContent, /límite/);
    assert.equal(r.stops, 1);
    r.onend();
    assert.equal(h.preview.hidden, false);
});

test('save during dictation stops listening but requires a second manual submission after review', () => {
    const h = harness(); const r = h.start();
    const event = {preventDefault() {this.prevented = true;}};
    h.save.fire('click', event);
    assert.equal(event.prevented, true);
    assert.equal(r.stops, 1);
    r.results([['Último texto.', true]]); r.onend();
    const second = {preventDefault() {this.prevented = true;}};
    h.form.fire('submit', second);
    assert.equal(second.prevented, undefined);
    assert.equal(h.fields.comment.value, 'Último texto.');
});

test('permission, microphone, no-speech and network failures keep existing text and permit manual entry', () => {
    for (const error of ['not-allowed', 'service-not-allowed', 'audio-capture', 'no-speech', 'network', 'aborted', 'unknown']) {
        const h = harness(); h.fields.comment.value = 'Conservar';
        const r = h.start(); r.onerror({error});
        assert.equal(h.fields.comment.value, 'Conservar');
        assert.equal(h.buttons.comment.attributes['aria-pressed'], 'false');
        assert.equal(h.buttons.comment.disabled, false);
        assert.ok(h.status.textContent.length > 15);
        r.onend();
    }
});

test('unsupported and insecure browsers retain writable text fields; prefixed recognition also works', () => {
    for (const options of [{supported: false}, {secure: false}]) {
        const h = harness(options);
        assert.equal(h.status.hidden, false);
        assert.equal(h.buttons.comment.disabled, true);
        assert.equal(h.fields.comment.disabled, false);
        assert.match(h.status.textContent, /escribir/);
    }
    const h = harness({prefixed: true}); h.start();
    assert.equal(h.instances.length, 1);
});

test('startup and stop exceptions reset the controls without losing existing text', () => {
    for (const options of [{startThrows: true}, {constructorThrows: true}, {stopThrows: true}]) {
        const h = harness(options); h.fields.comment.value = 'Conservar';
        h.start(); if (options.stopThrows) h.start();
        assert.equal(h.buttons.comment.attributes['aria-pressed'], 'false');
        assert.equal(h.fields.comment.value, 'Conservar');
    }
});

test('hiding or leaving the page aborts capture, ignores late callbacks and never restarts it', () => {
    for (const lifecycle of ['hidden', 'pagehide']) {
        const h = harness(); const r = h.start();
        if (lifecycle === 'hidden') {h.document.hidden = true; h.document.fire('visibilitychange');}
        else h.window.fire('pagehide');
        r.results([['Texto tardío', true]]); r.onstart(); r.onend();
        assert.equal(h.fields.comment.value, '');
        assert.ok(r.aborts >= 1);
        assert.equal(h.instances.length, 1);
    }
});

test('read-only pages do not construct a recognition session', () => {
    assert.equal(harness({writable: false}).instances.length, 0);
});

test('data dictation fills several explicit fields when listening ends, without changing comments or unnamed fields', () => {
    const h = harness({withData: true});
    h.fields.proyecto_id.value = '2'; h.fields.opinion_date.value = '2026-10-29';
    h.fields.comment.value = 'Queja escrita'; h.fields.improvement.value = 'Mejora escrita'; h.fields.phone.value = '04140000000';
    const r = h.start('datos');
    r.results([['proyecto FUNDANA, lugar Comunidad San José, nombre María González, edad 34 años, género femenino, discapacidad visual, afrodescendiente no, opinión muy buena, tipo sugerencia', true]]);
    assert.equal(h.fields.full_name.value, '');
    r.onend();
    assert.equal(h.fields.proyecto_id.value, '2');
    assert.equal(h.fields.place_name.value, 'Comunidad San José');
    assert.equal(h.fields.full_name.value, 'María González');
    assert.equal(h.fields.age.value, '34');
    assert.equal(h.fields.gender.value, 'female');
    assert.equal(h.fields.afro_descendant.value, '0');
    assert.equal(h.fields.feedback_type.value, 'suggestion');
    assert.equal(h.fields.phone.value, '04140000000');
    assert.equal(h.fields.opinion_date.value, '2026-10-29');
    assert.equal(h.fields.comment.value, 'Queja escrita');
    assert.equal(h.fields.improvement.value, 'Mejora escrita');
    assert.deepEqual(h.disabilities.filter(input => input.checked).map(input => input.value), ['visual']);
    assert.deepEqual(h.opinions.filter(input => input.checked).map(input => input.value), ['very_good']);
});

test('corrected typed phrases can be applied manually without starting a microphone', () => {
    const h = harness({withData: true, supported: false});
    h.transcript.value = 'nombre Ana Pérez, edad veintidós años, teléfono 0414 1234567';
    h.apply.fire('click');
    assert.equal(h.fields.full_name.value, 'Ana Pérez');
    assert.equal(h.fields.age.value, '22');
    assert.equal(h.fields.phone.value, '04141234567');
    assert.equal(h.instances.length, 0);
    h.transcript.value = 'proyecto desconocido, fecha 31/02/2026';
    h.apply.fire('click');
    assert.equal(h.fields.proyecto_id.value, '');
    assert.equal(h.fields.opinion_date.value, '2026-10-30');
    assert.match(h.status.textContent, /Proyecto y fecha no se modifican/);
});

test('voice and manually applied phrases preserve project/date, even if a parser returns excluded keys', () => {
    const h = harness({withData: true});
    h.fields.proyecto_id.value = '2'; h.fields.opinion_date.value = '2026-10-29';
    h.window.BuzonVoiceData.parse = () => ({values: {proyecto_id: '7', opinion_date: '2026-10-30', full_name: 'Ana Pérez'}, issues: []});
    h.transcript.value = 'proyecto FUNDANA, fecha 30/10/2026, nombre Ana Pérez';
    h.apply.fire('click');
    assert.equal(h.fields.proyecto_id.value, '2');
    assert.equal(h.fields.opinion_date.value, '2026-10-29');
    assert.equal(h.fields.full_name.value, 'Ana Pérez');
    const r = h.start('datos'); r.results([[h.transcript.value, true]]); r.onend();
    assert.equal(h.fields.proyecto_id.value, '2');
    assert.equal(h.fields.opinion_date.value, '2026-10-29');
});

test('cancelled data dictation never applies late or provisional personal data', () => {
    const h = harness({withData: true});
    h.fields.full_name.value = 'Nombre conservado';
    const r = h.start('datos');
    r.results([['nombre María González, edad 34 años', false]]);
    h.window.fire('pagehide');
    r.results([['nombre María González, edad 34 años', true]]); r.onend();
    assert.equal(h.fields.full_name.value, 'Nombre conservado');
    assert.equal(h.fields.age.value, '');
});

test('data dictation opens the transcript and clear removes only the transcript, not form data', () => {
    const h = harness({withData: true});
    h.fields.full_name.value = 'Nombre conservado';
    const r = h.start('datos');
    assert.equal(h.transcriptPanel.open, true);
    assert.equal(h.status.hidden, false);
    r.results([['nombre María González', true]]);
    h.clear.fire('click');
    assert.equal(r.aborts, 1);
    assert.equal(h.transcript.value, '');
    assert.equal(h.fields.full_name.value, 'Nombre conservado');
    r.onend();
    assert.equal(h.fields.full_name.value, 'Nombre conservado');
});
