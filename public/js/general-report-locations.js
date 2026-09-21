document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const form = document.getElementById('general-report-filters');
    if (!form) return;
    const state = document.getElementById('general_state_id');
    const municipality = document.getElementById('general_municipality_id');
    const parish = document.getElementById('general_parish_id');
    const error = document.getElementById('general-locations-error');
    const retry = document.getElementById('general-locations-retry');
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
        Array.from(state.selectedOptions).forEach(option => params.append('state_id[]', option.value));
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

    if (window.jQuery?.fn?.select2) {
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
});
