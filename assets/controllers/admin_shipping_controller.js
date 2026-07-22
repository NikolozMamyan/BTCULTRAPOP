import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tiers', 'row'];

    connect() {
        this.renumber();
    }

    add() {
        const index = Number(this.tiersTarget.dataset.index || this.rowTargets.length);
        const html = this.tiersTarget.dataset.prototype.replaceAll('__tier__', String(index));

        this.tiersTarget.insertAdjacentHTML('beforeend', html);
        this.tiersTarget.dataset.index = String(index + 1);
        this.renumber();
        this.rowTargets.at(-1)?.querySelector('input')?.focus();
    }

    remove(event) {
        if (this.rowTargets.length <= 2) {
            return;
        }

        event.currentTarget.closest('[data-admin-shipping-target="row"]')?.remove();
        this.renumber();
    }

    renumber() {
        this.rowTargets.forEach((row, index) => {
            const number = row.querySelector('[data-tier-number]');

            if (number) {
                number.textContent = String(index + 1);
            }

            const remove = row.querySelector('[data-action="admin-shipping#remove"]');

            if (remove) {
                remove.disabled = this.rowTargets.length <= 2;
            }
        });
    }
}
