import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        csrf: String,
        error: String,
        toggleUrl: String,
    };

    async toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        const button = event.currentTarget;
        const productId = event.params.productId;

        if (!button || !productId) {
            this.showToast(this.errorValue, true);
            return;
        }

        const previousState = button.classList.contains('is-active');
        const nextState = !previousState;

        this.applyState(button, nextState, true);

        try {
            const response = await fetch(this.toggleUrlValue.replace('__PRODUCT_ID__', productId), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfValue,
                },
            });
            const payload = await response.json();

            if (!response.ok) {
                this.applyState(button, previousState, false);
                this.showToast(payload?.message || this.errorValue, true);
                return;
            }

            this.applyState(button, Boolean(payload.favorite), false);
            this.syncProductButtons(productId, Boolean(payload.favorite), button);
            this.showToast(payload?.message || this.errorValue, false);
        } catch (error) {
            this.applyState(button, previousState, false);
            this.showToast(this.errorValue, true);
        }
    }

    applyState(button, active, optimistic) {
        const icon = button.querySelector('i');

        button.classList.toggle('is-active', active);
        button.classList.toggle('is-pending', optimistic);
        button.classList.remove('is-bursting');
        button.setAttribute('aria-pressed', String(active));
        const label = active ? button.dataset.favoriteActiveLabel : button.dataset.favoriteInactiveLabel;
        if (label) {
            button.setAttribute('aria-label', label);
        }

        if (icon) {
            icon.classList.toggle('fa-solid', active);
            icon.classList.toggle('fa-regular', !active);
        }

        if (active) {
            requestAnimationFrame(() => {
                button.classList.add('is-bursting');
                window.setTimeout(() => button.classList.remove('is-bursting'), 620);
            });
        }
    }

    syncProductButtons(productId, active, sourceButton) {
        document
            .querySelectorAll(`[data-favorites-product-id-param="${CSS.escape(String(productId))}"]`)
            .forEach((button) => {
                if (button !== sourceButton) {
                    this.applyState(button, active, false);
                }
            });
    }

    showToast(message, error = false) {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-msg');

        if (!toast || !toastMessage) {
            return;
        }

        window.clearTimeout(this.toastTimer);
        toastMessage.textContent = message;
        toast.classList.toggle('is-error', error);
        toast.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');
        toast.classList.add('opacity-100', 'translate-y-0');

        this.toastTimer = window.setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
            toast.classList.remove('opacity-100', 'translate-y-0', 'is-error');
        }, 2600);
    }
}
