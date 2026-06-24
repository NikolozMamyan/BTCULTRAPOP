let disposeCurrentPage = () => {};
let pageSkeletonVisibleAt = 0;
let pageSkeletonTimer;

function showPageSkeleton() {
    const skeleton = document.getElementById('page-transition-skeleton');

    if (!skeleton) {
        return;
    }

    window.clearTimeout(pageSkeletonTimer);
    pageSkeletonVisibleAt = Date.now();
    skeleton.setAttribute('aria-hidden', 'false');
    skeleton.classList.add('is-visible');
    document.body.classList.add('is-page-loading');
}

function hidePageSkeleton(force = false) {
    const skeleton = document.getElementById('page-transition-skeleton');

    if (!skeleton) {
        return;
    }

    const elapsed = Date.now() - pageSkeletonVisibleAt;
    const delay = force ? 0 : Math.max(0, 320 - elapsed);

    window.clearTimeout(pageSkeletonTimer);
    pageSkeletonTimer = window.setTimeout(() => {
        skeleton.classList.remove('is-visible');
        skeleton.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('is-page-loading');
    }, delay);
}

function initializeStorefront() {
    const app = document.getElementById('storefront-app');
    if (!app) {
        return () => {};
    }

    const abortController = new AbortController();
    const { signal } = abortController;
    let carouselTimer;
    let toastTimer;
    let slideIndex = 0;

    const on = (target, eventName, listener, options = {}) => {
        target?.addEventListener(eventName, listener, { ...options, signal });
    };

    const setBodyLocked = (locked) => {
        document.body.classList.toggle('overflow-hidden', locked);
    };

    const closeSearch = () => {
        const modal = document.getElementById('search-modal');
        if (!modal) {
            return;
        }

        modal.classList.remove('open');
        window.setTimeout(() => modal.classList.add('hidden'), 300);
        setBodyLocked(false);
    };

    const closeProductPreview = () => {
        const preview = document.getElementById('product-preview');

        if (!preview) {
            return;
        }

        preview.classList.remove('open');
        preview.setAttribute('aria-hidden', 'true');
        window.setTimeout(() => preview.classList.add('hidden'), 260);
        setBodyLocked(false);
    };

    const openProductPreview = (event) => {
        event.preventDefault();
        event.stopPropagation();

        const button = event.currentTarget;
        const preview = document.getElementById('product-preview');
        const image = document.getElementById('product-preview-image');
        const title = document.getElementById('product-preview-title');
        const link = document.getElementById('product-preview-link');

        if (!preview || !image || !title || !link) {
            return;
        }

        image.src = button.dataset.productPreviewImage || '';
        image.alt = button.dataset.productPreviewTitle || '';
        title.textContent = button.dataset.productPreviewTitle || '';
        link.href = button.dataset.productPreviewUrl || '#';
        preview.classList.remove('hidden');
        requestAnimationFrame(() => {
            preview.classList.add('open');
            preview.setAttribute('aria-hidden', 'false');
        });
        setBodyLocked(true);
    };

    const openSearch = () => {
        const modal = document.getElementById('search-modal');
        if (!modal) {
            return;
        }

        modal.classList.remove('hidden');
        requestAnimationFrame(() => {
            modal.classList.add('open');
            document.getElementById('search-input')?.focus();
        });
        setBodyLocked(true);
    };

    const closeCart = () => {
        document.getElementById('cart-overlay')?.classList.add('hidden');
        document.getElementById('cart-drawer')?.classList.remove('open');
        setBodyLocked(false);
    };

    const openCart = () => {
        document.getElementById('cart-overlay')?.classList.remove('hidden');
        document.getElementById('cart-drawer')?.classList.add('open');
        setBodyLocked(true);
    };

    const visitCard = (card) => {
        const url = card.dataset.productUrl;

        if (!url) {
            return;
        }

        if (window.Turbo) {
            window.Turbo.visit(url);
            return;
        }

        window.location.href = url;
    };

    const shouldIgnoreCardNavigation = (event) => event.defaultPrevented
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey
        || event.target.closest('a, button, input, select, textarea, label');

    const resetCart = () => {
        document.querySelectorAll('.cart-count').forEach((badge) => {
            badge.textContent = '0';
        });

        const shippingBar = document.getElementById('drawer-ship-bar');
        if (shippingBar) {
            shippingBar.style.width = '0%';
        }

        const shippingMessage = document.getElementById('drawer-ship-msg');
        if (shippingMessage) {
            shippingMessage.textContent = app.dataset.cartShippingEmpty;
        }

        const shippingAmount = document.getElementById('drawer-ship-amount');
        if (shippingAmount) {
            shippingAmount.textContent = '—';
            shippingAmount.classList.remove('is-free');
        }

        const shippingCheckpoints = document.getElementById('drawer-shipping-checkpoints');
        if (shippingCheckpoints) {
            shippingCheckpoints.innerHTML = '';
        }

        const cartItems = document.getElementById('cart-items');
        if (cartItems) {
            cartItems.innerHTML = `
                <div class="text-center text-text-light py-16">
                    <i class="fa-solid fa-bag-shopping text-4xl opacity-20 mb-4"></i>
                    <p class="font-bold text-text-dark mb-1">${app.dataset.cartEmptyTitle}</p>
                    <p class="text-sm">${app.dataset.cartEmptyText}</p>
                </div>
            `;
        }

        document.getElementById('cart-subtotal').textContent = '0,00€';
        document.getElementById('cart-shipping').textContent = '—';
        document.getElementById('cart-total').textContent = '0,00€';
    };

    const renderCarouselDots = () => {
        const container = document.getElementById('carousel-dots');
        if (!container) {
            return;
        }

        container.replaceChildren(...[0, 1].map((index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `h-2.5 rounded-full transition-all ${index === slideIndex ? 'bg-white w-7' : 'bg-white/50 w-2.5'}`;
            button.ariaLabel = app.dataset.carouselGoTo.replace('%number%', index + 1);
            on(button, 'click', () => goToSlide(index));

            return button;
        }));
    };

    const resetCarouselTimer = () => {
        window.clearInterval(carouselTimer);
        if (document.getElementById('carousel-track')) {
            carouselTimer = window.setInterval(() => goToSlide(slideIndex + 1), 5500);
        }
    };

    const goToSlide = (index) => {
        const track = document.getElementById('carousel-track');
        if (!track) {
            return;
        }

        slideIndex = (index + 2) % 2;
        track.style.transform = `translateX(-${slideIndex * 100}%)`;
        renderCarouselDots();
        resetCarouselTimer();
    };

    const triggerReveals = () => {
        document.querySelectorAll('.reveal').forEach((element) => {
            if (element.getBoundingClientRect().top < window.innerHeight - 60) {
                element.classList.add('in');
            }
        });
    };

    const updateBackToTop = () => {
        const button = document.getElementById('back-top');
        if (!button) {
            return;
        }

        const visible = window.scrollY > 500;
        button.classList.toggle('opacity-0', !visible);
        button.classList.toggle('pointer-events-none', !visible);
    };

    const bindFallbacks = () => {
        document.querySelectorAll('[data-logo-fallback]').forEach((image) => {
            on(image, 'error', () => {
                const logo = document.createElement('span');
                const white = image.dataset.logoFallback === 'white';
                logo.className = 'font-display font-extrabold text-2xl tracking-tight';
                logo.style.color = white ? '#fff' : '#e82118';
                logo.innerHTML = `ULTRA<span style="color:${white ? '#ffcc07' : '#203263'}">POP</span>`;
                image.replaceWith(logo);
            }, { once: true });
        });

        document.querySelectorAll('[data-banner-fallback]').forEach((image) => {
            on(image, 'error', () => {
                const fallback = document.createElement('div');
                fallback.className = 'home-hero__media-fallback w-full h-[300px] md:h-[480px] flex items-center justify-center';
                fallback.style.background = `linear-gradient(135deg,${image.dataset.fallbackColor},#203263)`;
                fallback.innerHTML = `<span class="font-display text-white text-4xl font-extrabold opacity-90">${image.dataset.bannerFallback}</span>`;
                image.replaceWith(fallback);
            }, { once: true });
        });

        document.querySelectorAll('[data-hide-on-error]').forEach((image) => {
            on(image, 'error', () => image.remove(), { once: true });
        });
    };

    document.querySelectorAll('[data-action="search-open"]').forEach((button) => on(button, 'click', openSearch));
    document.querySelectorAll('[data-action="search-close"]').forEach((button) => on(button, 'click', closeSearch));
    document.querySelectorAll('[data-action="cart-open"]').forEach((button) => on(button, 'click', openCart));
    document.querySelectorAll('[data-action="cart-close"]').forEach((button) => on(button, 'click', closeCart));
    document.querySelectorAll('[data-action="product-preview-open"]').forEach((button) => on(button, 'click', openProductPreview));
    document.querySelectorAll('[data-action="product-preview-close"]').forEach((button) => on(button, 'click', closeProductPreview));
    document.querySelectorAll('.shop-product-card[data-product-url]').forEach((card) => {
        on(card, 'click', (event) => {
            if (!shouldIgnoreCardNavigation(event)) {
                visitCard(card);
            }
        });
        on(card, 'keydown', (event) => {
            if ((event.key === 'Enter' || event.key === ' ') && !shouldIgnoreCardNavigation(event)) {
                event.preventDefault();
                visitCard(card);
            }
        });
    });
    document.querySelectorAll('[data-action="carousel-prev"]').forEach((button) => on(button, 'click', () => goToSlide(slideIndex - 1)));
    document.querySelectorAll('[data-action="carousel-next"]').forEach((button) => on(button, 'click', () => goToSlide(slideIndex + 1)));
    document.querySelectorAll('[data-action="back-to-top"]').forEach((button) => on(button, 'click', () => window.scrollTo({ top: 0, behavior: 'smooth' })));
    on(document, 'keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            openSearch();
        }

        if (event.key === 'Escape') {
            closeSearch();
            closeCart();
            closeProductPreview();
        }
    });

    on(window, 'scroll', () => {
        triggerReveals();
        updateBackToTop();
    }, { passive: true });

    bindFallbacks();
    renderCarouselDots();
    resetCarouselTimer();
    triggerReveals();
    updateBackToTop();

    return () => {
        abortController.abort();
        window.clearInterval(carouselTimer);
        window.clearTimeout(toastTimer);
        setBodyLocked(false);
        document.getElementById('search-modal')?.classList.add('hidden');
        document.getElementById('product-preview')?.classList.add('hidden');
        document.getElementById('product-preview')?.classList.remove('open');
        document.getElementById('cart-overlay')?.classList.add('hidden');
        document.getElementById('cart-drawer')?.classList.remove('open');
    };
}

function bootStorefront() {
    disposeCurrentPage();
    disposeCurrentPage = initializeStorefront();
}

document.addEventListener('turbo:load', bootStorefront);
document.addEventListener('turbo:before-visit', (event) => {
    const targetUrl = new URL(event.detail.url);
    const currentUrl = new URL(window.location.href);
    const samePageAnchor = targetUrl.origin === currentUrl.origin
        && targetUrl.pathname === currentUrl.pathname
        && targetUrl.search === currentUrl.search
        && targetUrl.hash !== '';

    if (!samePageAnchor) {
        showPageSkeleton();
    }
});
document.addEventListener('turbo:submit-start', showPageSkeleton);
document.addEventListener('turbo:render', hidePageSkeleton);
document.addEventListener('turbo:load', hidePageSkeleton);
document.addEventListener('turbo:submit-end', hidePageSkeleton);
document.addEventListener('turbo:before-cache', () => {
    disposeCurrentPage();
    disposeCurrentPage = () => {};
    hidePageSkeleton(true);
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootStorefront, { once: true });
} else {
    queueMicrotask(bootStorefront);
}
