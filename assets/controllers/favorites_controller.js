import { Controller } from '@hotwired/stimulus';
import { showStorefrontToast } from '../lib/storefront_toast.js';

export default class extends Controller {
    static targets = ['count', 'empty'];

    static values = {
        csrf: String,
        error: String,
        loginLabel: String,
        loginUrl: String,
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
                const loginUrl = response.status === 401 ? this.loginUrl() : '';

                this.showToast(payload?.message || this.errorValue, true, loginUrl ? {
                    label: this.loginLabelValue,
                    url: loginUrl,
                } : null);
                return;
            }

            const favorite = Boolean(payload.favorite);

            this.applyState(button, favorite, false);
            this.syncProductButtons(productId, favorite, button);
            this.updateCount(Number(payload.count) || 0);

            if (!favorite) {
                this.removeWishlistCard(button);
            }

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

    updateCount(count) {
        this.countTargets.forEach((badge) => {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count === 0;
        });
    }

    removeWishlistCard(button) {
        if (this.element.dataset.page !== 'wishlist') {
            return;
        }

        const card = button.closest('.shop-product-card');

        if (!card) {
            return;
        }

        card.classList.add('is-removing');
        window.setTimeout(() => {
            card.remove();

            if (this.hasEmptyTarget && this.element.querySelectorAll('.shop-product-card').length === 0) {
                this.emptyTarget.hidden = false;
            }
        }, 260);
    }

    loginUrl() {
        if (!this.hasLoginUrlValue) {
            return '';
        }

        const url = new URL(this.loginUrlValue, window.location.origin);
        url.searchParams.set(
            'return_to',
            `${window.location.pathname}${window.location.search}${window.location.hash}`,
        );

        return `${url.pathname}${url.search}${url.hash}`;
    }

    showToast(message, error = false, action = null) {
        showStorefrontToast(message, {
            error,
            actionLabel: action?.label || '',
            actionUrl: action?.url || '',
        });
    }
}
