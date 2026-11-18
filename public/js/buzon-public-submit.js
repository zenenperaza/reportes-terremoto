document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-public-buzon-form]');
    if (!form) return;
    const button = form.querySelector('[data-public-buzon-submit]');
    const status = form.querySelector('[data-public-buzon-status]');
    if (!button) return;
    const originalLabel = button.textContent;
    let sending = false;
    form.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if (sending) {event.preventDefault(); return;}
        sending = true;
        button.disabled = true;
        button.textContent = 'Enviando…';
        form.setAttribute('aria-busy', 'true');
        if (status) {status.hidden = false; status.textContent = 'Enviando su opinión. Espere un momento.';}
    });
    window.addEventListener('pageshow', () => {
        sending = false;
        button.disabled = false;
        button.textContent = originalLabel;
        form.removeAttribute('aria-busy');
        if (status) {status.hidden = true; status.textContent = '';}
    });
});
