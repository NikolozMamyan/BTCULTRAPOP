import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        errorText: String,
        savedText: String,
        savingText: String,
        source: String,
        token: String,
    };

    submitOnEnter(event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        event.currentTarget.blur();
    }

    async save(event) {
        const input = event.currentTarget;
        const row = input.closest('[data-stock-product-id]');

        if (!row || input.value === input.dataset.savedValue) {
            return;
        }

        const status = row.querySelector('[data-stock-status]');
        input.disabled = true;
        row.classList.remove('has-error');
        this.setStatus(status, this.savingTextValue, 'saving');

        try {
            const response = await fetch(row.dataset.stockUpdateUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.tokenValue,
                },
                body: JSON.stringify({
                    quantity: input.value,
                    source: this.sourceValue,
                }),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || this.errorTextValue);
            }

            input.value = this.normalizeQuantity(payload.product.quantity);
            input.dataset.savedValue = input.value;
            this.setStatus(status, payload.message || this.savedTextValue, 'saved');
        } catch (error) {
            row.classList.add('has-error');
            this.setStatus(status, error.message || this.errorTextValue, 'error');
        } finally {
            input.disabled = false;
        }
    }

    setStatus(element, message, tone) {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.dataset.tone = tone;
    }

    normalizeQuantity(quantity) {
        if (quantity === null || quantity === undefined) {
            return '';
        }

        return String(quantity);
    }
}
