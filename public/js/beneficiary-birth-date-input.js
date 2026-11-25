(function (window) {
    'use strict';
    const mask = digits => digits.slice(0, 2) + (digits.length >= 2 ? '/' : '') + digits.slice(2, 4) + (digits.length >= 4 ? '/' : '') + digits.slice(4, 8);
    const toISO = text => {
        const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(text);
        if (!match) return '';
        const [, day, month, year] = match;
        const d = Number(day), m = Number(month), y = Number(year);
        const leap = y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0);
        const days = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        return y > 0 && m >= 1 && m <= 12 && d >= 1 && d <= days[m - 1] ? `${year}-${month}-${day}` : '';
    };
    const display = iso => /^\d{4}-\d{2}-\d{2}$/.test(iso) ? iso.split('-').reverse().join('/') : '';

    window.SiaBirthDateInput = {
        mount(input, canonical, reference) {
            let lastISO = null;
            const validate = () => {
                const date = toISO(input.value);
                const error = input.value && !date ? 'Escriba una fecha válida en formato día/mes/año.'
                    : date && reference() && date > reference() ? 'La fecha de nacimiento no puede ser posterior a la fecha de atención.' : '';
                input.setCustomValidity(error);
                return date;
            };
            const sync = () => {
                if (canonical.value !== lastISO) {
                    input.value = display(canonical.value);
                    lastISO = canonical.value;
                }
                validate();
            };
            const update = () => {
                const raw = input.value;
                let position = input.selectionStart ?? raw.length;
                let digitCount = raw.slice(0, position).replace(/\D/g, '').length;
                if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                    input.value = display(raw);
                    digitCount = 8;
                }
                const digits = input.value.replace(/\D/g, '').slice(0, 8);
                input.value = mask(digits);
                position = 0;
                for (let seen = 0; position < input.value.length && seen < digitCount; position++) {
                    if (/\d/.test(input.value[position])) seen++;
                }
                if (input.value[position] === '/') position++;
                input.setSelectionRange(position, position);
                canonical.value = validate();
                canonical.dataset.manualRevision = String(Number(canonical.dataset.manualRevision || 0) + 1);
                lastISO = canonical.value;
                canonical.dispatchEvent(new Event('input', {bubbles: true}));
            };
            input.addEventListener('input', update);
            input.addEventListener('beforeinput', event => {
                const cursor = input.selectionStart;
                if (event.inputType !== 'deleteContentBackward' || cursor !== input.selectionEnd || input.value[cursor - 1] !== '/') return;
                event.preventDefault();
                input.value = input.value.slice(0, cursor - 2) + input.value.slice(cursor);
                input.setSelectionRange(cursor - 2, cursor - 2);
                update();
            });
            canonical.addEventListener('input', sync);
            canonical.addEventListener('change', sync);
            sync();
            return {sync, clear() {
                canonical.value = ''; lastISO = ''; input.value = ''; input.setCustomValidity('');
                input.classList.remove('voice-recognized', 'voice-review');
            }};
        },
    };
})(window);
