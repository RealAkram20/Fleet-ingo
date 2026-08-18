import './bootstrap';

/*
 * All behaviour is delegated from the document and driven by data attributes.
 * No inline handlers anywhere: the Content-Security-Policy forbids them along
 * with eval, which is also why there is no framework here.
 */

/*
 * Show/hide for password boxes.
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

/*
 * Confirmation before anything destructive or irreversible.
 *
 *   <form data-confirm="Remove this bike?">
 *
 * Replaces onsubmit="return confirm(...)", which the CSP silently disables —
 * a form with a dead inline handler would just submit with no question asked.
 */
document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');

    if (form && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

/*
 * A select that navigates when changed — the bike picker on Log Reading, so
 * the history below follows the chosen bike.
 *
 *   <select data-navigate="/readings?bike=">
 *
 * The value is appended to the URL in the attribute.
 */
document.addEventListener('change', (event) => {
    const select = event.target.closest('select[data-navigate]');

    if (select) {
        window.location = select.dataset.navigate + encodeURIComponent(select.value);
    }
});

/*
 * On a phone the tab strip scrolls sideways; make sure the current tab is
 * actually on screen rather than off to the right.
 */
const currentTab = document.querySelector('[data-tabs] [aria-current="page"]');

if (currentTab) {
    const strip = currentTab.closest('[data-tabs]');

    if (strip.scrollWidth > strip.clientWidth) {
        strip.scrollLeft = currentTab.offsetLeft - (strip.clientWidth - currentTab.offsetWidth) / 2;
    }
}
