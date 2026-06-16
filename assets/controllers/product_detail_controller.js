import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'quantity', 'tab'];
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

    addToCart() {
        window.dispatchEvent(new CustomEvent('cart:add', {
            detail: {
                productId: this.productIdValue,
                quantity: this.quantity,
            },
        }));
    }

    get quantity() {
        return Number(this.quantityTarget.textContent);
    }
}
