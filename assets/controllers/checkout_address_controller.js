import { Controller } from '@hotwired/stimulus';
import { pushEcommerceEvent } from '../lib/ecommerce_tracking.js';

export default class extends Controller {
    static targets = ['card', 'editor', 'editButton', 'cancelButton'];
    static values = {
        ecommerce: Object,
    };

    connect() {
        this.beginCheckoutTracked = false;
        this.showCard = this.hasCardTarget && !this.editorTarget.querySelector('.form-error-message, ul');
        this.render();
    }

    trackBeginCheckout() {
        if (this.beginCheckoutTracked || !this.hasEcommerceValue) {
            return;
        }

        if (!Array.isArray(this.ecommerceValue.items) || this.ecommerceValue.items.length === 0) {
            return;
        }

        pushEcommerceEvent('begin_checkout', this.ecommerceValue);
        this.beginCheckoutTracked = true;
    }

    edit() {
        this.showCard = false;
        this.render();

        requestAnimationFrame(() => {
            this.editorTarget.querySelector('input:not([type="hidden"])')?.focus();
        });
    }

    cancel() {
        this.element.reset();
        this.showCard = true;
        this.render();
    }

    render() {
        if (this.hasCardTarget) {
            this.cardTarget.hidden = !this.showCard;
        }

        this.editorTarget.hidden = this.showCard;

        if (this.hasEditButtonTarget) {
            this.editButtonTarget.setAttribute('aria-expanded', String(!this.showCard));
        }

        if (this.hasCancelButtonTarget) {
            this.cancelButtonTarget.hidden = !this.hasCardTarget;
        }
    }
}
