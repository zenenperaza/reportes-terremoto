(() => {
    const select = document.getElementById('reporting-period');
    if (!select || !window.jQuery?.fn?.select2) return;
    window.jQuery(select).select2({
        width: '100%',
        placeholder: 'Todos los períodos',
        allowClear: true,
        closeOnSelect: false,
        language: {noResults: () => 'No se encontraron períodos', searching: () => 'Buscando...'},
    }).on('change', event => {
        // Select2 emits a jQuery event; forward it to the native form listeners.
        if (!event.originalEvent) select.dispatchEvent(new Event('change', {bubbles: true}));
    });
})();
