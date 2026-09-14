import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'label'];

    static values = {
        text: String,
        copyLabel: String,
        copiedLabel: String,
        copyFailedLabel: String,
    };

    async copy() {
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(this.textValue);
            } else {
                this.copyWithFallback();
            }

            this.showResult(this.copiedLabelValue);
        } catch {
            try {
                this.copyWithFallback();
                this.showResult(this.copiedLabelValue);
            } catch {
                this.showResult(this.copyFailedLabelValue);
            }
        }
    }

    disconnect() {
        window.clearTimeout(this.resetTimer);
    }

    copyWithFallback() {
        const textarea = document.createElement('textarea');
        textarea.value = this.textValue;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        const copied = document.execCommand('copy');
        textarea.remove();

        if (!copied) {
            throw new Error('Copy failed');
        }
    }

    showResult(label) {
        window.clearTimeout(this.resetTimer);
        this.labelTarget.textContent = label;
        this.buttonTarget.classList.toggle('is-copied', label === this.copiedLabelValue);
        this.resetTimer = window.setTimeout(() => {
            this.labelTarget.textContent = this.copyLabelValue;
            this.buttonTarget.classList.remove('is-copied');
        }, 1600);
    }
}
