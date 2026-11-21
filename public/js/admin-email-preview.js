/*
 * Admin: live preview of the question email next to the question form.
 * The form values are POSTed (not saved) to the preview route and the returned HTML is shown in a sandboxed iframe.
 */
(function () {
    const DELAY = 400;

    function init() {
        const aside = document.querySelector('.vt-preview[data-preview-url]');
        const form = aside && aside.closest('.vt-edit-layout')?.querySelector('form');
        if (!aside || !form) return;

        const iframe = aside.querySelector('iframe');
        const subject = aside.querySelector('.vt-preview-subject');
        const subjectInput = form.querySelector('[name="Question[subject]"]');
        let timer = null;
        let controller = null;

        function resize() {
            try {
                const doc = iframe.contentDocument;
                if (doc && doc.documentElement) {
                    iframe.style.height = Math.max(200, doc.documentElement.scrollHeight) + 'px';
                }
            } catch (e) { /* cross-origin: keep the default height */ }
        }

        async function refresh() {
            if (controller) controller.abort();
            controller = new AbortController();
            const data = new FormData(form);
            // Not needed for the preview, and avoids sending the CSRF token around
            for (const key of [...data.keys()]) {
                if (!key.startsWith('Question[')) data.delete(key);
            }

            if (subject) {
                subject.textContent = (subjectInput?.value || '—')
                    .replace(/\{\{\s*(pr[ée]nom|firstname)\s*\}\}/gi, 'Camille')
                    .replace(/\{\{\s*(nom|lastname)\s*\}\}/gi, 'Martin');
            }

            aside.classList.add('is-loading');
            try {
                const response = await fetch(aside.dataset.previewUrl, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (response.ok) {
                    iframe.srcdoc = await response.text();
                }
            } catch (e) {
                if (e.name !== 'AbortError') console.error('Aperçu email :', e);
            } finally {
                aside.classList.remove('is-loading');
            }
        }

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(refresh, DELAY);
        }

        iframe.addEventListener('load', () => {
            resize();
            // Checking boxes in the preview can change the height (e.g. "Valider" button)
            iframe.contentDocument?.addEventListener('change', () => setTimeout(resize, 0));
        });

        ['input', 'change', 'trix-change'].forEach((type) => form.addEventListener(type, schedule));
        ['ea.collection.item-added', 'ea.collection.item-removed'].forEach((type) => document.addEventListener(type, schedule));

        aside.querySelectorAll('[data-preview-width]').forEach((button) => {
            button.addEventListener('click', () => {
                aside.querySelectorAll('[data-preview-width]').forEach((b) => {
                    b.classList.toggle('active', b === button);
                    b.setAttribute('aria-pressed', String(b === button));
                });
                iframe.style.width = button.dataset.previewWidth;
                setTimeout(resize, 250);
            });
        });

        refresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
