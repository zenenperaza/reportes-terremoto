(function () {
    'use strict';

    var banner = document.getElementById('system-maintenance-banner');
    if (!banner) return;

    function updateOffset() {
        document.body.style.setProperty('--maintenance-banner-height', Math.ceil(banner.getBoundingClientRect().height) + 'px');
    }

    updateOffset();
    // Text wrapping, zoom and font loading can change the banner's actual height.
    if (window.ResizeObserver) {
        new window.ResizeObserver(updateOffset).observe(banner);
    }
    window.addEventListener('resize', updateOffset);
}());
