(function (root) {
    'use strict';
    function primaryIndicatorOptions(items, sectorId, retainedId = null) {
        return items.filter(item => String(item.sectorProjectId) === String(sectorId)
            && (!item.isAssociated || (retainedId != null && String(item.id) === String(retainedId))));
    }
    function togglePrimaryIndicator(select, id) {
        select.value = String(select.value) === String(id) ? '' : String(id);
        select.dispatchEvent(new Event('change', {bubbles: true}));
    }
    function createAssociatedIndicatorPicker(panel) {
        const home = panel?.parentElement;
        const park = () => { if (panel && home) home.append(panel); };
        const mount = host => { if (panel && host) host.append(panel); };
        const selectedIds = () => panel ? Array.from(panel.querySelectorAll('input:checked')).map(input => input.value).sort() : [];
        const updateCount = () => {
            const count = selectedIds().length;
            panel.querySelector('#associated-indicator-count').textContent = `Se guardarán ${count + 1} registro(s) por beneficiario: el principal y ${count} asociado(s).`;
        };
        function render(items, selected = []) {
            if (!panel) return;
            const container = panel.querySelector('#associated-indicator-options');
            container.replaceChildren();
            panel.hidden = !items.length;
            const selection = new Set((Array.isArray(selected) ? selected : []).map(String));
            items.forEach(item => {
                const label = document.createElement('label');
                label.className = 'indicator-card';
                const top = document.createElement('span');
                top.className = 'indicator-card-top';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input';
                input.name = 'associated_indicator_ids[]';
                input.value = String(item.id);
                input.checked = selection.has(String(item.id));
                const code = document.createElement('strong');
                code.textContent = item.code;
                top.append(input, code);
                const description = document.createElement('span');
                description.className = 'indicator-card-description';
                description.textContent = item.title;
                const meta = document.createElement('span');
                meta.className = 'indicator-card-meta';
                meta.textContent = `${item.unit || 'Sin unidad'} · Edad: ${item.ageFrom ?? 0} a ${item.ageTo ?? 120} años`;
                label.append(top, description, meta);
                label.classList.toggle('is-selected', input.checked);
                input.addEventListener('change', () => {
                    label.classList.toggle('is-selected', input.checked);
                    updateCount();
                });
                container.append(label);
            });
            updateCount();
        }
        return {render, selectedIds, park, mount};
    }
    root.createAssociatedIndicatorPicker = createAssociatedIndicatorPicker;
    root.primaryIndicatorOptions = primaryIndicatorOptions;
    root.togglePrimaryIndicator = togglePrimaryIndicator;
    if (typeof module !== 'undefined') module.exports = createAssociatedIndicatorPicker;
})(typeof window !== 'undefined' ? window : globalThis);
