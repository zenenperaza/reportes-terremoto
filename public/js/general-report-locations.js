document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const generalForm = document.getElementById('general-report-filters');
    const form = generalForm || document.getElementById('beneficiary-report-filters');
    if (!form) return;
    const prefix = generalForm ? 'general' : 'summary';
    const state = document.getElementById(prefix + '_state_id');
    const municipality = document.getElementById(prefix + '_municipality_id');
    const parish = document.getElementById(prefix + '_parish_id');
    const error = document.getElementById(prefix + '-locations-error');
    const retry = document.getElementById(prefix + '-locations-retry');
    const exportButton = document.getElementById('beneficiary-export-button');
    const submit = form.querySelector('[type="submit"]');
    let controller;
    let sequence = 0;
    let blocked = false;

    function fill(element, items, placeholder, selected = '') {
        element.replaceChildren(new Option(placeholder, ''));
        items.forEach(item => element.add(new Option(item.name, String(item.id))));
        if (items.some(item => String(item.id) === selected)) element.value = selected;
    }

    async function refresh(resetMunicipality) {
        if (controller) controller.abort();
        controller = new AbortController();
        const current = ++sequence;
        const selectedMunicipality = resetMunicipality ? '' : municipality.value;
        const params = new URLSearchParams();
        if (generalForm) {
            Array.from(state.selectedOptions).forEach(option => params.append('state_id[]', option.value));
        } else if (state.value) {
            params.set('state_id', state.value);
        }
        if (selectedMunicipality) params.set('municipality_id', selectedMunicipality);
        fill(municipality, [], 'Cargando...');
        fill(parish, [], 'Cargando...');
        municipality.disabled = parish.disabled = blocked = true;
        if (submit) submit.disabled = true;
        error.hidden = true;

        try {
            const response = await fetch(form.dataset.locationsUrl + '?' + params.toString(), {
                headers: {'Accept': 'application/json'}, signal: controller.signal,
            });
            if (!response.ok) throw new Error('Location request failed');
            const options = await response.json();
            if (current !== sequence) return;
            fill(municipality, options.municipalities, 'Todos', selectedMunicipality);
            fill(parish, options.parishes, 'Todas');
            municipality.disabled = parish.disabled = blocked = false;
            if (submit) submit.disabled = false;
        } catch (failure) {
            if (current !== sequence || failure.name === 'AbortError') return;
            fill(municipality, [], 'No disponible');
            fill(parish, [], 'No disponible');
            error.hidden = false;
        }
    }

    if (generalForm && window.jQuery?.fn?.select2) {
        window.jQuery(state).select2({
            width: '100%', placeholder: 'Todos los estados', allowClear: true, closeOnSelect: false,
            language: {noResults: () => 'No se encontraron estados', searching: () => 'Buscando...'},
        }).on('change', () => refresh(true));
    } else {
        state.addEventListener('change', () => refresh(true));
    }
    municipality.addEventListener('change', () => refresh(false));
    retry.addEventListener('click', () => refresh(true));
    form.addEventListener('submit', event => { if (blocked) event.preventDefault(); });
    exportButton?.addEventListener('click', event => {
        if (blocked) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
});
