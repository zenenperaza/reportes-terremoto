document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const input = document.querySelector('[data-beneficiary-lookup]');
    const form = document.getElementById('report-form');
    const status = document.getElementById('beneficiary-lookup-status');
    if (!input || !form || !status) return;
    const names = ['full_name', 'age', 'sex', 'nationality', 'phone', 'disability', 'ethnicity', 'pregnant_lactating',
        'caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship'];
    const fields = Object.fromEntries(names.map(name => [name, document.getElementById(`beneficiary_${name}`)]));
    const defaults = {nationality: 'Venezolana', disability: 'Ninguna', ethnicity: 'Ninguna', pregnant_lactating: 'Ninguna'};
    const normalize = value => value.toUpperCase().replace(/[\s.\-]+/g, '');
    const valid = value => /^[VE]?\d{5,12}$/.test(value);
    const enabled = () => input.dataset.lookupEnabled === '1' && form.dataset.beneficiaryEditing !== '1';
    let timer, controller, sequence = 0, lastKey = '', observed = normalize(input.value), inFlight = null;
    const filled = new Map();

    const notify = () => form.dispatchEvent(new Event('beneficiary-autofill'));
    const invalidate = () => {
        clearTimeout(timer);
        controller?.abort();
        sequence++;
        inFlight = null;
        form.dataset.beneficiaryLookupPending = '0';
        input.setAttribute('aria-busy', 'false');
    };
    const clearLoaded = () => {
        const changed = [];
        filled.forEach((value, name) => {
            const field = fields[name];
            // Keep anything the registrant corrected manually after the last lookup.
            if (field && field.value === value) {
                field.value = defaults[name] || '';
                changed.push(name);
            }
        });
        filled.clear();
        if (changed.length) notify();
        return changed;
    };
    const lookup = () => {
        clearTimeout(timer);
        const key = normalize(input.value);
        if (!enabled() || !valid(key)) {form.dataset.beneficiaryLookupPending = '0'; return Promise.resolve({status: 'disabled', fields: []});}
        if (lastKey === key && inFlight) return inFlight;
        controller?.abort();
        controller = new AbortController();
        const current = ++sequence;
        lastKey = key;
        form.dataset.beneficiaryLookupPending = '1';
        input.setAttribute('aria-busy', 'true');
        status.textContent = 'Consultando cédula…';
        const snapshot = Object.fromEntries(names.map(name => [name, fields[name]?.value]));
        inFlight = (async () => {
        try {
            const response = await fetch(input.dataset.beneficiaryLookup, {
                method: 'POST', cache: 'no-store', signal: controller.signal,
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                body: JSON.stringify({national_id: key}),
            });
            if (!response.ok) throw new Error('Lookup failed');
            const result = await response.json();
            if (current !== sequence || key !== normalize(input.value) || !enabled()) return {status: 'cancelled', fields: []};
            if (result.ambiguous) {
                status.textContent = 'La cédula tiene datos distintos en los registros disponibles. No se autocompletó: verifique la identidad y escriba los datos manualmente.';
                return {status: 'ambiguous', fields: []};
            }
            if (!result.matched || !result.beneficiary || typeof result.beneficiary !== 'object') {
                status.textContent = 'No se encontraron coincidencias disponibles para esta cédula. Complete los datos manualmente.';
                return {status: 'not-found', fields: []};
            }
            const person = result.beneficiary;
            const updated = [];
            names.forEach(name => {
                const field = fields[name];
                if (!field || field.readOnly || field.value !== snapshot[name]) return;
                let value = person[name] == null ? '' : String(person[name]);
                if (field.options && !Array.from(field.options).some(option => option.value === value && !option.disabled)) value = '';
                field.value = value;
                filled.set(name, field.value);
                updated.push(name);
            });
            notify();
            status.textContent = 'Datos autocompletados del último registro disponible. Revise especialmente edad, teléfono y cuidador. La recurrencia se comprueba automáticamente en el proyecto seleccionado.';
            return {status: 'matched', fields: updated};
        } catch (error) {
            if (current !== sequence || error.name === 'AbortError') return {status: 'cancelled', fields: []};
            lastKey = '';
            status.textContent = 'No se pudo consultar la cédula. Puede completar los datos manualmente o volver a salir del campo para reintentar.';
            return {status: 'error', fields: []};
        } finally {
            if (current === sequence) {
                form.dataset.beneficiaryLookupPending = '0';
                input.setAttribute('aria-busy', 'false');
            }
        }
        })();
        return inFlight;
    };
    const changed = () => {
        const key = normalize(input.value);
        if (key === observed) return;
        observed = key;
        invalidate();
        lastKey = '';
        const cleared = clearLoaded();
        if (!enabled()) return cleared;
        status.textContent = valid(key) ? 'Buscando coincidencias al terminar de escribir…' : 'Ingrese la cédula para consultar coincidencias. Si no la tiene, complete los datos manualmente.';
        if (valid(key)) {
            form.dataset.beneficiaryLookupPending = '1';
            timer = setTimeout(lookup, 650);
        }
        return cleared;
    };
    input.addEventListener('input', changed);
    form.beneficiaryLookup = {
        forVoice(value) {
            input.value = value;
            const cleared = changed() || [];
            const snapshot = Object.fromEntries(cleared.map(name => [name, fields[name].value]));
            return lookup().then(result => ({...result, fields: [...new Set([...(result.fields || []),
                ...cleared.filter(name => fields[name].value === snapshot[name])])]}));
        },
        cancel: invalidate,
    };
    input.addEventListener('blur', lookup);
    form.addEventListener('beneficiary-entry-reset', () => {
        invalidate();
        lastKey = '';
        filled.clear();
        observed = '';
        status.textContent = enabled() ? 'Ingrese la cédula para autocompletar y revise los datos antes de guardar.' : 'En edición, los datos se revisan manualmente; no se reemplazan por los de otra persona.';
    });
    if (!enabled()) status.textContent = 'El autocompletado está disponible al registrar y requiere permiso para ver detalles de registros. Puede completar los datos manualmente.';
});
