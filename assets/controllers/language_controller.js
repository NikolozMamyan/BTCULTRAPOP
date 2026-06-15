import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        current: String,
    };

    connect() {
        this.updateOptions(this.currentValue || document.documentElement.lang || 'fr');
    }

    select({ params }) {
        const locale = ['fr', 'en'].includes(params.locale) ? params.locale : 'fr';

        document.cookie = `ultrapop_locale=${locale}; Path=/; SameSite=Lax`;
        document.documentElement.lang = locale;
        this.updateOptions(locale);

        if (window.Turbo) {
            window.Turbo.visit(window.location.href, { action: 'replace' });
            return;
        }

        window.location.reload();
    }

    updateOptions(locale) {
        this.element.querySelectorAll('[data-language-locale-param]').forEach((button) => {
            const selected = button.dataset.languageLocaleParam === locale;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
    }
}
