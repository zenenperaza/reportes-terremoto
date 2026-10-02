(() => {
    const select = document.getElementById('records-state-filter');
    if (!select || !window.jQuery?.fn?.select2) return;
    window.jQuery(select).select2({
        width: '100%',
        placeholder: 'Todos los estados',
        allowClear: true,
        closeOnSelect: false,
        language: {noResults: () => 'No se encontraron estados'},
    });
})();
