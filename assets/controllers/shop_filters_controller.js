import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        filterField: String,
        clearLabel: String,
        heroAllLabel: String,
        heroImages: Object,
        heroMobileBreakpoint: String,
        heroMobileImages: Object,
        newLabel: String,
        pageSize: { type: Number, default: 6 },
        priceChip: String,
        promoLabel: String,
        removeFilter: String,
    };

    static targets = [
        'activeCategoryImage',
        'activeCategoryTitle',
        'activeCount',
        'activeFilters',
        'backdrop',
        'card',
        'category',
        'categoryGroup',
        'count',
        'empty',
        'grid',
        'hero',
        'heroImage',
        'heroMobileSource',
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
        this.restoreControls(searchParams);
        this.handleKeydown = this.handleKeydown.bind(this);
        this.handleWindowScroll = () => this.loadOnScroll();
        document.addEventListener('keydown', this.handleKeydown);
        window.addEventListener('scroll', this.handleWindowScroll, { passive: true });
        this.setupHeroMediaQuery();

        this.syncCategoryButtons();
        this.syncCategoryGroups();
        this.syncHero();
        this.filter();
    }

    disconnect() {
        document.removeEventListener('keydown', this.handleKeydown);
        window.removeEventListener('scroll', this.handleWindowScroll);
        this.teardownHeroMediaQuery();
        document.body.classList.remove('shop-filters-open');
    }

    setupHeroMediaQuery() {
        if (!this.hasHeroMobileBreakpointValue) {
            return;
        }

        this.heroMediaQuery = window.matchMedia(`(max-width: ${this.heroMobileBreakpointValue})`);
        this.handleHeroMediaChange = () => this.syncHero();

        if (this.heroMediaQuery.addEventListener) {
            this.heroMediaQuery.addEventListener('change', this.handleHeroMediaChange);

            return;
        }

        this.heroMediaQuery.addListener(this.handleHeroMediaChange);
    }

    teardownHeroMediaQuery() {
        if (!this.heroMediaQuery || !this.handleHeroMediaChange) {
            return;
        }

        if (this.heroMediaQuery.removeEventListener) {
            this.heroMediaQuery.removeEventListener('change', this.handleHeroMediaChange);
        } else {
            this.heroMediaQuery.removeListener(this.handleHeroMediaChange);
        }
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

    selectCategoryGroup(event) {
        const group = event.currentTarget.closest('[data-shop-filters-target~="categoryGroup"]');
        const parentCategoryButton = group?.querySelector('.shop-category-button--parent[data-shop-filters-target~="category"]');

        if (!group || !parentCategoryButton) {
            return;
        }

        event.preventDefault();

        this.selectedCategory = parentCategoryButton.dataset.shopFiltersCategoryParam || 'all';
        group.open = true;

        this.categoryGroupTargets.forEach((categoryGroup) => {
            if (categoryGroup !== group) {
                categoryGroup.open = false;
            }
        });

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
        const restoredCardCount = this.restoredCardCount;

        this.restoredCardCount = null;
        this.revealNextBatch(restoredCardCount);

        this.countTarget.textContent = matchingCards.length;
        this.mobileCountTarget.textContent = matchingCards.length;

        this.emptyTarget.hidden = matchingCards.length !== 0;
        this.updateActiveCount(maximumPrice, promoOnly, newOnly);
        this.renderActiveFilters(maximumPrice, promoOnly, newOnly);
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

    revealNextBatch(requestedCount = null) {
        if (!this.filteredCards) {
            return;
        }

        const targetCount = Number.isInteger(requestedCount) && requestedCount > 0
            ? Math.max(requestedCount, this.pageSizeValue)
            : this.renderedCardCount + this.pageSizeValue;
        const nextCount = Math.min(
            targetCount,
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
        this.syncUrl();
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

    renderActiveFilters(maximumPrice, promoOnly, newOnly) {
        if (!this.hasActiveFiltersTarget) {
            return;
        }

        const filters = [];

        if (this.selectedCategory !== 'all') {
            filters.push({ type: 'category', label: this.selectedCategory });
        }

        if (maximumPrice < Number(this.priceTarget.max)) {
            filters.push({
                type: 'price',
                label: this.priceChipValue.replace('%price%', String(maximumPrice)),
            });
        }

        if (promoOnly) {
            filters.push({ type: 'promo', label: this.promoLabelValue });
        }

        if (newOnly) {
            filters.push({ type: 'new', label: this.newLabelValue });
        }

        const buttons = filters.map((filter) => {
            const button = document.createElement('button');
            const label = document.createElement('span');
            const icon = document.createElement('i');

            button.type = 'button';
            button.className = 'shop-active-filter';
            button.dataset.action = 'shop-filters#removeFilter';
            button.dataset.shopFiltersFilterParam = filter.type;
            button.setAttribute(
                'aria-label',
                this.removeFilterValue.replace('%filter%', filter.label),
            );
            label.textContent = filter.label;
            icon.className = 'fa-solid fa-xmark';
            icon.setAttribute('aria-hidden', 'true');
            button.append(label, icon);

            return button;
        });

        if (filters.length > 1) {
            const clearButton = document.createElement('button');

            clearButton.type = 'button';
            clearButton.className = 'shop-active-filters__clear';
            clearButton.dataset.action = 'shop-filters#reset';
            clearButton.textContent = this.clearLabelValue;
            buttons.push(clearButton);
        }

        this.activeFiltersTarget.replaceChildren(...buttons);
        this.activeFiltersTarget.hidden = filters.length === 0;
    }

    removeFilter(event) {
        const filter = event.params.filter;

        if (filter === 'category') {
            this.selectedCategory = 'all';
            this.syncCategoryButtons();
            this.syncCategoryGroups();
            this.syncHero();
        } else if (filter === 'price') {
            this.priceTarget.value = this.priceTarget.max;
        } else if (filter === 'promo') {
            this.promoTarget.checked = false;
        } else if (filter === 'new') {
            this.newTarget.checked = false;
        } else {
            return;
        }

        this.filter();
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

    restoreControls(searchParams) {
        const minimumPrice = Number(this.priceTarget.min);
        const maximumPrice = Number(this.priceTarget.max);
        const requestedPrice = Number(searchParams.get('price'));

        if (searchParams.has('price') && Number.isFinite(requestedPrice)) {
            this.priceTarget.value = String(Math.min(maximumPrice, Math.max(minimumPrice, requestedPrice)));
        }

        this.promoTarget.checked = searchParams.get('promo') === '1';
        this.newTarget.checked = searchParams.get('new') === '1'
            || searchParams.get('filter') === 'nouveautes';

        const requestedSort = searchParams.get('sort');
        const availableSorts = [...this.sortTarget.options].map((option) => option.value);

        if (requestedSort && availableSorts.includes(requestedSort)) {
            this.sortTarget.value = requestedSort;
        }

        const requestedCardCount = Number.parseInt(searchParams.get('shown') || '', 10);

        this.restoredCardCount = Number.isInteger(requestedCardCount) && requestedCardCount > 0
            ? requestedCardCount
            : null;
    }

    syncUrl() {
        const url = new URL(window.location.href);
        const searchParams = url.searchParams;
        const filterField = this.currentFilterField();
        const maximumPrice = Number(this.priceTarget.max);
        const selectedPrice = Number(this.priceTarget.value);
        const defaultCardCount = Math.min(
            this.pageSizeValue,
            this.filteredCards?.length || 0,
        );

        this.setSearchParam(searchParams, filterField, this.selectedCategory !== 'all' ? this.selectedCategory : '');
        this.setSearchParam(searchParams, 'price', selectedPrice < maximumPrice ? String(selectedPrice) : '');
        this.setSearchParam(searchParams, 'promo', this.promoTarget.checked ? '1' : '');
        this.setSearchParam(searchParams, 'new', this.newTarget.checked ? '1' : '');
        this.setSearchParam(searchParams, 'sort', this.sortTarget.value !== 'pop' ? this.sortTarget.value : '');
        this.setSearchParam(
            searchParams,
            'shown',
            this.renderedCardCount > defaultCardCount ? String(this.renderedCardCount) : '',
        );
        searchParams.delete('filter');

        if (url.href !== window.location.href) {
            window.history.replaceState(window.history.state, '', url);
        }
    }

    setSearchParam(searchParams, key, value) {
        if (value) {
            searchParams.set(key, value);

            return;
        }

        searchParams.delete(key);
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
        const { desktopSource, mobileSource, source } = this.heroSourcesFor(heroKey);
        const label = this.selectedCategory === 'all'
            ? this.heroAllLabelValue
            : this.selectedCategory;

        this.heroImageTarget.alt = label;
        this.heroTarget.setAttribute('aria-label', label);
        this.heroTarget.dataset.heroKey = heroKey;

        if (this.hasActiveCategoryTitleTarget) {
            this.activeCategoryTitleTarget.textContent = label;
        }

        if (!source || new URL(source, window.location.href).href === (this.heroImageTarget.currentSrc || this.heroImageTarget.src)) {
            this.applyHeroSources(desktopSource, mobileSource);

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
                this.applyHeroSources(desktopSource, mobileSource);

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

    heroSourcesFor(heroKey) {
        const heroImages = this.heroImagesValue;
        const desktopSource = heroImages[heroKey] || heroImages.all;
        const mobileImages = this.hasHeroMobileImagesValue ? this.heroMobileImagesValue : {};
        const mobileSource = mobileImages[heroKey] || '';
        const source = this.usesMobileHero() && mobileSource ? mobileSource : desktopSource;

        return {
            desktopSource,
            mobileSource,
            source,
        };
    }

    usesMobileHero() {
        return Boolean(this.heroMediaQuery?.matches);
    }

    applyHeroSources(desktopSource, mobileSource) {
        if (this.hasHeroMobileSourceTarget) {
            this.heroMobileSourceTarget.srcset = mobileSource || desktopSource || '';

            if (this.hasHeroMobileBreakpointValue) {
                this.heroMobileSourceTarget.media = `(max-width: ${this.heroMobileBreakpointValue})`;
            }
        }

        if (desktopSource) {
            this.heroImageTarget.src = desktopSource;
        }
    }
}
