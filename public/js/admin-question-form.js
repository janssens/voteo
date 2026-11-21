/*
 * Admin question form: the "Envoyer les résultats à la clôture" checkbox
 * is only shown when a closing date is filled in.
 */
(function () {
    function init() {
        const field = document.querySelector('.js-send-results-on-close');
        const form = field?.closest('form');
        if (!field || !form) return;

        const dateInputs = () => [...form.querySelectorAll('[name^="Question[closesAt]"]')]
            .filter((el) => el.tagName !== 'BUTTON');
        const checkbox = field.querySelector('input[type="checkbox"]');

        function update() {
            const hasDate = dateInputs().some((el) => el.value.trim() !== '');
            field.hidden = !hasDate;
            field.style.display = hasDate ? '' : 'none';
            // No closing date: the option is meaningless, make sure it is not saved as checked
            if (!hasDate && checkbox) checkbox.checked = false;
        }

        form.addEventListener('input', update);
        form.addEventListener('change', update);
        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
