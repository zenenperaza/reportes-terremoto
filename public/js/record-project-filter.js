document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-record-project-filter]').forEach(element => {
        const selectAll = element.querySelector('[data-project-select-all]');
        const options = () => Array.from(element.options).filter(option => option !== selectAll && option.value !== '' && !option.disabled);
        if (selectAll) selectAll.disabled = options().length === 0;
        let control = null;
        const applySelectAll = () => {
            if (!selectAll?.selected) return;
            options().forEach(option => {option.selected = true;});
            // UI action only: never submit a sentinel instead of a project ID.
            selectAll.selected = false;
            control?.trigger('change.select2');
        };
        element.addEventListener('change', applySelectAll);
        element.form?.addEventListener('submit', applySelectAll);
        if (!window.jQuery?.fn?.select2) return;
        control = window.jQuery(element).select2({
            width: '100%', multiple: true, allowClear: true, closeOnSelect: false,
            placeholder: element.dataset.placeholder,
            language: {noResults: () => 'No se encontraron proyectos'},
        });
        control.on('change.recordProjects', event => {
            applySelectAll();
            if (!event.originalEvent) element.dispatchEvent(new Event('change', {bubbles: true}));
        });
    });
});
