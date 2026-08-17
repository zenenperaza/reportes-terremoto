(function () {
    'use strict';

    var desktopBreakpoint = 992;
    var root = document.documentElement;
    var body = document.body;
    var button = document.getElementById('topnav-hamburger-icon');
    var overlay = document.querySelector('.vertical-overlay');
    var storageKey = 'asonacop-sidebar-collapsed';

    if (!button || !body.classList.contains('admin-layout')) {
        return;
    }

    function isDesktop() {
        return window.innerWidth >= desktopBreakpoint;
    }

    function hamburger() {
        return button.querySelector('.hamburger-icon');
    }

    function setExpanded(expanded) {
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.setAttribute('aria-label', expanded ? 'Ocultar menú' : 'Mostrar menú');
        button.classList.toggle('menu-is-collapsed', !expanded);

        var icon = hamburger();
        if (icon) {
            icon.classList.remove('open');
        }
    }

    function restoreDesktopState() {
        var collapsed = window.localStorage.getItem(storageKey) === '1';
        root.setAttribute('data-sidebar-size', collapsed ? 'sm' : 'lg');
        body.classList.remove('vertical-sidebar-enable');
        setExpanded(!collapsed);
    }

    function closeMobileMenu() {
        body.classList.remove('vertical-sidebar-enable');
        setExpanded(false);
    }

    function toggleMenu(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        if (isDesktop()) {
            var collapsed = root.getAttribute('data-sidebar-size') === 'sm';
            root.setAttribute('data-sidebar-size', collapsed ? 'lg' : 'sm');
            window.localStorage.setItem(storageKey, collapsed ? '0' : '1');
            setExpanded(collapsed);
            return;
        }

        var expanded = body.classList.toggle('vertical-sidebar-enable');
        setExpanded(expanded);
    }

    button.addEventListener('click', toggleMenu, true);

    if (overlay) {
        overlay.addEventListener('click', function (event) {
            event.preventDefault();
            closeMobileMenu();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !isDesktop()) {
            closeMobileMenu();
            button.focus();
        }
    });

    window.addEventListener('resize', function () {
        if (isDesktop()) {
            restoreDesktopState();
        } else {
            closeMobileMenu();
        }
    });

    if (isDesktop()) {
        restoreDesktopState();
    } else {
        closeMobileMenu();
    }
}());
