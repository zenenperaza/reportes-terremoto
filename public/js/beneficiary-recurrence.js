(() => {
    'use strict';
    function create({form, inputs, project, status, url, csrf}) {
        let sequence = 0, timer, controller, lastKey = '', inFlight;
        const editing = () => form.dataset.beneficiaryEditing === '1';
        const data = () => Object.fromEntries(['national_id', 'full_name', 'age', 'sex'].map(name => [name, inputs[name].value.trim()]));
        const key = () => JSON.stringify({proyecto_id: project.value, ...data()});
        const notify = text => {status.textContent = text; status.hidden = !text;};
        function cancel() {
            sequence++;
            clearTimeout(timer);
            controller?.abort();
            lastKey = ''; inFlight = null;
            form.dataset.beneficiaryRecurrencePending = '0';
        }
        function configure() {
            inputs.is_recurrent.disabled = !editing();
            inputs.is_recurrent.readOnly = !editing(); // Also excludes this calculated field from voice assignment.
            inputs.is_recurrent.setAttribute('aria-readonly', editing() ? 'false' : 'true');
        }
        function check() {
            clearTimeout(timer);
            configure();
            if (editing()) return Promise.resolve(true); // Corrections are not new attendances.
            const person = data(), currentKey = key();
            const comparable = project.value && (person.national_id || (person.full_name && person.age !== '' && person.sex));
            if (!comparable) {cancel(); inputs.is_recurrent.value = '0'; notify(''); return Promise.resolve(true);}
            if (lastKey === currentKey && inFlight) return inFlight;
            cancel();
            lastKey = currentKey;
            const current = ++sequence;
            controller = new AbortController();
            form.dataset.beneficiaryRecurrencePending = '1';
            notify('Comprobando si el beneficiario ya está registrado en este proyecto…');
            inFlight = (async () => {
                try {
                    const response = await fetch(url, {method: 'POST', cache: 'no-store', signal: controller.signal,
                        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                        body: JSON.stringify({proyecto_id: project.value, ...person})});
                    if (!response.ok) throw new Error('Recurrence lookup failed');
                    const result = await response.json();
                    if (current !== sequence || currentKey !== key() || editing()) return false;
                    inputs.is_recurrent.value = result.is_recurrent ? '1' : '0';
                    notify(result.is_recurrent ? 'Recurrente: este beneficiario ya tiene un registro en el mismo proyecto.' :
                        'No recurrente: no se encontraron registros anteriores de este beneficiario en el proyecto seleccionado.');
                    return true;
                } catch (_) {
                    if (current !== sequence) return false;
                    lastKey = '';
                    notify('No se pudo verificar la recurrencia. Vuelva a intentarlo antes de guardar.');
                    return false;
                } finally {if (current === sequence) form.dataset.beneficiaryRecurrencePending = '0';}
            })();
            return inFlight;
        }
        function changed() {
            cancel();
            if (editing()) return;
            inputs.is_recurrent.value = '0';
            notify('');
            timer = setTimeout(check, 500);
        }
        ['national_id', 'full_name', 'age', 'sex'].forEach(name => {
            inputs[name].addEventListener('input', changed);
            inputs[name].addEventListener('change', () => {void check();});
        });
        project.addEventListener('change', () => {changed(); void check();});
        form.addEventListener('beneficiary-entry-reset', () => {cancel(); configure(); notify('');});
        configure();
        return {check, cancel};
    }
    window.BeneficiaryRecurrence = {create};
})();
