(function () {
    'use strict';

    var root = document.documentElement;
    var body = document.body;
    var button = document.getElementById('topnav-hamburger-icon');
    var menu = document.getElementById('main-navigation');

    if (!button || !menu || root.getAttribute('data-layout') !== 'horizontal') {
        return;
    }

    // Match the horizontal navigation breakpoint in the template stylesheet.
    var mobile = window.matchMedia('(max-width: 1024px)');
    var desktopExpanded = true;
    var mobileExpanded = false;
    var wasMobile = mobile.matches;

    function synchronize() {
        var expanded = mobile.matches ? mobileExpanded : desktopExpanded;
        body.classList.toggle('menu', mobile.matches && expanded);
        body.classList.toggle('horizontal-menu-hidden', !mobile.matches && !expanded);
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.setAttribute('aria-label', expanded ? 'Ocultar menú' : 'Mostrar menú');
        var icon = button.querySelector('.hamburger-icon');
        if (icon) {
            icon.classList.toggle('open', !expanded);
        }
    }

    // Capture prevents the template's mobile-only handler from toggling twice.
    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        if (mobile.matches) {
            mobileExpanded = !mobileExpanded;
        } else {
            desktopExpanded = !desktopExpanded;
        }
        synchronize();
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || !mobile.matches || !mobileExpanded) {
            return;
        }
        mobileExpanded = false;
        synchronize();
        button.focus();
    });

    window.addEventListener('resize', function () {
        if (mobile.matches !== wasMobile) {
            mobileExpanded = false;
            wasMobile = mobile.matches;
        }
        synchronize();
    });

    synchronize();
}());
