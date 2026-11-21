/*
 * Admin: ready-made themes for question choices.
 * Picking a theme fills the sibling "color", "textColor" and "borderColor" inputs
 * of the same choice (inputs share the same name prefix, e.g. Question[choices][0][...]).
 * Changing a color by hand switches the picker back to "Personnalisé".
 */
(function () {
    const FIELDS = ['color', 'textColor', 'borderColor'];
    const ROTATION = ['mint', 'sky', 'rose', 'lavender', 'peach', 'neutral'];

    const prefixOf = (name, field) => name.slice(0, name.length - `[${field}]`.length);
    const input = (form, prefix, field) => form.querySelector(`[name="${CSS.escape(`${prefix}[${field}]`)}"]`);

    function applyTheme(select) {
        const theme = JSON.parse(select.dataset.choiceTheme || '{}')[select.value];
        if (!theme) return;
        const prefix = prefixOf(select.name, 'theme');
        FIELDS.forEach((field) => {
            const el = input(select.form, prefix, field);
            if (el) {
                el.value = theme[field];
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    document.addEventListener('change', (event) => {
        const el = event.target;
        if (!(el instanceof HTMLElement) || !el.form || !el.name) return;

        if (el.matches('select[data-choice-theme]')) {
            applyTheme(el);
            return;
        }

        const field = FIELDS.find((f) => el.name.endsWith(`[${f}]`));
        if (field) {
            const select = input(el.form, prefixOf(el.name, field), 'theme');
            if (select) select.value = '';
        }
    });

    // New choice added to the collection: give it a theme (mint, sky, rose... like the home page)
    document.addEventListener('ea.collection.item-added', (event) => {
        const select = event.detail?.newElement?.querySelector('select[data-choice-theme]');
        if (!select || select.value) return;
        const count = event.detail.collection?.querySelectorAll('select[data-choice-theme]').length || 1;
        select.value = ROTATION[(count - 1) % ROTATION.length];
        applyTheme(select);
    });
})();
