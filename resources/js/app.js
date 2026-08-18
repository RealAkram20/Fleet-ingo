import './bootstrap';

/*
 * Show/hide for password boxes.
 *
 * Delegated from the document, so it covers fields that were not in the page
 * when it loaded. No framework involved: the Content-Security-Policy forbids
 * eval, and Alpine needs it to evaluate directives.
 */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');

    if (!toggle) {
        return;
    }

    const field = toggle.closest('[data-password-field]');
    const input = field?.querySelector('input');

    if (!input) {
        return;
    }

    const reveal = input.type === 'password';

    input.type = reveal ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(reveal));
    toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');

    field.querySelector('[data-icon="show"]').hidden = reveal;
    field.querySelector('[data-icon="hide"]').hidden = !reveal;
});
