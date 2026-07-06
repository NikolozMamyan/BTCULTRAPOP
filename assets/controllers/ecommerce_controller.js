import { Controller } from '@hotwired/stimulus';
import { pushEcommerceEvent } from '../lib/ecommerce_tracking.js';

const ALLOWED_EVENTS = new Set([
    'view_item',
    'view_cart',
    'purchase',
]);

export default class extends Controller {
    static values = {
        event: String,
        ecommerce: Object,
    };

    connect() {
        if (!this.hasEventValue || !this.hasEcommerceValue || !ALLOWED_EVENTS.has(this.eventValue)) {
            return;
        }

        pushEcommerceEvent(this.eventValue, this.ecommerceValue);
    }
}
