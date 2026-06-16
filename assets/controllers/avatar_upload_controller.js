import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['fallback', 'input', 'preview'];

    submit() {
        const file = this.inputTarget.files?.[0];

        if (!file) {
            return;
        }

        if (this.hasPreviewTarget && file.type.startsWith('image/')) {
            this.previewTarget.src = URL.createObjectURL(file);
            this.previewTarget.classList.remove('is-hidden');

            if (this.hasFallbackTarget) {
                this.fallbackTarget.classList.add('is-hidden');
            }
        }

        this.element.requestSubmit();
    }
}
