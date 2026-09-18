(function () {
    'use strict';

    var button = document.querySelector('.asonacop-theme-toggle');
    var storageKey = 'data-layout-mode';

    if (!button) {
        return;
    }

    function synchronizeAccessibleState() {
        var darkModeEnabled = document.documentElement.getAttribute('data-layout-mode') === 'dark';
        var actionLabel = darkModeEnabled ? 'Activar tema claro' : 'Activar tema oscuro';
        var icon = button.querySelector('i');

        button.setAttribute('aria-label', actionLabel);
        button.setAttribute('title', actionLabel);
        button.setAttribute('aria-pressed', darkModeEnabled ? 'true' : 'false');

        if (icon) {
            icon.classList.toggle('bx-moon', !darkModeEnabled);
            icon.classList.toggle('bx-sun', darkModeEnabled);
        }
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-layout-mode', theme);
        window.sessionStorage.setItem(storageKey, theme);
        document.querySelectorAll('img[data-logo-light][data-logo-dark]').forEach(function (logo) {
            logo.setAttribute('src', logo.getAttribute(theme === 'dark' ? 'data-logo-dark' : 'data-logo-light'));
        });
        synchronizeAccessibleState();
    }

    button.addEventListener('click', function (event) {
        event.preventDefault();

        var currentTheme = document.documentElement.getAttribute('data-layout-mode');
        applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
    });

    applyTheme(window.sessionStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light');
}());
