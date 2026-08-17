import { Controller } from '@hotwired/stimulus';
import { showStorefrontToast } from '../lib/storefront_toast.js';

export default class extends Controller {
    static targets = ['button', 'email', 'form', 'message', 'messageIcon', 'messageText'];

    static values = {
        error: String,
    };

    connect() {
        this.originalButtonContent = this.buttonTarget.innerHTML;
    }

    disconnect() {
        window.clearTimeout(this.messageTimer);
        window.clearTimeout(this.messageHiddenTimer);
    }

    async submit(event) {
        event.preventDefault();

        if (this.buttonTarget.disabled || !this.formTarget.reportValidity()) {
            return;
        }

        this.setBusy(true);
        this.hideMessage();

        try {
            const response = await fetch(this.formTarget.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(this.formTarget),
            });
            const payload = await response.json();
            const success = response.ok && payload?.success === true;
            const message = payload?.message || this.errorValue;

            this.showMessage(message, success);
            showStorefrontToast(message, { error: !success });

            if (success) {
                this.emailTarget.value = '';
            }
        } catch (error) {
            this.showMessage(this.errorValue, false);
            showStorefrontToast(this.errorValue, { error: true });
        } finally {
            this.setBusy(false);
        }
    }

    setBusy(busy) {
        this.buttonTarget.disabled = busy;
        this.buttonTarget.setAttribute('aria-busy', String(busy));
        this.formTarget.classList.toggle('is-loading', busy);
        this.buttonTarget.innerHTML = busy
            ? '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>'
            : this.originalButtonContent;
    }

    showMessage(message, success) {
        window.clearTimeout(this.messageTimer);
        window.clearTimeout(this.messageHiddenTimer);
        this.messageTarget.hidden = false;
        this.messageTarget.classList.toggle('is-error', !success);
        this.messageTarget.setAttribute('role', success ? 'status' : 'alert');
        this.messageIconTarget.className = success
            ? 'fa-solid fa-circle-check'
            : 'fa-solid fa-circle-exclamation';
        this.messageTextTarget.textContent = message;

        window.requestAnimationFrame(() => {
            this.messageTarget.classList.add('is-visible');
        });

        this.messageTimer = window.setTimeout(() => {
            this.hideMessage();
        }, 6500);
    }

    hideMessage() {
        this.messageTarget.classList.remove('is-visible', 'is-error');
        window.clearTimeout(this.messageHiddenTimer);
        this.messageHiddenTimer = window.setTimeout(() => {
            this.messageTarget.hidden = true;
        }, 350);
    }

}
