import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        filterField: String,
        heroAllLabel: String,
        heroImages: Object,
        pageSize: { type: Number, default: 6 },
    };

    static targets = [
        'activeCategoryImage',
        'activeCategoryTitle',
        'activeCount',
        'backdrop',
        'card',
        'category',
        'categoryGroup',
        'count',
        'empty',
        'grid',
        'hero',
        'heroImage',
        'loader',
        'mobileCount',
        'modal',
        'new',
        'price',
        'priceLabel',
        'promo',
        'remaining',
        'results',
        'sort',
        'trigger',
    ];

    connect() {
        const searchParams = new URLSearchParams(window.location.search);

        this.selectedCategory = this.initialSelectedCategory(searchParams);
        this.handleKeydown = this.handleKeydown.bind(this);
        this.handleWindowScroll = () => this.loadOnScroll();
        document.addEventListener('keydown', this.handleKeydown);
        window.addEventListener('scroll', this.handleWindowScroll, { passive: true });

        if (searchParams.get('filter') === 'nouveautes') {
            this.newTarget.checked = true;
        }

        this.syncCategoryButtons();
        this.syncCategoryGroups();
        this.syncHero();
        this.filter();
    }

    disconnect() {
        document.removeEventListener('keydown', this.handleKeydown);
        window.removeEventListener('scroll', this.handleWindowScroll);
        document.body.classList.remove('shop-filters-open');
    }

    openModal() {
        this.modalTarget.classList.add('is-open');
        this.backdropTarget.classList.add('is-open');
        this.triggerTarget.setAttribute('aria-expanded', 'true');
        document.body.classList.add('shop-filters-open');
        this.modalTarget.focus();
    }

    closeModal() {
        this.modalTarget.classList.remove('is-open');
        this.backdropTarget.classList.remove('is-open');
        this.triggerTarget.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('shop-filters-open');
    }

    handleKeydown(event) {
        if (event.key === 'Escape' && this.modalTarget.classList.contains('is-open')) {
            this.closeModal();
            this.triggerTarget.focus();
        }
    }

    selectCategory(event) {
        this.selectedCategory = event.params.category;

        this.syncCategoryButtons();
        this.syncCategoryGroups();
        this.syncHero();
        this.filter();
    }

    useImageFallback(event) {
        const image = event.currentTarget;
        const fallback = image.dataset.fallbackSrc;

        if (!fallback || image.dataset.fallbackApplied === 'true') {
            return;
        }

        image.dataset.fallbackApplied = 'true';
        image.src = fallback;
    }

    toggleCategoryGroup(event) {
        const openedGroup = event.currentTarget;

        if (!openedGroup.open) {
            return;
        }

        this.categoryGroupTargets.forEach((group) => {
            if (group !== openedGroup) {
                group.open = false;
            }
        });
    }

    reset() {
        this.selectedCategory = 'all';
        this.priceTarget.value = this.priceTarget.max;
        this.promoTarget.checked = false;
        this.newTarget.checked = false;

        this.categoryTargets.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.shopFiltersCategoryParam === 'all');
        });
        this.syncCategoryGroups();
        this.syncHero();

        this.filter();
    }

    filter() {
        const maximumPrice = Number(this.priceTarget.value);
        const promoOnly = this.promoTarget.checked;
        const newOnly = this.newTarget.checked;
        const requestedTags = [];
        const filterField = this.currentFilterField();

        if (promoOnly) {
            requestedTags.push('Promo');
        }

        if (newOnly) {
            requestedTags.push('Nouveau');
        }

        this.priceLabelTarget.textContent = maximumPrice;

        const matchingCards = this.cardTargets.filter((card) => {
            const matchesCategory = this.selectedCategory === 'all'
                || this.cardMatchesCategory(card, filterField);
            const matchesPrice = Number(card.dataset.price) <= maximumPrice;
            const matchesTag = requestedTags.length === 0
                || requestedTags.includes(card.dataset.tag);
            const visible = matchesCategory && matchesPrice && matchesTag;

            card.dataset.filterMatch = visible ? 'true' : 'false';
            card.hidden = true;

            return visible;
        });

        this.sortCards();
        this.filteredCards = this.cardTargets.filter(
            (card) => card.dataset.filterMatch === 'true',
        );
        this.renderedCardCount = 0;
        this.isLoadingMore = false;
        this.resultsTarget.scrollTop = 0;
        this.revealNextBatch();

        this.countTarget.textContent = matchingCards.length;
        this.mobileCountTarget.textContent = matchingCards.length;

        this.emptyTarget.hidden = matchingCards.length !== 0;
        this.updateActiveCount(maximumPrice, promoOnly, newOnly);
    }

    loadOnScroll() {
        if (
            this.isLoadingMore
            || !this.filteredCards
            || this.renderedCardCount >= this.filteredCards.length
        ) {
            return;
        }

        const styles = window.getComputedStyle(this.resultsTarget);
        const hasInternalScroll = ['auto', 'scroll'].includes(styles.overflowY)
            && this.resultsTarget.scrollHeight > this.resultsTarget.clientHeight;
        const distanceToEnd = hasInternalScroll
            ? this.resultsTarget.scrollHeight
                - this.resultsTarget.scrollTop
                - this.resultsTarget.clientHeight
            : this.loaderTarget.getBoundingClientRect().top - window.innerHeight;

        if (distanceToEnd > 320) {
            return;
        }

        this.isLoadingMore = true;
        this.loaderTarget.classList.add('is-loading');

        window.setTimeout(() => {
            this.revealNextBatch();
            this.loaderTarget.classList.remove('is-loading');
            this.isLoadingMore = false;
        }, 420);
    }

    revealNextBatch() {
        if (!this.filteredCards) {
            return;
        }

        const nextCount = Math.min(
            this.renderedCardCount + this.pageSizeValue,
            this.filteredCards.length,
        );

        this.filteredCards
            .slice(this.renderedCardCount, nextCount)
            .forEach((card, index) => {
                card.hidden = false;
                card.style.setProperty('--shop-card-reveal-index', index);
                this.hydrateCardImage(card);
            });

        this.renderedCardCount = nextCount;
        const remaining = Math.max(0, this.filteredCards.length - nextCount);

        this.remainingTarget.textContent = remaining;
        this.loaderTarget.hidden = remaining === 0;
    }

    hydrateCardImage(card) {
        const image = card.querySelector('img[data-src]');

        if (!image) {
            return;
        }

        const source = image.dataset.src;

        image.removeAttribute('data-src');

        if (!source) {
            image.classList.remove('is-deferred');

            return;
        }

        image.addEventListener('load', () => {
            image.classList.remove('is-deferred');
        }, { once: true });
        image.src = source;
    }

    updateActiveCount(maximumPrice, promoOnly, newOnly) {
        const activeFilters = [
            this.selectedCategory !== 'all',
            maximumPrice < Number(this.priceTarget.max),
            promoOnly,
            newOnly,
        ].filter(Boolean).length;

        this.activeCountTarget.textContent = activeFilters;
        this.activeCountTarget.hidden = activeFilters === 0;
    }

    sortCards() {
        const sort = this.sortTarget.value;
        const cards = [...this.cardTargets];
        const numeric = (card, key) => Number(card.dataset[key]);

        cards.sort((first, second) => {
            if (sort === 'price-asc') {
                return numeric(first, 'price') - numeric(second, 'price');
            }

            if (sort === 'price-desc') {
                return numeric(second, 'price') - numeric(first, 'price');
            }

            if (sort === 'rating') {
                return numeric(second, 'rating') - numeric(first, 'rating');
            }

            return numeric(second, 'popularity') - numeric(first, 'popularity');
        });

        cards.forEach((card) => this.gridTarget.append(card));
    }

    currentFilterField() {
        return this.hasFilterFieldValue ? this.filterFieldValue : 'category';
    }

    cardMatchesCategory(card, filterField) {
        if (filterField !== 'category') {
            return card.dataset[filterField] === this.selectedCategory;
        }

        return (card.dataset.categoryPath || '')
            .split('|')
            .includes(this.selectedCategory);
    }

    initialSelectedCategory(searchParams) {
        const requestedCategory = searchParams.get(this.currentFilterField());

        return requestedCategory && requestedCategory.trim() !== '' ? requestedCategory : 'all';
    }

    syncCategoryButtons() {
        this.categoryTargets.forEach((button) => {
            button.classList.toggle(
                'is-active',
                button.dataset.shopFiltersCategoryParam === this.selectedCategory,
            );
        });
    }

    syncCategoryGroups() {
        this.categoryGroupTargets.forEach((group) => {
            const hasActiveCategory = [...group.querySelectorAll('[data-shop-filters-target~="category"]')]
                .some((button) => button.classList.contains('is-active'));

            group.classList.toggle('has-active-category', hasActiveCategory);

            if (hasActiveCategory) {
                group.open = true;
            }
        });
    }

    syncHero() {
        if (
            !this.hasHeroTarget
            || !this.hasHeroImageTarget
            || !this.hasHeroImagesValue
        ) {
            return;
        }

        const activeCategory = this.categoryTargets.find(
            (button) => button.dataset.shopFiltersCategoryParam === this.selectedCategory,
        );
        const heroKey = activeCategory?.dataset.shopFiltersHeroKeyParam || 'all';
        const heroImages = this.heroImagesValue;
        const source = heroImages[heroKey] || heroImages.all;
        const label = this.selectedCategory === 'all'
            ? this.heroAllLabelValue
            : this.selectedCategory;

        this.heroImageTarget.alt = label;
        this.heroTarget.setAttribute('aria-label', label);
        this.heroTarget.dataset.heroKey = heroKey;

        if (this.hasActiveCategoryTitleTarget) {
            this.activeCategoryTitleTarget.textContent = label;
        }

        if (!source || new URL(source, window.location.href).href === this.heroImageTarget.src) {
            if (source && this.hasActiveCategoryImageTarget) {
                this.activeCategoryImageTarget.src = source;
            }

            return;
        }

        this.pendingHeroSource = source;
        const candidate = new Image();

        candidate.addEventListener('load', () => {
            if (this.pendingHeroSource !== source) {
                return;
            }

            this.heroTarget.classList.add('is-changing');

            requestAnimationFrame(() => {
                this.heroImageTarget.src = source;

                if (this.hasActiveCategoryImageTarget) {
                    this.activeCategoryImageTarget.src = source;
                }

                requestAnimationFrame(() => {
                    this.heroTarget.classList.remove('is-changing');
                });
            });
        }, { once: true });

        candidate.src = source;
    }
}
