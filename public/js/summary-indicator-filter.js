document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-summary-indicator-filter]').forEach(element => {
        const selectAll = element.querySelector('[data-summary-select-all]');
        const availableOptions = () => Array.from(element.options).filter(option => option !== selectAll && option.value !== '' && !option.disabled);
        if (selectAll) selectAll.disabled = availableOptions().length === 0;
        let control = null;

        const applySelectAll = () => {
            if (!selectAll?.selected) return;
            availableOptions().forEach(option => { option.selected = true; });
            // This is a UI action, never an ID submitted to report/export validation.
            selectAll.selected = false;
            control?.trigger('change.select2');
        };
        element.addEventListener('change', applySelectAll);
        element.form?.addEventListener('submit', applySelectAll);

        if (!window.jQuery?.fn?.select2) return;
        control = window.jQuery(element).select2({
            width: '100%', multiple: true, allowClear: true, closeOnSelect: false,
            placeholder: element.dataset.placeholder,
            language: {noResults: () => 'No se encontraron resúmenes'},
        });
        control.on('change.summaryIndicators', event => {
            applySelectAll();
            // Select2 emits jQuery events; notify native filter/export listeners too.
            if (!event.originalEvent) element.dispatchEvent(new Event('change', {bubbles: true}));
        });
    });
});
