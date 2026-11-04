document.addEventListener('DOMContentLoaded', function () {
    const element = document.querySelector('[data-general-project-filter]');
    if (!element) return;
    const selectAll = element.querySelector('[data-project-select-all]');
    const options = () => Array.from(element.options).filter(option => option !== selectAll && option.value && !option.disabled);
    const ids = () => options().filter(option => option.selected).map(option => option.value);
    const key = () => ids().sort().join(',');
    const initial = key();
    const scope = document.getElementById('general-project-scope');
    const main = document.getElementById('general-report-filters');
    const reported = document.getElementById('general-reported-filter');
    const exportButton = document.getElementById('general-report-export');
    const status = document.getElementById('general-project-status');
    let control, submitting = false;
    if (selectAll) selectAll.disabled = options().length === 0;
    const selectEveryProject = () => {
        if (!selectAll?.selected) return;
        options().forEach(option => {option.selected = true;});
        selectAll.selected = false;
        control?.trigger('change.select2');
    };
    const sync = () => {
        document.querySelectorAll('[data-general-project-inputs]').forEach(container => {
            container.replaceChildren(...ids().map(id => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'project_ids[]'; input.value = id;
                return input;
            }));
        });
    };
    const applyScope = () => {if (!submitting && key() !== initial) scope.requestSubmit();};
    element.addEventListener('change', () => {
        selectEveryProject(); sync();
        status.textContent = key() !== initial ? 'Cierre la lista o pulse «Aplicar proyectos» para actualizar los filtros.' : '';
    }, true);
    scope.addEventListener('submit', () => {
        selectEveryProject(); sync(); submitting = true;
        scope.querySelector('[name="reported"]').value = document.getElementById('general_reported').value;
        status.textContent = 'Actualizando filtros de los proyectos seleccionados…';
    });
    const guard = event => {
        if (key() === initial) return;
        event.preventDefault(); event.stopImmediatePropagation(); applyScope();
    };
    main.addEventListener('submit', guard, true);
    exportButton?.addEventListener('click', guard, true);
    // Do not let the reporting-status handler re-add periods from the old project scope.
    reported.addEventListener('submit', event => {sync(); guard(event);}, true);
    if (!window.jQuery?.fn?.select2) return;
    control = window.jQuery(element).select2({width: '100%', multiple: true, allowClear: true,
        closeOnSelect: false, placeholder: element.dataset.placeholder,
        language: {noResults: () => 'No se encontraron proyectos'}});
    control.on('change.generalProjects', event => {
        selectEveryProject();
        if (!event.originalEvent) element.dispatchEvent(new Event('change', {bubbles: true}));
    });
    control.on('select2:close.generalProjects', applyScope);
});
