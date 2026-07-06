export function pushEcommerceEvent(eventName, ecommercePayload = {}) {
    if (typeof window === 'undefined' || typeof eventName !== 'string' || eventName.trim() === '') {
        return;
    }

    if (!ecommercePayload || typeof ecommercePayload !== 'object' || Array.isArray(ecommercePayload)) {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({
        event: eventName,
        ecommerce: ecommercePayload,
    });
}
