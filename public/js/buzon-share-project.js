document.addEventListener('DOMContentLoaded', () => {
    const section = document.querySelector('[data-buzon-public-link]');
    if (!section) return;
    const select = section.querySelector('[data-public-project]');
    const preview = section.querySelector('[data-public-donor-preview]');
    if (!select || !preview) return;

    const donorCard = (option, showProject = true) => {
        const donor = option.dataset.donorName || 'Donante no disponible';
        const root = document.createElement('span');
        root.className = 'buzon-project-option';
        const avatar = document.createElement('span');
        avatar.className = 'buzon-project-avatar';
        const fallback = document.createElement('span');
        fallback.className = 'buzon-project-fallback';
        fallback.textContent = donor.slice(0, 1).toLocaleUpperCase('es');
        fallback.title = 'Sin logo disponible';
        avatar.append(fallback);
        try {
            if (option.dataset.projectLogo) {
                const url = new URL(option.dataset.projectLogo, location.href);
                if (url.origin !== location.origin || !['https:', 'http:'].includes(url.protocol)) throw new Error('Invalid logo');
                const image = document.createElement('img');
                image.alt = `Logo de ${donor}`;
                image.width = 40; image.height = 40;
                image.addEventListener('error', () => {image.hidden = true; fallback.hidden = false;});
                image.src = url.href;
                fallback.hidden = true;
                avatar.append(image);
            }
        } catch (_) { /* Retain donor name and placeholder if the logo is unavailable. */ }
        const copy = document.createElement('span');
        copy.className = 'buzon-project-copy';
        const name = document.createElement('strong');
        name.textContent = donor;
        copy.append(name);
        const detail = document.createElement('span');
        detail.textContent = showProject
            ? [option.dataset.projectCode, option.dataset.projectTitle].filter(Boolean).join(' — ')
            : 'Donante del proyecto';
        copy.append(detail);
        root.append(avatar, copy);
        return root;
    };
    const updatePreview = () => {
        const option = select.options[select.selectedIndex];
        preview.replaceChildren();
        preview.hidden = !option?.value;
        if (option?.value) preview.append(donorCard(option, false));
    };
    select.addEventListener('change', updatePreview);
    updatePreview();

    // Reuse SIA's local Select2: native <option> elements cannot reliably display images.
    const $ = window.jQuery;
    if (! $?.fn?.select2) return;
    const template = data => data.id && data.element ? $(donorCard(data.element)) : data.text;
    $(select).select2({width: '100%', placeholder: 'Seleccione un proyecto', allowClear: true,
        templateResult: template, templateSelection: template, dropdownParent: $(section),
        language: {noResults: () => 'No se encontraron proyectos', searching: () => 'Buscando…'}})
        .on('select2:select.buzonShare select2:clear.buzonShare', () => {
            // Select2 emits jQuery events. Notify the existing native QR/link listeners once.
            select.dispatchEvent(new Event('change', {bubbles: true}));
        });
});
