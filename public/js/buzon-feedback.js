document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-buzon-entry], form[data-public-buzon-form]').forEach(form => {
        const contact = form.querySelector('[data-buzon-contact]');
        const name = form.querySelector('input[name="full_name"]');
        const phone = form.querySelector('input[name="phone"]');
        if (!contact || !name || !phone) return;
        const radios = Array.from(form.querySelectorAll('input[name="feedback_type"]'));
        const sync = () => {
            const isRequest = radios.some(radio => radio.checked && radio.value === 'request');
            name.required = isRequest;
            phone.required = isRequest;
            // Never clear or disable existing data when changing the feedback type.
            contact.hidden = false;
            contact.querySelectorAll('[data-buzon-contact-label]').forEach(label => {
                label.textContent = label.dataset.buzonContactLabel + (isRequest ? ' *' : ' (opcional)');
            });
        };
        radios.forEach(radio => radio.addEventListener('change', sync));
        [name, phone].forEach(input => input.addEventListener('input', sync));
        window.addEventListener('pageshow', sync);
        sync();
    });
});
