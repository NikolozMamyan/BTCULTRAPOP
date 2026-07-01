import { Controller } from '@hotwired/stimulus';
import ProductModelScene from '../lib/can_product_scene.js';

export default class extends Controller {
    static targets = [
        'canvas',
        'content',
        'dialog',
        'error',
        'image',
        'link',
        'loader',
        'media',
        'root',
        'title',
    ];

    connect() {
        this.handleKeydown = this.handleKeydown.bind(this);
        this.close = this.close.bind(this);
        document.addEventListener('keydown', this.handleKeydown);
        document.addEventListener('turbo:before-cache', this.close);
    }

    disconnect() {
        document.removeEventListener('keydown', this.handleKeydown);
        document.removeEventListener('turbo:before-cache', this.close);
        this.disposeScene();
    }

    open(event) {
        event.preventDefault();
        event.stopPropagation();

        const button = event.currentTarget;
        const imageUrl = button.dataset.productPreviewImage || '';
        const title = button.dataset.productPreviewTitle || '';
        const url = button.dataset.productPreviewUrl || '#';
        const model = this.parseModel(button.dataset.productPreviewModel || '');

        this.currentImageUrl = imageUrl;
        this.currentTitle = title;
        this.dialogTarget.setAttribute('aria-label', title ? `Aperçu 3D - ${title}` : 'Aperçu 3D du produit');
        this.titleTarget.textContent = title;
        this.linkTarget.href = url;
        this.errorTarget.hidden = true;
        this.errorTarget.textContent = '';
        this.showModal();

        if (model?.type && model.shape && model.texture) {
            this.show3D();
            window.requestAnimationFrame(() => this.startScene(model.texture, model.shape, model.type));

            return;
        }

        this.showImage();
    }

    close() {
        const alreadyHidden = this.rootTarget.getAttribute('aria-hidden') === 'true';
        this.disposeScene();

        if (alreadyHidden) {
            this.rootTarget.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');

            return;
        }

        this.rootTarget.classList.remove('open');
        this.rootTarget.setAttribute('aria-hidden', 'true');
        window.setTimeout(() => this.rootTarget.classList.add('hidden'), 260);
        document.body.classList.remove('overflow-hidden');
    }

    showModal() {
        this.rootTarget.classList.remove('hidden');
        window.requestAnimationFrame(() => {
            this.rootTarget.classList.add('open');
            this.rootTarget.setAttribute('aria-hidden', 'false');
        });
        document.body.classList.add('overflow-hidden');
    }

    show3D() {
        this.disposeScene();
        this.dialogTarget.classList.add('is-3d-preview');
        this.dialogTarget.classList.remove('is-image-preview');
        this.mediaTarget.classList.add('is-3d-mode');
        this.mediaTarget.classList.remove('is-image-mode');
        this.contentTarget.hidden = true;
        this.imageTarget.removeAttribute('src');
        this.imageTarget.alt = '';
        this.imageTarget.hidden = true;
        this.canvasTarget.hidden = false;
        this.loaderTarget.hidden = false;
    }

    showImage(message = '') {
        this.disposeScene();
        this.dialogTarget.classList.add('is-image-preview');
        this.dialogTarget.classList.remove('is-3d-preview');
        this.mediaTarget.classList.add('is-image-mode');
        this.mediaTarget.classList.remove('is-3d-mode');
        this.contentTarget.hidden = false;
        this.canvasTarget.hidden = true;
        this.loaderTarget.hidden = true;
        this.imageTarget.src = this.currentImageUrl || '';
        this.imageTarget.alt = this.currentTitle || '';
        this.imageTarget.hidden = false;

        if (message) {
            this.errorTarget.textContent = message;
            this.errorTarget.hidden = false;
        }
    }

    async startScene(imageUrl, shape, modelType = 'can') {
        this.disposeScene();
        const scene = new ProductModelScene(this.canvasTarget, imageUrl, shape, modelType);
        this.scene = scene;

        try {
            await scene.start();

            if (this.scene !== scene) {
                scene.dispose();

                return;
            }

            this.loaderTarget.hidden = true;
            window.requestAnimationFrame(() => scene.resize());
        } catch (error) {
            if (this.scene === scene) {
                this.scene = null;
            }

            scene.dispose();
            this.showImage('Aperçu 3D indisponible sur ce navigateur. Image produit affichée.');
        }
    }

    disposeScene() {
        this.scene?.dispose();
        this.scene = null;
    }

    parseModel(value) {
        if (!value) {
            return null;
        }

        try {
            return JSON.parse(value);
        } catch {
            return null;
        }
    }

    handleKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
