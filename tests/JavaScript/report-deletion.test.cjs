const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function harness(window) {
    vm.runInNewContext(fs.readFileSync('public/js/report-deletion.js', 'utf8'), {window});
    return window.SiaReportDeletion;
}

test('the confirmation requires a trimmed nonempty reason with a 1000-character limit', async () => {
    let options;
    const helper = harness({Swal: {fire: async value => { options = value; return {isConfirmed: true, value: '  Duplicado  '}; }}});
    assert.equal(await helper.requestReason({title: 'Eliminar', text: 'Confirmar'}), 'Duplicado');
    assert.equal(options.input, 'textarea');
    assert.match(options.inputValidator('  '), /motivo/);
    assert.match(options.inputValidator('a'.repeat(1001)), /1000/);
    assert.equal(options.inputValidator('a'.repeat(1000)), null);
    assert.equal(options.inputAttributes.maxlength, '1000');
});

test('cancelling or returning an invalid reason does not authorize deletion', async () => {
    for (const result of [{isConfirmed: false}, {isConfirmed: true, value: '   '}]) {
        const helper = harness({Swal: {fire: async () => result}});
        assert.equal(await helper.requestReason({title: 'Eliminar'}), null);
    }
});

test('the native fallback validates, supports cancellation and trims the result', async () => {
    const answers = [' ', 'a'.repeat(1001), '  Error  '];
    const alerts = [];
    const helper = harness({prompt: () => answers.shift(), alert: message => alerts.push(message)});
    assert.equal(await helper.requestReason({title: 'Eliminar', text: 'Confirmar'}), 'Error');
    assert.equal(alerts.length, 2);
    assert.equal(await harness({prompt: () => null}).requestReason({title: 'Eliminar'}), null);
});

test('all report deletion entry points send the reason', () => {
    const detail = fs.readFileSync('resources/views/reports/show.blade.php', 'utf8');
    const create = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');
    assert.match(detail, /name="deletion_reason"/);
    assert.match(detail, /deleteForm\.elements\.deletion_reason\.value = reason/);
    for (const view of [detail, create]) {
        assert.match(view, /js\/report-deletion\.js/);
        assert.match(view, /body: JSON\.stringify\(\{deletion_reason: reason\}\)/);
    }
});
