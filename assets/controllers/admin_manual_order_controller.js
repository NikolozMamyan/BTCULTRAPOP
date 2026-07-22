import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'items',
        'row',
        'total',
        'firstName',
        'lastName',
        'email',
        'phone',
        'street',
        'postalCode',
        'city',
        'countryCode',
    ];

    connect() {
        this.renumber();
        this.refreshTotal();
    }

    add() {
        const index = Number(this.itemsTarget.dataset.index || this.rowTargets.length);
        const html = this.itemsTarget.dataset.prototype.replaceAll('__item__', String(index));

        this.itemsTarget.insertAdjacentHTML('beforeend', html);
        this.itemsTarget.dataset.index = String(index + 1);
        this.renumber();
        this.refreshTotal();
        this.rowTargets.at(-1)?.querySelector('select')?.focus();
    }

    remove(event) {
        if (this.rowTargets.length <= 1) {
            return;
        }

        event.currentTarget.closest('[data-admin-manual-order-target="row"]')?.remove();
        this.renumber();
        this.refreshTotal();
    }

    fillCustomer(event) {
        const option = event.currentTarget.selectedOptions?.[0];

        if (!option?.value) {
            return;
        }

        const fields = {
            firstName: 'firstName',
            lastName: 'lastName',
            email: 'email',
            phone: 'phone',
            street: 'street',
            postalCode: 'postalCode',
            city: 'city',
            countryCode: 'countryCode',
        };

        Object.entries(fields).forEach(([targetName, dataName]) => {
            const target = this[`${targetName}Target`];
            const value = option.dataset[dataName];

            if (target && value !== undefined) {
                target.value = value;
            }
        });
    }

    refreshTotal() {
        const total = this.rowTargets.reduce((sum, row) => {
            const product = row.querySelector('select');
            const quantity = Math.max(1, Number(row.querySelector('input[type="number"]')?.value) || 1);
            const manualPriceInput = row.querySelector('[data-manual-price]');
            const manualPrice = this.parseAmount(manualPriceInput?.value);
            const catalogPrice = this.parseAmount(product?.selectedOptions?.[0]?.dataset.price);
            const price = manualPriceInput?.value.trim() ? manualPrice : catalogPrice;

            return sum + (price * quantity);
        }, 0);

        if (this.hasTotalTarget) {
            this.totalTarget.textContent = new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR',
            }).format(total);
        }
    }

    renumber() {
        this.rowTargets.forEach((row, index) => {
            const number = row.querySelector('[data-order-item-number]');
            const remove = row.querySelector('[data-action="admin-manual-order#remove"]');

            if (number) {
                number.textContent = String(index + 1);
            }

            if (remove) {
                remove.disabled = this.rowTargets.length <= 1;
            }
        });
    }

    parseAmount(value) {
        const amount = Number.parseFloat(String(value || '').replace(',', '.'));

        return Number.isFinite(amount) ? Math.max(0, amount) : 0;
    }
}
