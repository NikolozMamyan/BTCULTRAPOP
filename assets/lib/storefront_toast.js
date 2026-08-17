let hideTimer;

export function hideStorefrontToast() {
    const toast = document.getElementById('toast');

    window.clearTimeout(hideTimer);

    if (!toast) {
        return;
    }

    toast.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
    toast.classList.remove('opacity-100', 'translate-y-0', 'is-error');
}

export function showStorefrontToast(message, {
    error = false,
    actionLabel = '',
    actionUrl = '',
    duration = 0,
} = {}) {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-msg');
    const toastAction = document.getElementById('toast-action');
    const toastIcon = toast?.querySelector('[data-toast-icon]');

    if (!toast || !toastMessage || !toastAction) {
        return;
    }

    window.clearTimeout(hideTimer);
    toastMessage.textContent = message;
    toast.classList.toggle('is-error', error);
    toast.setAttribute('role', error ? 'alert' : 'status');
    toastIcon?.classList.toggle('fa-circle-check', !error);
    toastIcon?.classList.toggle('fa-circle-exclamation', error);

    if (actionLabel && actionUrl) {
        toastAction.textContent = actionLabel;
        toastAction.href = actionUrl;
        toastAction.hidden = false;
    } else {
        toastAction.hidden = true;
        toastAction.removeAttribute('href');
        toastAction.textContent = '';
    }

    toast.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');
    toast.classList.add('opacity-100', 'translate-y-0');

    hideTimer = window.setTimeout(hideStorefrontToast, duration || (actionLabel ? 5200 : 3200));
}

document.addEventListener('turbo:before-cache', hideStorefrontToast);
