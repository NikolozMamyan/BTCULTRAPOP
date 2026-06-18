import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'results', 'clear'];

    static values = {
        url: String,
        initialTitle: String,
        initialText: String,
        noResultsTitle: String,
        noResultsText: String,
        errorTitle: String,
        errorText: String,
        inStock: String,
        outOfStock: String,
        resultCount: String,
        loading: String,
    };

    connect() {
        this.requestController = null;
        this.searchTimer = null;
        this.renderInitial();
    }

    disconnect() {
        window.clearTimeout(this.searchTimer);
        this.requestController?.abort();
    }

    search() {
        const query = this.inputTarget.value.trim();
        this.clearTarget.hidden = query.length === 0;
        window.clearTimeout(this.searchTimer);

        if (query.length < 2) {
            this.requestController?.abort();
            this.renderInitial();
            return;
        }

        this.renderLoading();
        this.searchTimer = window.setTimeout(() => this.load(query), 220);
    }

    clear() {
        this.inputTarget.value = '';
        this.clearTarget.hidden = true;
        this.requestController?.abort();
        this.renderInitial();
        this.inputTarget.focus();
    }

    async load(query) {
        this.requestController?.abort();
        this.requestController = new AbortController();

        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: this.requestController.signal,
            });

            if (!response.ok) {
                throw new Error(`Search failed with status ${response.status}`);
            }

            const payload = await response.json();

            if (this.inputTarget.value.trim() !== query) {
                return;
            }

            this.renderResults(payload.results || [], query);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.renderState('fa-triangle-exclamation', this.errorTitleValue, this.errorTextValue, 'is-error');
            }
        }
    }

    renderResults(results, query) {
        if (results.length === 0) {
            this.renderState(
                'fa-magnifying-glass',
                this.noResultsTitleValue,
                this.noResultsTextValue.replace('%query%', query),
            );
            return;
        }

        const countLabel = this.resultCountValue.replace('%count%', String(results.length));
        this.resultsTarget.innerHTML = `
            <div class="search-results__heading">
                <span>${this.escape(countLabel)}</span>
                <small>${this.escape(query)}</small>
            </div>
            <div class="search-results__list">
                ${results.map((product) => this.resultTemplate(product)).join('')}
            </div>
        `;
    }

    resultTemplate(product) {
        const stockLabel = product.inStock ? this.inStockValue : this.outOfStockValue;

        return `
            <a href="${this.escapeAttribute(product.url)}" class="search-result-card">
                <span class="search-result-card__image">
                    <img src="${this.escapeAttribute(product.image)}" alt="" loading="lazy">
                </span>
                <span class="search-result-card__content">
                    <small>${this.escape([product.category, product.license].filter(Boolean).join(' / '))}</small>
                    <strong>${this.escape(product.name)}</strong>
                    <em class="${product.inStock ? 'is-available' : 'is-unavailable'}">
                        <i class="fa-solid ${product.inStock ? 'fa-circle-check' : 'fa-circle-xmark'}"></i>
                        ${this.escape(stockLabel)}
                    </em>
                </span>
                <span class="search-result-card__price">${this.escape(product.priceFormatted)}</span>
                <i class="search-result-card__arrow fa-solid fa-arrow-right"></i>
            </a>
        `;
    }

    renderInitial() {
        this.renderState('fa-magnifying-glass', this.initialTitleValue, this.initialTextValue);
    }

    renderLoading() {
        this.resultsTarget.innerHTML = `
            <div class="search-loading" aria-label="${this.escapeAttribute(this.loadingValue)}">
                ${Array.from({ length: 4 }, () => `
                    <span class="search-loading__row">
                        <i></i>
                        <b></b>
                        <em></em>
                    </span>
                `).join('')}
            </div>
        `;
    }

    renderState(icon, title, text, tone = '') {
        this.resultsTarget.innerHTML = `
            <div class="search-empty-state ${tone}">
                <span><i class="fa-solid ${icon}"></i></span>
                <strong>${this.escape(title)}</strong>
                <p>${this.escape(text)}</p>
            </div>
        `;
    }

    escape(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    escapeAttribute(value) {
        return this.escape(value).replaceAll('`', '&#096;');
    }
}
