import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        filterField: String,
    };

    static targets = [
        'activeCount',
        'backdrop',
        'card',
        'category',
        'count',
        'empty',
        'grid',
        'mobileCount',
        'modal',
        'new',
        'price',
        'priceLabel',
        'promo',
        'sort',
        'trigger',
    ];

    connect() {
        const searchParams = new URLSearchParams(window.location.search);

        this.selectedCategory = this.initialSelectedCategory(searchParams);
        this.handleKeydown = this.handleKeydown.bind(this);
        document.addEventListener('keydown', this.handleKeydown);

        if (searchParams.get('filter') === 'nouveautes') {
            this.newTarget.checked = true;
        }

        this.syncCategoryButtons();
        this.filter();
    }

    disconnect() {
        document.removeEventListener('keydown', this.handleKeydown);
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

        this.categoryTargets.forEach((button) => {
            button.classList.toggle('is-active', button === event.currentTarget);
        });

        this.filter();
    }

    reset() {
        this.selectedCategory = 'all';
        this.priceTarget.value = this.priceTarget.max;
        this.promoTarget.checked = false;
        this.newTarget.checked = false;

        this.categoryTargets.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.shopFiltersCategoryParam === 'all');
        });

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

        const visibleCards = this.cardTargets.filter((card) => {
            const matchesCategory = this.selectedCategory === 'all'
                || card.dataset[filterField] === this.selectedCategory;
            const matchesPrice = Number(card.dataset.price) <= maximumPrice;
            const matchesTag = requestedTags.length === 0
                || requestedTags.includes(card.dataset.tag);
            const visible = matchesCategory && matchesPrice && matchesTag;

            card.hidden = !visible;

            return visible;
        });

        this.sortCards();
        this.countTarget.textContent = visibleCards.length;
        this.mobileCountTarget.textContent = visibleCards.length;
        this.emptyTarget.hidden = visibleCards.length !== 0;
        this.updateActiveCount(maximumPrice, promoOnly, newOnly);
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
}
