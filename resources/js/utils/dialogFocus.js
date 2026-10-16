const FOCUSABLE_SELECTOR =
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function trapFocusWithin(event) {
    const dialog = event.currentTarget;
    if (!(dialog instanceof HTMLElement)) return;

    const focusable = Array.from(dialog.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
        (element) => element instanceof HTMLElement && !element.hasAttribute('hidden'),
    );

    if (!focusable.length) {
        event.preventDefault();
        dialog.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
