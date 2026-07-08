import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['message'];

    async send(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const button = form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent.trim();
            button.textContent = 'Envoi...';
        }

        this.clearMessage();

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok || payload.ok === false) {
                throw new Error(payload.message || `Erreur HTTP ${response.status}`);
            }

            this.showMessage(payload.message || 'Commande envoyée à Sage.', 'success');

            window.setTimeout(() => {
                window.location.reload();
            }, 900);
        } catch (error) {
            this.showMessage(error.message || 'Impossible d’envoyer la commande à Sage.', 'error');

            if (button) {
                button.disabled = false;
                button.textContent = button.dataset.originalText || 'Envoyer à Sage';
            }
        }
    }

    clearMessage() {
        if (!this.hasMessageTarget) {
            return;
        }

        this.messageTarget.innerHTML = '';
    }

    showMessage(message, type) {
        if (!this.hasMessageTarget) {
            window.alert(message);

            return;
        }

        const icon = type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation';
        this.messageTarget.innerHTML = `
            <div class="admin-flash admin-flash--${type}">
                <i class="fa-solid ${icon}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
        `;
    }

    escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}
