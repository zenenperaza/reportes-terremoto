(() => {
    'use strict';
    const editor = document.querySelector('#case-editor');
    if (!editor) return;
    const links = [...editor.querySelectorAll('[data-section]')];
    const panels = [...editor.querySelectorAll('[data-panel]')];
    let current = 0;
    const activate = index => {
        current = Math.max(0, Math.min(index, panels.length - 1));
        panels.forEach((panel, i) => panel.classList.toggle('active', i === current));
        links.forEach((link, i) => {
            link.classList.toggle('active', i === current);
            if (i === current) link.setAttribute('aria-current', 'step');
            else link.removeAttribute('aria-current');
        });
        const activeLink = links[current];
        const group = activeLink?.closest('details');
        if (group) group.open = true;
    };
    editor.classList.add('is-enhanced');
    links.forEach((link, i) => link.addEventListener('click', event => { event.preventDefault(); activate(i); }));
    editor.querySelectorAll('[data-step]').forEach(button => button.addEventListener('click', () => {
        activate(current + Number(button.dataset.step));
        links[current].focus();
    }));
    const errorPanel = editor.querySelector('.is-invalid')?.closest('[data-panel]');
    activate(errorPanel ? panels.indexOf(errorPanel) : Math.max(0, panels.findIndex(panel => panel.dataset.panel === editor.dataset.initialSection)));
    editor.querySelectorAll('[data-jump]').forEach(button => button.addEventListener('click', () => {
        activate(panels.findIndex(panel => panel.dataset.panel === button.dataset.jump));
        panels[current]?.querySelector('h2')?.scrollIntoView({block: 'nearest'});
    }));
    if (editor.dataset.readOnly === 'true') {
        editor.addEventListener('submit', event => event.preventDefault());
        return;
    }
    // Llevar al primer campo inválido antes de mostrar la validación nativa.
    // De otro modo el navegador intentaría enfocar controles en pestañas ocultas.
    editor.noValidate = true;
    let dirty = false;
    editor.querySelectorAll('[data-repeater]').forEach(repeater => {
        const rows = repeater.querySelector('[data-rows]');
        const empty = repeater.querySelector('[data-empty]');
        const add = repeater.querySelector('[data-add-row]');
        const sync = () => {
            empty.hidden = rows.children.length > 0;
            if (add) add.disabled = rows.children.length >= 50;
        };
        add?.addEventListener('click', () => {
            if (rows.children.length >= 50) return;
            const template = repeater.querySelector('[data-row-template]');
            const fragment = template.content.cloneNode(true);
            const index = Number(repeater.dataset.next);
            repeater.dataset.next = String(index + 1);
            fragment.querySelectorAll('[name], [id], [for], [aria-labelledby]').forEach(element => {
                ['name', 'id', 'for', 'aria-labelledby'].forEach(attribute => {
                    if (element.hasAttribute(attribute)) element.setAttribute(attribute, element.getAttribute(attribute).replaceAll('__INDEX__', String(index)));
                });
            });
            rows.append(fragment);
            rows.lastElementChild.querySelector('input:not([type=hidden]),select,textarea')?.focus();
            dirty = true; sync();
        });
        repeater.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-row]');
            if (!button) return;
            if (!window.confirm('¿Quitar esta fila? El cambio se aplicará al guardar.')) return;
            button.closest('[data-row]').remove(); dirty = true; sync();
        });
        sync();
    });
    editor.addEventListener('input', () => { dirty = true; });
    editor.addEventListener('change', () => { dirty = true; });
    editor.addEventListener('submit', event => {
        if (pending.size > 0) {
            event.preventDefault();
            activate(panels.findIndex(panel => panel.dataset.panel === 'contact'));
            const status = editor.querySelector('#case-location-status');
            if (status) status.textContent = 'Espere a que termine de cargar la ubicación antes de guardar.';
            return;
        }
        const invalid = editor.querySelector(':invalid');
        if (invalid) {
            event.preventDefault();
            const panel = invalid.closest('[data-panel]');
            if (panel) activate(panels.indexOf(panel));
            let parent = invalid.parentElement;
            while (parent && parent !== editor) { if (parent.tagName === 'DETAILS') parent.open = true; parent = parent.parentElement; }
            invalid.reportValidity();
            return;
        }
        dirty = false;
    });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });

    const birth = editor.querySelector('#case-birth_date');
    const age = editor.querySelector('#case-age_at_registration');
    const syncAge = () => { if (age) { age.disabled = Boolean(birth?.value); if (birth?.value) age.value = ''; } };
    birth?.addEventListener('change', syncAge);
    syncAge();
    const consent = editor.querySelector('#case-consent_status');
    const syncConsent = () => {
        const granted = consent?.value === 'granted';
        ['consent_source', 'consent_date'].forEach(name => { const input = editor.querySelector(`#case-${name}`); if (input) input.required = granted; });
        ['share_services', 'share_reports'].forEach(name => {
            const input = editor.querySelector(`#case-${name}`);
            if (!granted && input) input.value = '0';
        });
    };
    consent?.addEventListener('change', syncConsent);
    syncConsent();

    const state = editor.querySelector('#case-state_id');
    const municipality = editor.querySelector('#case-municipality_id');
    const parish = editor.querySelector('#case-parish_id');
    const reset = select => { if (select) { select.replaceChildren(new Option('Seleccione', '')); select.disabled = false; } };
    const pending = new Map();
    const populate = async (select, url) => {
        if (!select) return;
        pending.get(select)?.abort();
        const controller = new AbortController();
        pending.set(select, controller);
        reset(select);
        select.disabled = true;
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, signal: controller.signal});
            if (!response.ok) throw new Error('No fue posible cargar las ubicaciones.');
            const rows = await response.json();
            rows.forEach(row => select.add(new Option(row.name, row.id)));
        } catch (error) {
            if (error.name !== 'AbortError') {
                select.options[0].text = 'Error al cargar. Seleccione nuevamente la ubicación anterior.';
            }
        } finally {
            if (pending.get(select) === controller) { select.disabled = false; pending.delete(select); }
        }
    };
    state?.addEventListener('change', () => {
        pending.get(municipality)?.abort(); pending.get(parish)?.abort();
        reset(municipality); reset(parish);
        if (state.value) populate(municipality, `${editor.dataset.municipalitiesUrl}/${encodeURIComponent(state.value)}/municipios`);
    });
    municipality?.addEventListener('change', () => {
        pending.get(parish)?.abort(); reset(parish);
        if (municipality.value) populate(parish, `${editor.dataset.parishesUrl}/${encodeURIComponent(municipality.value)}/parroquias`);
    });
})();
