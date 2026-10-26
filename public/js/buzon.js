document.addEventListener('DOMContentLoaded', function () {
    const projects = document.getElementById('buzon-project-filter');
    if (projects && window.jQuery?.fn?.select2) {
        window.jQuery(projects).select2({width: '100%', closeOnSelect: false, placeholder: projects.dataset.placeholder});
    }
    const from = document.getElementById('buzon-from');
    const to = document.getElementById('buzon-to');
    if (from && to) {
        const validateDates = () => {
            const invalid = from.value && to.value && from.value > to.value;
            to.setCustomValidity(invalid ? 'La fecha hasta no puede ser anterior a la fecha desde.' : '');
            to.min = from.value || '';
            from.max = to.value || '';
        };
        from.addEventListener('input', validateDates);
        to.addEventListener('input', validateDates);
        validateDates();
    }
    const disabilities = Array.from(document.querySelectorAll('[data-buzon-entry] input[name="disabilities[]"]'));
    disabilities.forEach(input => input.addEventListener('change', () => {
        if (!input.checked) return;
        disabilities.forEach(other => {
            if (other !== input && (input.value === 'none' || other.value === 'none')) other.checked = false;
        });
    }));
});
