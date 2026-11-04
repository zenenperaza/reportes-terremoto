document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-buzon-delete]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.confirm('¿Eliminar este formulario del Buzón? Esta acción es permanente y no se puede deshacer.')) {
                event.preventDefault();
            } else {
                form.querySelector('button[type="submit"]').disabled = true;
            }
        });
    });
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
