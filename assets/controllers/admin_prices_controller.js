import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'tab'];

    static values = {
        categoryConfirm: String,
        errorText: String,
        savedText: String,
        savingText: String,
        token: String,
    };

    connect() {
        this.showMode('product');
    }

    switchMode(event) {
        this.showMode(event.params.mode);
    }

    submitOnEnter(event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        event.currentTarget.blur();
    }

    async saveProduct(event) {
        const input = event.currentTarget;
        const card = input.closest('[data-price-product-id]');

        if (!card || !this.hasChanged(card)) {
            return;
        }

        if (event.relatedTarget && card.contains(event.relatedTarget)) {
            return;
        }

        await this.save(
            card.dataset.priceUpdateUrl,
            this.payload(card),
            card,
            (payload) => this.updateProduct(payload.product),
            false,
        );
    }

    async saveCategory(event) {
        const card = event.currentTarget.closest('[data-price-category-id]');

        if (!card || event.currentTarget.disabled) {
            return;
        }

        const productCount = Number(card.dataset.priceProductCount || 0);
        const categoryName = card.dataset.priceCategoryName || '';
        const confirmation = this.categoryConfirmValue
            .replace('%category%', categoryName)
            .replace('%count%', String(productCount));

        if (!window.confirm(confirmation)) {
            return;
        }

        await this.save(
            card.dataset.priceUpdateUrl,
            this.payload(card),
            card,
            (payload) => payload.products.forEach((product) => this.updateProduct(product)),
            true,
        );
    }

    showMode(mode) {
        this.tabTargets.forEach((tab) => {
            const active = tab.dataset.adminPricesModeParam === mode;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        this.panelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.mode !== mode;
        });
    }

    async save(url, body, card, onSuccess, lockInputs) {
        const inputs = [...card.querySelectorAll('[data-price-field]')];
        const status = card.querySelector('[data-price-status]');

        if (lockInputs) {
            inputs.forEach((input) => {
                input.disabled = true;
            });
        }
        this.setStatus(status, this.savingTextValue, 'saving');

        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.tokenValue,
                },
                body: JSON.stringify(body),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || this.errorTextValue);
            }

            onSuccess(payload);
            card.classList.remove('has-error');
            inputs.forEach((input) => {
                input.dataset.savedValue = input.value;
            });
            this.setStatus(status, payload.message || this.savedTextValue, 'saved');
        } catch (error) {
            this.setStatus(status, error.message || this.errorTextValue, 'error');
            card.classList.add('has-error');
        } finally {
            if (lockInputs) {
                inputs.forEach((input) => {
                    input.disabled = false;
                });
            }
        }
    }

    payload(card) {
        return {
            priceTaxExcluded: card.querySelector('[data-price-field="priceTaxExcluded"]')?.value || '',
            taxRate: card.querySelector('[data-price-field="taxRate"]')?.value || '',
        };
    }

    hasChanged(card) {
        return [...card.querySelectorAll('[data-price-field]')]
            .some((input) => input.value !== input.dataset.savedValue);
    }

    updateProduct(product) {
        document.querySelectorAll(`[data-price-product-id="${product.id}"]`).forEach((element) => {
            const priceInput = element.querySelector('[data-price-field="priceTaxExcluded"]');
            const taxInput = element.querySelector('[data-price-field="taxRate"]');
            const priceTaxExcluded = element.querySelector('[data-price-tax-excluded]');
            const taxRate = element.querySelector('[data-price-tax-rate]');
            const taxIncluded = element.querySelector('[data-price-tax-included]');

            if (priceInput) {
                priceInput.value = product.priceTaxExcluded;
                priceInput.dataset.savedValue = product.priceTaxExcluded;
            }

            if (taxInput) {
                taxInput.value = product.taxRate;
                taxInput.dataset.savedValue = product.taxRate;
            }

            if (priceTaxExcluded) {
                priceTaxExcluded.textContent = `${product.priceTaxExcluded} € HT`;
            }

            if (taxRate) {
                taxRate.textContent = `${product.taxRate} %`;
            }

            if (taxIncluded) {
                taxIncluded.textContent = `${product.priceTaxIncluded} €`;
            }

            element.classList.remove('has-error');
        });
    }

    setStatus(element, message, tone) {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.dataset.tone = tone;
    }
}
