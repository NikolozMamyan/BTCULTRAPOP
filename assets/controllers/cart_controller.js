import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        addUrl: String,
        csrf: String,
        error: String,
        url: String,
    };

    connect() {
        this.boundAddFromEvent = (event) => this.addFromEvent(event);
        window.addEventListener('cart:add', this.boundAddFromEvent);
        this.load();
    }

    disconnect() {
        window.removeEventListener('cart:add', this.boundAddFromEvent);
    }

    async load() {
        const response = await this.request('GET', this.urlValue);

        if (response?.cart) {
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

    async addProduct({ productId, quantity = 1, button = null }) {
        if (!productId) {
            this.showToast(this.errorValue, true);
            return;
        }

        await this.mutate('POST', this.addUrlValue, {
            productId: Number(productId),
            quantity: Number(quantity) || 1,
        }, button);
    }

    async updateItem(url, quantity, button = null) {
        await this.mutate('PATCH', url, { quantity }, button);
    }

    async mutate(method, url, body = null, button = null) {
        this.setBusy(button, true);

        try {
            const response = await this.request(method, url, body);

            if (response?.cart) {
                this.render(response.cart);
            }

            this.showToast(response?.message || this.errorValue, !response?.cart);
        } finally {
            this.setBusy(button, false);
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

    render(cart) {
        document.querySelectorAll('.cart-count').forEach((badge) => {
            badge.textContent = String(cart.totalQuantity);
            badge.classList.remove('badge-pulse');
            requestAnimationFrame(() => badge.classList.add('badge-pulse'));
        });

        const shippingBar = document.getElementById('ship-bar');
        if (shippingBar) {
            shippingBar.style.width = `${cart.shippingProgress}%`;
        }

        const shippingMessage = document.getElementById('ship-msg');
        if (shippingMessage) {
            shippingMessage.textContent = cart.shippingMessage;
        }

        const cartItems = document.getElementById('cart-items');
        if (cartItems) {
            cartItems.innerHTML = cart.empty ? this.emptyTemplate() : cart.items.map((item) => this.itemTemplate(item)).join('');
        }

        document.getElementById('cart-subtotal').textContent = cart.subtotalFormatted;
        document.getElementById('cart-total').textContent = cart.totalFormatted;
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

    itemTemplate(item) {
        return `
            <article class="cart-line">
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
