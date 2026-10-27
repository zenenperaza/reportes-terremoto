document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery?.fn?.select2) return;
    document.querySelectorAll('[data-summary-indicator-filter]').forEach(element => {
        window.jQuery(element).select2({width: '100%', closeOnSelect: false, placeholder: element.dataset.placeholder});
    });
});
