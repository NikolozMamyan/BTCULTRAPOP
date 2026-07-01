import { Controller } from '@hotwired/stimulus';
import ProductModelScene from '../lib/can_product_scene.js';

export default class extends Controller {
    static targets = [
        'active',
        'canvas',
        'empty',
        'file',
        'form',
        'input',
        'loader',
        'modal',
        'modelType',
        'output',
        'subtitle',
        'textureName',
        'title',
        'token',
    ];

    static values = {
        cardHasTextureText: String,
        cardNoTextureText: String,
        defaultModelType: String,
        defaults: Object,
        defaultsByType: Object,
        errorText: String,
        noTextureText: String,
        readyText: String,
        toConfigureText: String,
    };

    connect() {
        this.handleKeydown = this.handleKeydown.bind(this);
        document.addEventListener('keydown', this.handleKeydown);
        this.sync();
    }

    disconnect() {
        document.removeEventListener('keydown', this.handleKeydown);
        this.disposeScene();
        this.revokeObjectUrl();
    }

    open(event) {
        const button = event.currentTarget;
        const product = this.currentProductData(button);

        if (!product) {
            return;
        }

        const textureUrl = product.textureUrl || '';

        this.formTarget.action = product.actionUrl || '';
        this.tokenTarget.value = product.token || '';
        this.titleTarget.textContent = product.name || '';
        this.subtitleTarget.textContent = this.productSubtitle(product);
        this.activeTarget.checked = product.active === '1';
        this.modelTypeTarget.value = product.modelType || this.defaultModelTypeValue;
        this.fileTarget.value = '';
        this.currentTextureUrl = textureUrl;
        this.textureNameTarget.textContent = textureUrl
            ? this.filenameFromUrl(textureUrl)
            : this.noTextureTextValue;

        this.setShape(this.parseShape(product.shape));
        this.openModal();
        this.renderPreview(textureUrl);
    }

    selectProduct(event) {
        const card = event.currentTarget.closest('.admin-model-3d-card');

        if (!card) {
            return;
        }

        this.refreshCard(card);
    }

    changeModelType() {
        this.setShape(this.defaultsForType(this.currentModelType()));
        this.renderPreview(this.objectUrl || this.currentTextureUrl || '');
    }

    close() {
        this.modalTarget.classList.remove('is-open');
        this.modalTarget.setAttribute('aria-hidden', 'true');
        window.setTimeout(() => this.modalTarget.classList.add('is-hidden'), 240);
        document.body.classList.remove('overflow-hidden');
        this.disposeScene();
        this.revokeObjectUrl();
    }

    reset() {
        this.setShape(this.defaultsForType(this.currentModelType()));
        this.updateSceneShape();
    }

    sync() {
        this.inputTargets.forEach((input) => {
            const output = this.outputTargets.find((target) => target.dataset.modelField === input.dataset.modelField);

            if (output) {
                output.textContent = this.formatValue(input.dataset.modelField, Number(input.value));
            }
        });

        this.updateSceneShape();
    }

    previewUpload() {
        const [file] = this.fileTarget.files;

        if (!file) {
            this.renderPreview(this.currentTextureUrl || '');

            return;
        }

        this.revokeObjectUrl();
        this.objectUrl = URL.createObjectURL(file);
        this.textureNameTarget.textContent = file.name;
        this.renderPreview(this.objectUrl);
    }

    currentProductData(source) {
        const card = source.closest('.admin-model-3d-card');
        const select = card?.querySelector('[data-model-product-select]');
        const option = select?.selectedOptions?.[0];

        if (!option) {
            return null;
        }

        return {
            active: option.dataset.modelActive || '0',
            actionUrl: option.dataset.modelActionUrl || '',
            categoryPath: option.dataset.modelCategoryPath || '',
            image: option.dataset.modelProductImage || '',
            imageAlt: option.dataset.modelProductImageAlt || '',
            modelType: option.dataset.modelType || this.defaultModelTypeValue,
            name: option.dataset.modelProductName || option.textContent.trim(),
            reference: option.dataset.modelProductReference || '',
            shape: option.dataset.modelShape || '',
            textureUrl: option.dataset.modelTextureUrl || '',
            token: option.dataset.modelToken || '',
        };
    }

    refreshCard(card) {
        const data = this.currentProductData(card);

        if (!data) {
            return;
        }

        const thumb = card.querySelector('[data-model-card-thumb]');
        const status = card.querySelector('[data-model-card-status]');
        const description = card.querySelector('[data-model-card-description]');
        const imageUrl = data.textureUrl || data.image;
        const ready = data.active === '1' && Boolean(data.textureUrl);

        if (thumb) {
            thumb.classList.toggle('is-empty', !imageUrl);
            thumb.innerHTML = imageUrl
                ? `<img src="${this.escapeAttribute(imageUrl)}" alt="${this.escapeAttribute(data.imageAlt || data.name)}">`
                : '<i class="fa-solid fa-image"></i>';
        }

        if (status) {
            status.classList.toggle('is-ready', ready);
            status.innerHTML = `
                <i class="fa-solid ${ready ? 'fa-circle-check' : 'fa-circle-info'}"></i>
                <span>${this.escapeHtml(ready ? this.readyTextValue : this.toConfigureTextValue)}</span>
            `;
        }

        if (description) {
            description.textContent = data.textureUrl ? this.cardHasTextureTextValue : this.cardNoTextureTextValue;
        }
    }

    openModal() {
        this.modalTarget.classList.remove('is-hidden');
        window.requestAnimationFrame(() => {
            this.modalTarget.classList.add('is-open');
            this.modalTarget.setAttribute('aria-hidden', 'false');
        });
        document.body.classList.add('overflow-hidden');
    }

    async renderPreview(textureUrl) {
        this.disposeScene();

        if (!textureUrl) {
            this.canvasTarget.hidden = true;
            this.loaderTarget.hidden = true;
            this.emptyTarget.hidden = false;
            this.emptyTarget.querySelector('span').textContent = this.noTextureTextValue;

            return;
        }

        this.canvasTarget.hidden = false;
        this.loaderTarget.hidden = false;
        this.emptyTarget.hidden = true;

        const scene = new ProductModelScene(this.canvasTarget, textureUrl, this.currentShape(), this.currentModelType());
        this.scene = scene;

        try {
            await scene.start();

            if (this.scene !== scene) {
                scene.dispose();

                return;
            }

            this.loaderTarget.hidden = true;
            window.requestAnimationFrame(() => scene.resize());
        } catch {
            if (this.scene === scene) {
                this.scene = null;
            }

            scene.dispose();
            this.canvasTarget.hidden = true;
            this.loaderTarget.hidden = true;
            this.emptyTarget.hidden = false;
            this.emptyTarget.querySelector('span').textContent = this.errorTextValue;
        }
    }

    updateSceneShape() {
        this.scene?.updateShape(this.currentShape(), this.currentModelType());
    }

    currentShape() {
        return this.inputTargets.reduce((shape, input) => {
            shape[input.dataset.modelField] = Number(input.value);

            return shape;
        }, {});
    }

    currentModelType() {
        return this.modelTypeTarget?.value || this.defaultModelTypeValue;
    }

    defaultsForType(modelType) {
        return this.defaultsByTypeValue?.[modelType] || this.defaultsValue;
    }

    setShape(shape) {
        const values = { ...this.defaultsForType(this.currentModelType()), ...shape };

        this.inputTargets.forEach((input) => {
            const value = values[input.dataset.modelField];

            if (value !== undefined) {
                input.value = String(value);
            }
        });

        this.sync();
    }

    disposeScene() {
        this.scene?.dispose();
        this.scene = null;
    }

    revokeObjectUrl() {
        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        }
    }

    parseShape(value) {
        if (!value) {
            return {};
        }

        try {
            return JSON.parse(value);
        } catch {
            return {};
        }
    }

    filenameFromUrl(url) {
        try {
            return decodeURIComponent(new URL(url, window.location.href).pathname.split('/').pop() || '');
        } catch {
            return url.split('/').pop() || '';
        }
    }

    productSubtitle(product) {
        const parts = [product.categoryPath];

        if (product.reference) {
            parts.push(`Réf. ${product.reference}`);
        }

        return parts.filter(Boolean).join(' · ');
    }

    escapeAttribute(value) {
        return this.escapeHtml(value).replaceAll('"', '&quot;');
    }

    escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    handleKeydown(event) {
        if (event.key === 'Escape' && this.modalTarget.classList.contains('is-open')) {
            this.close();
        }
    }

    formatValue(field, value) {
        if (field === 'height') {
            return value.toFixed(2);
        }

        if (field === 'topCut') {
            return `${Math.round(value * 100)}%`;
        }

        return `x${value.toFixed(2)}`;
    }
}
