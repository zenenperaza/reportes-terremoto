(() => {
    'use strict';
    const picker = document.getElementById('summary-indicator-picker');
    if (!picker) return;
    const sector = document.getElementById('summary_sector_id');
    const search = document.getElementById('summary-indicator-search');
    const all = document.getElementById('summary-indicator-all');
    const clear = document.getElementById('summary-indicator-clear');
    const selection = document.getElementById('summary-indicator-selection');
    const empty = document.getElementById('summary-indicator-empty');
    const panel = document.getElementById('summary-indicator-panel');
    const toggle = document.getElementById('summary-indicator-toggle');
    const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const cards = [...picker.querySelectorAll('[data-indicator-card]')].map(element => ({
        element,
        input: element.querySelector('input[type="checkbox"]'),
        sectors: JSON.parse(element.dataset.sectors),
        text: normalize(element.dataset.search),
    }));
    const groups = [...picker.querySelectorAll('[data-indicator-group]')];
    const groupSelections = groups.map(group => ({
        control: group.querySelector('[data-group-all]'),
        inputs: [...group.querySelectorAll('[data-indicator-card]')].map(card => card.querySelector('input[type="checkbox"]')),
    }));
    const syncBulkControl = (control, inputs) => {
        const available = inputs.filter(input => !input.disabled);
        const checked = available.filter(input => input.checked).length;
        control.disabled = available.length === 0;
        control.checked = available.length > 0 && checked === available.length;
        control.indeterminate = checked > 0 && checked < available.length;
    };
    const syncSelection = () => {
        const selected = cards.filter(card => !card.input.disabled && card.input.checked);
        cards.forEach(card => card.element.classList.toggle('is-selected', card.input.checked));
        selection.textContent = selected.length
            ? `${selected.length} ${selected.length === 1 ? 'indicador seleccionado' : 'indicadores seleccionados'}`
            : 'Todos los indicadores (sin filtro)';
        syncBulkControl(all, cards.map(card => card.input));
        groupSelections.forEach(group => syncBulkControl(group.control, group.inputs));
        clear.disabled = selected.length === 0;
    };
    const filterCards = () => {
        const query = normalize(search.value.trim());
        cards.forEach(card => {
            const available = !sector.value || card.sectors.includes(sector.value);
            card.input.disabled = !available;
            if (!available) card.input.checked = false;
            // Searching only changes visibility, never the submitted selection.
            card.element.hidden = !available || !card.text.includes(query);
        });
        groups.forEach(group => {
            const count = [...group.querySelectorAll('[data-indicator-card]')].filter(card => !card.hidden).length;
            group.hidden = count === 0;
            group.querySelector('[data-group-count]').textContent = String(count);
        });
        empty.hidden = cards.some(card => !card.element.hidden);
        empty.textContent = query ? 'No hay indicadores que coincidan con la búsqueda.' : 'No hay indicadores disponibles para este sector y estado de reporte.';
        syncSelection();
    };
    const notify = () => picker.dispatchEvent(new Event('change', {bubbles: true}));
    all.addEventListener('change', () => {
        const checked = all.checked;
        cards.forEach(card => {card.input.checked = checked && !card.input.disabled;});
        syncSelection();
        notify();
    });
    groupSelections.forEach(group => group.control.addEventListener('change', () => {
        const checked = group.control.checked;
        group.inputs.forEach(input => {input.checked = checked && !input.disabled;});
        syncSelection();
        notify();
    }));
    clear.addEventListener('click', () => {
        cards.forEach(card => {card.input.checked = false;});
        syncSelection();
        notify();
    });
    picker.addEventListener('change', syncSelection);
    sector.addEventListener('change', () => {filterCards(); notify();});
    search.addEventListener('input', filterCards);
    search.addEventListener('keydown', event => {
        if (event.key === 'Enter') event.preventDefault();
    });
    // Keep the panel usable if Bootstrap is not available.
    if (!window.bootstrap?.Collapse) {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            panel.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', String(open));
        });
    }
    filterCards();
})();
