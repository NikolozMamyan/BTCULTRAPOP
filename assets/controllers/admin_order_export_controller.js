import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['order', 'selectedCount', 'selectAll', 'submit'];

    connect() {
        this.updateCount();
    }

    toggleAll(event) {
        this.orderTargets.forEach((checkbox) => {
            checkbox.checked = event.currentTarget.checked;
        });
        this.updateCount();
    }

    updateCount() {
        const selected = this.orderTargets.filter((checkbox) => checkbox.checked).length;

        if (this.hasSelectedCountTarget) {
            this.selectedCountTarget.textContent = String(selected);
        }

        if (this.hasSelectAllTarget) {
            this.selectAllTarget.checked = selected > 0 && selected === this.orderTargets.length;
            this.selectAllTarget.indeterminate = selected > 0 && selected < this.orderTargets.length;
        }

        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = selected === 0;
        }
    }
}
