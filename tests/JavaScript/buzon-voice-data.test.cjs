const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const window = {};
vm.runInNewContext(fs.readFileSync('public/js/buzon-voice-data.js', 'utf8'), {window});
const parse = text => JSON.parse(JSON.stringify(window.BuzonVoiceData.parse(text)));

test('one explicitly labeled phrase fills the data shown at the top of the Buzón', () => {
    assert.deepEqual(parse('proyecto FUNDANA, fecha 30 de octubre de 2026, lugar Comunidad San José, nombre María González, edad 34 años, género femenino, teléfono 0414 123 4567, discapacidad ninguna, afrodescendiente no'), {
        values: {place_name: 'Comunidad San José', full_name: 'María González', age: '34', gender: 'female', phone: '04141234567', disabilities: ['none'], afro_descendant: '0'}, issues: [],
    });
});

test('beneficiary-style leading name age and gender are recognized without inventing other data', () => {
    const result = parse('María González, 34 años, mujer, teléfono 0414 123 4567');
    assert.deepEqual(result.values, {full_name: 'María González', age: '34', gender: 'female', phone: '04141234567'});
});

test('projects are ignored regardless of code or name and do not become another field', () => {
    for (const text of ['proyecto FUNDANA', 'proyecto desconocido', 'proyecto FUN', 'proyecto 99']) {
        assert.deepEqual(parse(text), {values: {}, issues: []});
    }
    assert.deepEqual(parse('nombre Ana Pérez, proyecto FUNDANA, lugar Comunidad San José').values, {place_name: 'Comunidad San José', full_name: 'Ana Pérez'});
});

test('all date formats and even invalid dates are ignored without producing review errors', () => {
    for (const text of ['fecha 30/10/2026', 'fecha 2026-10-30', 'fecha 30 de octubre del 2026', 'fecha 31/02/2026', 'fecha mañana']) {
        assert.deepEqual(parse(text), {values: {}, issues: []});
    }
    assert.deepEqual(parse('nombre Ana Pérez, fecha de la opinión 30/10/2026, edad 22 años').values, {full_name: 'Ana Pérez', age: '22'});
});

test('ages support common number words and valid extremes while invalid ages stay untouched', () => {
    for (const [text, expected] of [['cero', '0'], ['veintidós', '22'], ['treinta y cuatro años', '34'], ['cien', '100'], ['ciento veinte', '120']]) assert.equal(parse('edad ' + text).values.age, expected);
    for (const text of ['121', '-1', 'desconocida']) assert.equal(parse('edad ' + text).values.age, undefined);
});

test('phones preserve leading zeros, support individual spoken digits and reject uncertain words', () => {
    assert.equal(parse('teléfono cero cuatro uno cuatro uno dos tres cuatro cinco seis siete').values.phone, '04141234567');
    assert.equal(parse('celular +58 414 123 4567').values.phone, '+584141234567');
    assert.equal(parse('teléfono cuatrocientos catorce').values.phone, undefined);
    assert.equal(parse('teléfono 123').values.phone, undefined);
});

test('disabilities do not combine none with another selection, and demographics honor explicit negatives', () => {
    assert.deepEqual(parse('discapacidad visual y auditiva, afrodescendiente no, género no binario').values, {gender: 'non_binary_other', disabilities: ['visual', 'hearing'], afro_descendant: '0'});
    assert.equal(parse('discapacidad ninguna y visual').values.disabilities, undefined);
    assert.equal(parse('afrodescendiente sí').values.afro_descendant, '1');
    assert.equal(parse('nombre anónimo').values.full_name, '');
});

test('opinion and feedback type are only set when explicitly dictated and never inferred from a narrative', () => {
    assert.deepEqual(parse('opinión ni buena ni mala, tipo queja').values, {opinion: 'neutral', feedback_type: 'complaint'});
    assert.deepEqual(parse('La atención fue mala y tengo una queja').values, {});
    assert.deepEqual(parse('guardar formulario').values, {});
    assert.deepEqual(parse('').values, {});
});

test('overlong names and places are not silently truncated', () => {
    assert.equal(parse('nombre ' + 'A'.repeat(151)).values.full_name, undefined);
    assert.equal(parse('lugar ' + 'A'.repeat(201)).values.place_name, undefined);
});
