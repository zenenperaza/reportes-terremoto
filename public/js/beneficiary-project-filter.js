document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-beneficiary-project-filter]').forEach(element => {
        const selectAll = element.querySelector('[data-project-select-all]');
        const availableOptions = () => Array.from(element.options).filter(option => option !== selectAll && option.value !== '' && !option.disabled);
        if (selectAll) selectAll.disabled = availableOptions().length === 0;
        let control = null;
        const scopeForm = document.getElementById('beneficiary-project-scope');
        const mainForm = document.getElementById('beneficiary-report-filters');
        const reportedForm = document.getElementById('beneficiary-reported-filter');
        const exportButton = document.getElementById('beneficiary-export-button');
        const status = document.getElementById('summary-project-status');
        const ids = () => availableOptions().filter(option => option.selected).map(option => option.value);
        const key = () => ids().sort().join(',');
        const initial = key();
        let submitting = false;

        const applySelectAll = () => {
            if (!selectAll?.selected) return;
            availableOptions().forEach(option => { option.selected = true; });
            // UI action only: never submit this sentinel as a project ID.
            selectAll.selected = false;
            control?.trigger('change.select2');
        };
        const syncContext = () => {
            document.querySelectorAll('[data-project-scope-inputs]').forEach(container => {
                container.replaceChildren(...ids().map(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'project_ids[]';
                    input.value = id;
                    return input;
                }));
            });
        };
        const pending = () => key() !== initial;
        element.addEventListener('change', () => {
            applySelectAll();
            syncContext();
            if (status) status.textContent = pending() ? 'Cierre la lista o pulse «Aplicar proyectos» para actualizar los filtros.' : '';
        }, true);
        element.form?.addEventListener('submit', applySelectAll);
        scopeForm?.addEventListener('submit', () => {
            applySelectAll();
            syncContext();
            submitting = true;
            if (status) status.textContent = 'Actualizando filtros de los proyectos seleccionados…';
        });
        const applyScope = () => { if (!submitting && pending()) scopeForm?.requestSubmit(); };
        const guardStaleFilters = event => {
            if (!pending()) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            applyScope();
        };
        mainForm?.addEventListener('submit', guardStaleFilters, true);
        exportButton?.addEventListener('click', guardStaleFilters, true);
        reportedForm?.addEventListener('submit', () => {
            syncContext();
            if (pending()) document.getElementById('reported-periods')?.replaceChildren();
        }, true);

        if (!window.jQuery?.fn?.select2) return;
        control = window.jQuery(element).select2({
            width: '100%', multiple: true, allowClear: true, closeOnSelect: false,
            placeholder: element.dataset.placeholder,
            language: {noResults: () => 'No se encontraron proyectos'},
        });
        control.on('change.beneficiaryProjects', event => {
            applySelectAll();
            if (!event.originalEvent) element.dispatchEvent(new Event('change', {bubbles: true}));
        });
        // Let users select all and remove individual projects before refreshing the page.
        control.on('select2:close.beneficiaryProjects', applyScope);
    });
});
