(() => {
    const menu = document.getElementById('main-navigation');
    if (!menu) return;
    const panels = [...menu.querySelectorAll('.collapse')];
    const close = panel => {
        panel.classList.remove('show');
        menu.querySelectorAll('[aria-controls]').forEach(trigger => {
            if (trigger.getAttribute('aria-controls') === panel.id) {
                trigger.classList.add('collapsed');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    };
    // Velzon expands the active page's ancestors during initialization.
    // Keep the active highlight, but leave every level closed until clicked.
    const initialize = () => {
        panels.forEach(close);
        panels.forEach(panel => panel.addEventListener('show.bs.collapse', event => {
            if (event.target !== panel) return;
            panel.querySelectorAll('.collapse').forEach(close);
        }));
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
