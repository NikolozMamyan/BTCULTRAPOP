import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'quantity', 'tab', 'thumbnail', 'visualImage'];
    static values = {
        productId: Number,
    };

    increment() {
        this.quantityTarget.textContent = Math.min(this.quantity + 1, 10);
    }

    decrement() {
        this.quantityTarget.textContent = Math.max(this.quantity - 1, 1);
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

    get quantity() {
        return Number(this.quantityTarget.textContent);
    }
}
