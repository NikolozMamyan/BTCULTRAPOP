let disposeCurrentPage = () => {};

function initializeStorefront() {
    const app = document.getElementById('storefront-app');
    if (!app) {
        return () => {};
    }

    const abortController = new AbortController();
    const { signal } = abortController;
    let carouselTimer;
    let countdownTimer;
    let toastTimer;
    let slideIndex = 0;

    localStorage.removeItem('ultrapop_cart');
    localStorage.removeItem('ultrapop_wishlist');

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

    const openSearch = () => {
        const modal = document.getElementById('search-modal');
        if (!modal) {
            return;
        }

        modal.classList.remove('hidden');
        requestAnimationFrame(() => modal.classList.add('open'));
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

    const resetCart = () => {
        document.querySelectorAll('.cart-count').forEach((badge) => {
            badge.textContent = '0';
        });

        const shippingBar = document.getElementById('ship-bar');
        if (shippingBar) {
            shippingBar.style.width = '0%';
        }

        const shippingMessage = document.getElementById('ship-msg');
        if (shippingMessage) {
            shippingMessage.textContent = app.dataset.cartShippingEmpty;
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

    const tickCountdown = () => {
        const hours = document.getElementById('cd-h');
        if (!hours) {
            return;
        }

        const end = new Date();
        end.setHours(23, 59, 59, 0);
        const difference = Math.max(0, end - new Date());
        const pad = (value) => String(value).padStart(2, '0');

        hours.textContent = pad(Math.floor(difference / 3.6e6));
        document.getElementById('cd-m').textContent = pad(Math.floor((difference % 3.6e6) / 6e4));
        document.getElementById('cd-s').textContent = pad(Math.floor((difference % 6e4) / 1e3));
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
                fallback.className = 'w-full h-[300px] md:h-[480px] flex items-center justify-center';
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
    document.querySelectorAll('[data-action="carousel-prev"]').forEach((button) => on(button, 'click', () => goToSlide(slideIndex - 1)));
    document.querySelectorAll('[data-action="carousel-next"]').forEach((button) => on(button, 'click', () => goToSlide(slideIndex + 1)));
    document.querySelectorAll('[data-action="back-to-top"]').forEach((button) => on(button, 'click', () => window.scrollTo({ top: 0, behavior: 'smooth' })));
    on(document.getElementById('newsletter-form'), 'submit', (event) => {
        event.preventDefault();
        document.getElementById('nl-success')?.classList.remove('hidden');
        event.target.reset();
    });

    on(document, 'keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            openSearch();
        }

        if (event.key === 'Escape') {
            closeSearch();
            closeCart();
        }
    });

    on(window, 'scroll', () => {
        triggerReveals();
        updateBackToTop();
    }, { passive: true });

    bindFallbacks();
    resetCart();
    renderCarouselDots();
    resetCarouselTimer();
    tickCountdown();
    countdownTimer = window.setInterval(tickCountdown, 1000);
    triggerReveals();
    updateBackToTop();

    return () => {
        abortController.abort();
        window.clearInterval(carouselTimer);
        window.clearInterval(countdownTimer);
        window.clearTimeout(toastTimer);
        setBodyLocked(false);
        document.getElementById('search-modal')?.classList.add('hidden');
        document.getElementById('cart-overlay')?.classList.add('hidden');
        document.getElementById('cart-drawer')?.classList.remove('open');
    };
}

function bootStorefront() {
    disposeCurrentPage();
    disposeCurrentPage = initializeStorefront();
}

document.addEventListener('turbo:load', bootStorefront);
document.addEventListener('turbo:before-cache', () => {
    disposeCurrentPage();
    disposeCurrentPage = () => {};
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootStorefront, { once: true });
} else {
    queueMicrotask(bootStorefront);
}
