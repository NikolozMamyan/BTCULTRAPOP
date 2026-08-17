import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['mobileBar', 'panel', 'purchase', 'quantity', 'tab', 'thumbnail', 'visualImage'];
    static values = {
        backUrl: String,
        productId: Number,
    };

    connect() {
        this.observePurchaseActions();
    }

    disconnect() {
        this.purchaseObserver?.disconnect();
    }

    goBack(event) {
        if (window.history.length > 1) {
            event.preventDefault();
            window.history.back();

            return;
        }

        if (this.hasBackUrlValue) {
            event.currentTarget.href = this.backUrlValue;
        }
    }

    increment() {
        this.updateQuantity(Math.min(this.quantity + 1, 10));
    }

    decrement() {
        this.updateQuantity(Math.max(this.quantity - 1, 1));
    }

    selectTab(event) {
        const selectedPanel = event.params.panel;

        this.tabTargets.forEach((tab) => {
            tab.classList.toggle('is-active', tab === event.currentTarget);
        });

        this.panelTargets.forEach((panel) => {
            const active = panel.dataset.panel === selectedPanel;

            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });
    }

    selectImage(event) {
        const button = event.currentTarget;
        const image = event.params.image || '';
        const alt = event.params.alt || '';

        if (!image || !this.hasVisualImageTarget) {
            return;
        }

        this.visualImageTarget.src = image;
        this.visualImageTarget.alt = alt;

        this.thumbnailTargets.forEach((thumbnail) => {
            thumbnail.classList.toggle('is-active', thumbnail === button);
        });
    }

    addToCart(event) {
        window.dispatchEvent(new CustomEvent('cart:add', {
            detail: {
                productId: this.productIdValue,
                quantity: this.quantity,
                button: event.currentTarget,
            },
        }));
    }

    observePurchaseActions() {
        if (!this.hasMobileBarTarget || !this.hasPurchaseTarget) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            this.setMobileBarVisible(true);
            return;
        }

        this.purchaseObserver = new IntersectionObserver(([entry]) => {
            this.setMobileBarVisible(!entry.isIntersecting);
        }, {
            threshold: 0.25,
        });
        this.purchaseObserver.observe(this.purchaseTarget);
    }

    setMobileBarVisible(visible) {
        this.mobileBarTarget.classList.toggle('is-visible', visible);
        this.mobileBarTarget.toggleAttribute('inert', !visible);
        this.mobileBarTarget.setAttribute('aria-hidden', String(!visible));
    }

    updateQuantity(quantity) {
        this.quantityTargets.forEach((target) => {
            target.textContent = String(quantity);
        });
    }

    get quantity() {
        return Number(this.quantityTarget.textContent);
    }
}
