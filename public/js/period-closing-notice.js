document.addEventListener('DOMContentLoaded', () => {
    const notice = document.getElementById('period-closing-notice');
    if (!notice) return;
    const button = notice.querySelector('[data-period-closing-dismiss]');
    const previousFocus = document.activeElement;
    let dialog;
    const dismiss = () => {
        if (dialog?.open) dialog.close();
        notice.hidden = true;
        dialog?.remove();
        previousFocus?.focus?.();
    };
    button.hidden = false;
    button.addEventListener('click', dismiss);
    const candidate = document.createElement('dialog');
    if (typeof candidate.showModal !== 'function') return; // Keep a visible, dismissible notice without dialog support.
    dialog = candidate;
    dialog.className = 'period-closing-dialog';
    dialog.setAttribute('aria-labelledby', 'period-closing-title');
    dialog.setAttribute('aria-describedby', 'period-closing-description');
    notice.before(dialog);
    dialog.append(notice);
    dialog.addEventListener('cancel', event => {event.preventDefault(); dismiss();});
    try {
        dialog.showModal();
        button.focus();
    } catch (_) {
        dialog.before(notice);
        dialog.remove();
        dialog = null;
    }
});
