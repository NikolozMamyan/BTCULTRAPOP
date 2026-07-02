import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.storageKey = `${window.location.pathname}:admin-web-components-state`;
        this.handleSubmit = this.handleSubmit.bind(this);
        this.element.addEventListener('submit', this.handleSubmit, true);

        this.restoreState();
    }

    disconnect() {
        this.element.removeEventListener('submit', this.handleSubmit, true);
    }

    submitOnFileSelect(event) {
        const input = event.currentTarget;

        if (!input.files || input.files.length === 0 || !input.form) {
            return;
        }

        input.form.requestSubmit();
    }

    async handleSubmit(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        event.preventDefault();
        this.persistState();

        try {
            await this.submitForm(form);
        } catch {
            form.classList.remove('is-submitting');
            this.toggleFormControls(form, false);
            form.submit();
        }
    }

    async submitForm(form) {
        const payload = new FormData(form);

        form.classList.add('is-submitting');
        this.toggleFormControls(form, true);

        const response = await fetch(form.action, {
            method: form.method || 'POST',
            body: payload,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Web component form failed with status ${response.status}`);
        }

        const html = await response.text();
        const nextDocument = new DOMParser().parseFromString(html, 'text/html');
        const nextComponents = nextDocument.querySelector('[data-controller~="admin-web-components"]');

        if (!nextComponents) {
            window.location.href = response.url || window.location.href;

            return;
        }

        this.replaceFlashes(nextDocument);
        this.element.replaceWith(nextComponents);
    }

    toggleFormControls(form, disabled) {
        form.querySelectorAll('button, input:not([type="hidden"]), select, textarea').forEach((control) => {
            control.disabled = disabled;
        });
    }

    replaceFlashes(nextDocument) {
        const currentFlashes = document.querySelector('.admin-flashes');
        const nextFlashes = nextDocument.querySelector('.admin-flashes');

        if (currentFlashes && nextFlashes) {
            currentFlashes.replaceWith(nextFlashes);

            return;
        }

        if (currentFlashes && !nextFlashes) {
            currentFlashes.remove();

            return;
        }

        if (!currentFlashes && nextFlashes) {
            this.element.before(nextFlashes);
        }
    }

    persistState() {
        if (!this.storageKey) {
            return;
        }

        try {
            const detailsOpen = [...this.element.querySelectorAll('details')]
                .map((details) => details.open);

            window.sessionStorage.setItem(
                this.storageKey,
                JSON.stringify({
                    detailsOpen,
                    scrollY: window.scrollY,
                    timestamp: Date.now(),
                }),
            );
        } catch {
            // If sessionStorage is unavailable, the form still works normally through AJAX/fallback.
        }
    }

    restoreState() {
        if (!this.storageKey) {
            return;
        }

        let rawState = null;

        try {
            rawState = window.sessionStorage.getItem(this.storageKey);
        } catch {
            return;
        }

        if (!rawState) {
            return;
        }

        try {
            window.sessionStorage.removeItem(this.storageKey);
            const state = JSON.parse(rawState);

            if (!state || Date.now() - Number(state.timestamp || 0) > 30000) {
                return;
            }

            if (Array.isArray(state.detailsOpen)) {
                [...this.element.querySelectorAll('details')].forEach((details, index) => {
                    if (typeof state.detailsOpen[index] === 'boolean') {
                        details.open = state.detailsOpen[index];
                    }
                });
            }

            this.restoreScroll(Number(state.scrollY || 0));
        } catch {
            window.sessionStorage.removeItem(this.storageKey);
        }
    }

    restoreScroll(scrollY) {
        if (scrollY <= 0) {
            return;
        }

        const restore = () => window.scrollTo({
            top: scrollY,
            behavior: 'auto',
        });

        requestAnimationFrame(() => {
            restore();
            window.setTimeout(restore, 80);
            window.setTimeout(restore, 260);
        });
    }
}
