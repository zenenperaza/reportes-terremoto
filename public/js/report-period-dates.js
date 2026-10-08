(() => {
    const select = document.getElementById('reporting-period');
    const form = document.querySelector('form[data-period-dates]');
    if (!select || !form) return;

    const error = document.getElementById('report-period-dates-error');
    const retry = document.getElementById('report-period-dates-retry');
    const orderError = document.getElementById('report-date-order-error');
    const submits = Array.from(form.querySelectorAll('[type="submit"]'));
    const exports = Array.from(document.querySelectorAll('[data-period-date-export]'));
    let controller;
    let sequence = 0;
    let blocked = false;
    const format = value => value.split('-').reverse().join('/');
    const fields = Array.from(form.querySelectorAll('[data-period-date]')).map(input => {
        const picker = typeof window.flatpickr === 'function' ? window.flatpickr(input, {
            locale: 'es', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
            allowInput: true, disableMobile: true,
        }) : null;
        if (picker?.altInput) {
            picker.altInput.id = `${input.id || input.name}_calendar`;
            const labels = Array.from(input.labels || []);
            picker.altInput.placeholder = 'dd/mm/aaaa';
            picker.altInput.setAttribute('aria-label', labels[0]?.textContent.trim() || input.name);
            labels.forEach(label => { label.htmlFor = picker.altInput.id; });
            if (input.getAttribute('aria-describedby')) picker.altInput.setAttribute('aria-describedby', input.getAttribute('aria-describedby'));
            picker.altInput.addEventListener('keydown', event => {
                if (event.key === 'Escape') picker.close();
            });
        }
        const field = {input, picker, help: input.parentElement.querySelector('[data-period-date-help]')};
        input.addEventListener('change', validateDates);
        picker?.altInput?.addEventListener('blur', validateDates);
        return field;
    });

    function validate({input, picker}) {
        const valid = !input.value || (!input.disabled && input.min && input.max
            && /^\d{4}-\d{2}-\d{2}$/.test(input.value) && input.value >= input.min && input.value <= input.max);
        const message = valid ? '' : 'Seleccione una fecha dentro del rango de los registros disponibles.';
        input.setCustomValidity(message);
        picker?.altInput?.setCustomValidity(message);
    }

    function validateDates() {
        fields.forEach(validate);
        const errors = [];
        for (const group of ['attention', 'registered']) {
            const groupFields = fields.filter(({input}) => input.dataset.dateGroup === group);
            const from = groupFields.find(({input}) => input.name === 'from' || input.name.endsWith('_from'));
            const to = groupFields.find(({input}) => input.name === 'to' || input.name.endsWith('_to'));
            if (!from || !to || from.input.disabled || to.input.disabled || !from.input.value || !to.input.value) continue;
            const equalAllowed = form.dataset.dateAllowEqual === '1';
            if (from.input.value > to.input.value || (!equalAllowed && from.input.value === to.input.value)) {
                const label = group === 'attention' ? 'atención' : 'registro';
                const message = equalAllowed
                    ? `La fecha de ${label} «Hasta» no puede ser anterior a «Desde».`
                    : `La fecha de ${label} «Desde» debe ser anterior a «Hasta».`;
                to.input.setCustomValidity(message);
                to.picker?.altInput?.setCustomValidity(message);
                errors.push(message);
            }
        }
        if (orderError) {
            orderError.hidden = errors.length === 0;
            orderError.textContent = errors.join(' ');
        }
    }

    function canProceed(event) {
        validateDates();
        if (blocked || !form.checkValidity() || fields.some(({picker}) => picker?.altInput && !picker.altInput.checkValidity())) {
            event.preventDefault();
            if (!blocked) {
                form.reportValidity();
                const invalid = fields.find(({input, picker}) => !(picker?.altInput || input).checkValidity());
                (invalid?.picker?.altInput || invalid?.input)?.reportValidity();
            }
            return false;
        }
        return true;
    }

    function lock(value) {
        blocked = value;
        form.dataset.periodDatesBlocked = value ? '1' : '0';
        submits.forEach(button => { button.disabled = value || form.dataset.locationsBlocked === '1'; });
        exports.forEach(button => { button.setAttribute('aria-disabled', value ? 'true' : 'false'); });
        if (value) fields.forEach(({input, picker, help}) => {
            input.disabled = true;
            if (picker?.altInput) picker.altInput.disabled = true;
            picker?.close();
            if (help) help.textContent = 'Consultando fechas de los registros…';
        });
    }

    function apply(bounds) {
        // One continuous interval per date source, even when a period spans several months.
        fields.forEach(field => {
            const {input, picker, help} = field;
            const previous = input.value;
            const range = bounds[input.dataset.dateGroup];
            const min = range.min || '';
            const max = range.max || '';
            const disabled = !min || !max;
            input.min = min;
            input.max = max;
            input.disabled = disabled;
            if (picker) {
                picker.set({minDate: min || undefined, maxDate: max || undefined, enable: [() => true]});
                picker.altInput.disabled = disabled;
            }
            if (previous && (disabled || previous < min || previous > max)) {
                if (picker) picker.clear(false);
                else input.value = '';
            } else if (previous && picker) picker.setDate(previous, false, 'Y-m-d');
            validate(field);
            if (help) help.textContent = disabled ? 'Sin fechas registradas disponibles.'
                : `Disponible: ${format(min)} al ${format(max)}.`;
            if (previous !== input.value) input.dispatchEvent(new Event('change', {bubbles: true}));
        });
        validateDates();
        // Refresh export URLs after every input has been re-enabled or cleared.
        form.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function validBounds(bounds) {
        return Array.from(new Set(fields.map(({input}) => input.dataset.dateGroup))).every(group => {
            const range = bounds?.[group];
            return range && ((range.min === null && range.max === null)
                || (/^\d{4}-\d{2}-\d{2}$/.test(range.min) && /^\d{4}-\d{2}-\d{2}$/.test(range.max) && range.min <= range.max));
        });
    }

    async function refresh() {
        controller?.abort();
        controller = new AbortController();
        const current = ++sequence;
        const params = new URLSearchParams();
        params.set('reported', form.querySelector('input[name="reported"]')?.value ?? '');
        const periods = Array.from(select.selectedOptions, option => option.value).filter(Boolean);
        (periods.length ? periods : ['']).forEach(period => params.append('reporting_period[]', period));
        lock(true);
        if (error) error.hidden = true;
        try {
            const response = await fetch(`${form.dataset.dateBoundsUrl}?${params}`, {
                headers: {'Accept': 'application/json'}, signal: controller.signal,
            });
            if (!response.ok) throw new Error('Date bounds request failed');
            const bounds = await response.json();
            if (current !== sequence) return;
            if (!validBounds(bounds)) throw new Error('Invalid date bounds');
            apply(bounds);
            lock(false);
        } catch (failure) {
            if (current !== sequence || failure.name === 'AbortError') return;
            if (error) error.hidden = false;
            fields.forEach(({help}) => { if (help) help.textContent = 'Fechas no disponibles. Reintente la consulta.'; });
        }
    }

    select.addEventListener('change', refresh);
    retry?.addEventListener('click', refresh);
    form.addEventListener('submit', canProceed);
    exports.forEach(button => button.addEventListener('click', event => {
        if (!canProceed(event)) {
            event.stopImmediatePropagation();
        }
    }, true));
    const initial = {};
    fields.forEach(({input}) => {
        initial[input.dataset.dateGroup] = {min: input.dataset.dateMin || null, max: input.dataset.dateMax || null};
    });
    apply(initial);
})();
