import { Controller } from '@hotwired/stimulus';

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
            this.showToast(message, !success);

            if (success) {
                this.emailTarget.value = '';
            }
        } catch (error) {
            this.showMessage(this.errorValue, false);
            this.showToast(this.errorValue, true);
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

    showToast(message, error) {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-msg');
        const toastIcon = toast?.querySelector('i');

        if (!toast || !toastMessage) {
            return;
        }

        window.clearTimeout(window.newsletterToastTimer);
        toastMessage.textContent = message;
        toast.classList.toggle('is-error', error);
        toastIcon?.classList.toggle('fa-circle-check', !error);
        toastIcon?.classList.toggle('fa-circle-exclamation', error);
        toast.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');
        toast.classList.add('opacity-100', 'translate-y-0');

        window.newsletterToastTimer = window.setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
            toast.classList.remove('opacity-100', 'translate-y-0', 'is-error');
            toastIcon?.classList.add('fa-circle-check');
            toastIcon?.classList.remove('fa-circle-exclamation');
        }, 3600);
    }
}
