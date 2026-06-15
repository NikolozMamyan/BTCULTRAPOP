import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'quantity', 'tab'];

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

    get quantity() {
        return Number(this.quantityTarget.textContent);
    }
}
