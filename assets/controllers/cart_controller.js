import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        addUrl: String,
        csrf: String,
        error: String,
        url: String,
    };

    connect() {
        this.stateVersion = 0;
        this.pendingMutations = new Map();
        this.mutationQueue = Promise.resolve();
        this.boundAddFromEvent = (event) => this.addFromEvent(event);
        window.addEventListener('cart:add', this.boundAddFromEvent);
        this.load();
    }

    disconnect() {
        window.removeEventListener('cart:add', this.boundAddFromEvent);
    }

    async load() {
        const versionAtStart = this.stateVersion;
        const response = await this.request('GET', this.urlValue);

        if (response?.cart && versionAtStart === this.stateVersion) {
            this.render(response.cart);
        }
    }

    async add(event) {
        await this.addProduct({
            productId: event.params.productId,
            quantity: event.params.quantity || 1,
            button: event.currentTarget,
        });
    }

    async addFromEvent(event) {
        await this.addProduct(event.detail || {});
    }

    async increment(event) {
        const quantity = Number(event.params.quantity || 0) + 1;
        await this.updateItem(event.params.url, quantity, event.currentTarget);
    }

    async decrement(event) {
        const quantity = Number(event.params.quantity || 0) - 1;
        await this.updateItem(event.params.url, quantity, event.currentTarget);
    }

    async remove(event) {
        await this.mutate('DELETE', event.params.url, null, event.currentTarget);
    }

    async applyPromo(event) {
        event.preventDefault();
        await this.submitPromo(event.currentTarget);
    }

    async removePromo(event) {
        event.preventDefault();
        await this.submitPromo(event.currentTarget);
    }

    async addProduct({ productId, quantity = 1, button = null }) {
        if (!productId) {
            this.showToast(this.errorValue, true);
            return;
        }

        this.animateAdd(button);

        await this.mutate('POST', this.addUrlValue, {
            productId: Number(productId),
            quantity: Number(quantity) || 1,
        }, button);
    }

    async updateItem(url, quantity, button = null) {
        await this.mutate('PATCH', url, { quantity }, button);
    }

    async mutate(method, url, body = null, button = null) {
        if (!url) {
            this.showToast(this.errorValue, true);
            return;
        }

        const mutationKey = `${method}:${url}`;

        if (this.pendingMutations.has(mutationKey)) {
            return;
        }

        ++this.stateVersion;
        this.pendingMutations.set(mutationKey, { method, url });
        this.setMutationBusy(url, true, button);

        if (method === 'DELETE') {
            this.setItemLinesHidden(url, true);
        }

        const operation = async () => {
            const response = await this.request(method, url, body);

            if (response?.cart) {
                this.render(response.cart);
                this.showToast(response.message || this.errorValue, false);
                return;
            }

            if (method === 'DELETE') {
                this.setItemLinesHidden(url, false);
            }
        };
        const queuedOperation = this.mutationQueue.then(operation, operation);
        this.mutationQueue = queuedOperation.catch(() => {});

        try {
            await queuedOperation;
        } finally {
            this.pendingMutations.delete(mutationKey);
            this.setMutationBusy(url, false, button);
        }
    }

    async request(method, url, body = null) {
        try {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfValue,
                },
                body: body === null ? null : JSON.stringify(body),
            });
            const payload = await response.json();

            if (!response.ok) {
                this.showToast(payload?.message || this.errorValue, true);
                return null;
            }

            return payload;
        } catch (error) {
            this.showToast(this.errorValue, true);
            return null;
        }
    }

    async submitPromo(form) {
        const button = form.querySelector('button[type="submit"]');

        if (!form.action || button?.disabled) {
            return;
        }

        this.setBusy(button, true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
                body: new FormData(form),
            });
            const payload = await response.json();

            if (payload?.cart) {
                this.render(payload.cart);
            }

            this.showToast(payload?.message || this.errorValue, !response.ok);
        } catch (error) {
            this.showToast(this.errorValue, true);
        } finally {
            this.setBusy(button, false);
        }
    }

    render(cart) {
        document.querySelectorAll('.cart-count').forEach((badge) => {
            badge.textContent = String(cart.totalQuantity);
            badge.classList.remove('badge-pulse');
            requestAnimationFrame(() => badge.classList.add('badge-pulse'));
        });

        this.renderShippingMeter('drawer', cart);
        this.renderShippingMeter('page', cart);

        const cartItems = document.getElementById('cart-items');
        if (cartItems) {
            cartItems.innerHTML = cart.empty ? this.emptyTemplate() : cart.items.map((item) => this.itemTemplate(item)).join('');
        }

        const cartPageItems = document.getElementById('cart-page-items');
        if (cartPageItems) {
            cartPageItems.innerHTML = cart.empty ? this.pageEmptyTemplate() : cart.items.map((item) => this.itemTemplate(item)).join('');
        }

        this.setText('cart-subtotal', cart.subtotalFormatted);
        this.setText('cart-total', cart.totalFormatted);
        this.setShippingText('cart-shipping', cart);
        this.setText('cart-page-subtotal', cart.subtotalFormatted);
        this.setText('cart-page-total', cart.totalFormatted);
        this.setShippingText('cart-page-shipping', cart);
        this.renderDiscount('cart-discount-row', 'cart-discount', cart);
        this.renderDiscount('cart-page-discount-row', 'cart-page-discount', cart);
        this.setText('cart-page-promo-code', cart.promoCode || '');
        this.setText('cart-page-active-discount', cart.discountFormatted);
        this.renderPromoControls(cart);
        this.syncPendingMutations();
    }

    renderDiscount(rowId, amountId, cart) {
        const row = document.getElementById(rowId);

        if (row) {
            row.classList.toggle('hidden', !cart.hasDiscount);
        }

        this.setText(amountId, cart.discountFormatted);
    }

    renderPromoControls(cart) {
        const container = document.getElementById('cart-promo');

        if (!container) {
            return;
        }

        if (cart.promoCode) {
            container.innerHTML = `
                <div class="cart-promo__active">
                    <span>
                        <i class="fa-solid fa-ticket"></i>
                        <strong>${this.escape(cart.promoCode)}</strong>
                        <small id="cart-page-active-discount">${this.escape(cart.discountFormatted)}</small>
                    </span>
                    <form action="${this.escapeAttribute(container.dataset.removeUrl)}" method="post" data-action="submit->cart#removePromo">
                        <input type="hidden" name="_csrf_token" value="${this.escapeAttribute(container.dataset.csrfToken)}">
                        <button type="submit" aria-label="${this.escapeAttribute(container.dataset.removeLabel)}">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </form>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <form action="${this.escapeAttribute(container.dataset.applyUrl)}" method="post" class="cart-promo__form" data-action="submit->cart#applyPromo">
                <input type="hidden" name="_csrf_token" value="${this.escapeAttribute(container.dataset.csrfToken)}">
                <label for="cart-promo-code">${this.escape(container.dataset.label)}</label>
                <div>
                    <input id="cart-promo-code" name="promo_code" type="text" required autocomplete="off" placeholder="${this.escapeAttribute(container.dataset.placeholder)}">
                    <button type="submit">${this.escape(container.dataset.applyLabel)}</button>
                </div>
            </form>
        `;
    }

    renderShippingMeter(prefix, cart) {
        this.setText(`${prefix}-ship-msg`, cart.shippingMessage);
        this.setShippingText(`${prefix}-ship-amount`, cart);

        const bar = document.getElementById(`${prefix}-ship-bar`);
        if (bar) {
            bar.style.width = `${cart.shippingProgress}%`;
        }

        const checkpoints = document.getElementById(`${prefix}-shipping-checkpoints`);
        if (checkpoints) {
            checkpoints.innerHTML = (cart.shippingCheckpoints || [])
                .map((checkpoint) => this.shippingCheckpointTemplate(checkpoint))
                .join('');
        }
    }

    shippingCheckpointTemplate(checkpoint) {
        const classes = [
            'shipping-meter__checkpoint',
            checkpoint.reached ? 'is-reached' : '',
            checkpoint.current ? 'is-current' : '',
            Number(checkpoint.shippingAmountCents) === 0 ? 'is-free-checkpoint' : '',
        ].filter(Boolean).join(' ');

        return `
            <span class="${classes}" style="--checkpoint-position: ${Number(checkpoint.position) || 0}%">
                <i></i>
                <small>${this.escape(Number(checkpoint.thresholdCents) === 0 ? '0 €' : checkpoint.thresholdFormatted)}</small>
            </span>
        `;
    }

    setShippingText(id, cart) {
        const element = document.getElementById(id);

        if (!element) {
            return;
        }

        element.textContent = cart.shippingDisplay;
        element.classList.toggle('is-free', Boolean(cart.shippingFree));
    }

    emptyTemplate() {
        return `
            <div class="text-center text-text-light py-16">
                <i class="fa-solid fa-bag-shopping text-4xl opacity-20 mb-4"></i>
                <p class="font-bold text-text-dark mb-1">${this.escape(this.element.dataset.cartEmptyTitle)}</p>
                <p class="text-sm">${this.escape(this.element.dataset.cartEmptyText)}</p>
            </div>
        `;
    }

    pageEmptyTemplate() {
        return `
            <div class="catalog-empty">
                <div class="catalog-empty__visual" aria-hidden="true">
                    <span class="catalog-empty__orb catalog-empty__orb--yellow"></span>
                    <span class="catalog-empty__orb catalog-empty__orb--red"></span>
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <span class="catalog-empty__eyebrow">${this.escape(this.element.dataset.cartEmptyEyebrow)}</span>
                <h2>${this.escape(this.element.dataset.cartEmptyTitle)}</h2>
                <p>${this.escape(this.element.dataset.cartEmptyText)}</p>
                <a href="${this.escapeAttribute(this.element.dataset.shopUrl)}" class="catalog-empty__action">
                    ${this.escape(this.element.dataset.cartEmptyAction)}
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        `;
    }

    itemTemplate(item) {
        return `
            <article class="cart-line" data-cart-item-id="${Number(item.id)}" data-cart-remove-url="${this.escapeAttribute(item.removeUrl)}">
                <a class="cart-line__media" href="${this.escapeAttribute(item.productUrl || '#')}">
                    ${item.image ? `<img src="${this.escapeAttribute(item.image)}" alt="">` : '<i class="fa-solid fa-box"></i>'}
                </a>
                <div class="cart-line__content">
                    <div class="cart-line__top">
                        <a href="${this.escapeAttribute(item.productUrl || '#')}">${this.escape(item.name)}</a>
                        <button type="button" class="cart-line__remove" data-action="cart#remove" data-cart-url-param="${this.escapeAttribute(item.removeUrl)}" aria-label="Remove">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <span>${this.escape(item.unitPriceFormatted)}</span>
                    <div class="cart-line__bottom">
                        <div class="cart-line__quantity">
                            <button type="button" data-action="cart#decrement" data-cart-url-param="${this.escapeAttribute(item.updateUrl)}" data-cart-quantity-param="${item.quantity}" aria-label="-">
                                <i class="fa-solid fa-minus"></i>
                            </button>
                            <strong>${item.quantity}</strong>
                            <button type="button" data-action="cart#increment" data-cart-url-param="${this.escapeAttribute(item.updateUrl)}" data-cart-quantity-param="${item.quantity}" aria-label="+">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <strong>${this.escape(item.totalFormatted)}</strong>
                    </div>
                </div>
            </article>
        `;
    }

    showToast(message, error = false) {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-msg');

        if (!toast || !toastMessage) {
            return;
        }

        window.clearTimeout(this.toastTimer);
        toastMessage.textContent = message;
        toast.classList.toggle('is-error', error);
        toast.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');
        toast.classList.add('opacity-100', 'translate-y-0');

        this.toastTimer = window.setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
            toast.classList.remove('opacity-100', 'translate-y-0', 'is-error');
        }, 2600);
    }

    setBusy(button, busy) {
        if (!button) {
            return;
        }

        button.disabled = busy;
        button.classList.toggle('is-loading', busy);
        button.setAttribute('aria-busy', String(busy));
    }

    setMutationBusy(url, busy, fallbackButton = null) {
        let matched = false;

        document.querySelectorAll('[data-cart-url-param]').forEach((button) => {
            if (button.dataset.cartUrlParam !== url) {
                return;
            }

            matched = true;
            this.setBusy(button, busy);
        });

        if (!matched) {
            this.setBusy(fallbackButton, busy);
        }
    }

    setItemLinesHidden(removeUrl, hidden) {
        document.querySelectorAll('.cart-line[data-cart-remove-url]').forEach((line) => {
            if (line.dataset.cartRemoveUrl !== removeUrl) {
                return;
            }

            line.hidden = hidden;
            line.classList.toggle('is-removing', hidden);
            line.setAttribute('aria-hidden', String(hidden));
        });
    }

    syncPendingMutations() {
        this.pendingMutations.forEach(({ method, url }) => {
            this.setMutationBusy(url, true);

            if (method === 'DELETE') {
                this.setItemLinesHidden(url, true);
            }
        });
    }

    animateAdd(button) {
        if (!button) {
            return;
        }

        const targets = new Set([
            button,
            button.closest('.shop-product-card'),
        ].filter(Boolean));

        targets.forEach((target) => target.classList.remove('is-cart-bursting'));
        requestAnimationFrame(() => {
            targets.forEach((target) => target.classList.add('is-cart-bursting'));
            window.setTimeout(() => {
                targets.forEach((target) => target.classList.remove('is-cart-bursting'));
            }, 720);
        });
    }

    setText(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.textContent = value;
        }
    }

    escape(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    escapeAttribute(value) {
        return this.escape(value).replaceAll('`', '&#096;');
    }
}
