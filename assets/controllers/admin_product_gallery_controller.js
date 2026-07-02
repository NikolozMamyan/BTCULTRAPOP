import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'previewList'];

    connect() {
        this.objectUrls = [];
    }

    disconnect() {
        this.clearObjectUrls();
    }

    preview() {
        if (!this.hasInputTarget || !this.hasPreviewListTarget) {
            return;
        }

        this.clearPreviews();

        const files = Array.from(this.inputTarget.files || [])
            .filter((file) => file.type.startsWith('image/'));

        if (files.length === 0) {
            this.previewListTarget.hidden = true;

            return;
        }

        const fragment = document.createDocumentFragment();

        files.forEach((file) => {
            const objectUrl = URL.createObjectURL(file);

            this.objectUrls.push(objectUrl);
            fragment.appendChild(this.createPreviewTile(file, objectUrl));
        });

        this.previewListTarget.appendChild(fragment);
        this.previewListTarget.hidden = false;
    }

    createPreviewTile(file, objectUrl) {
        const tile = document.createElement('div');
        const image = document.createElement('img');
        const badge = document.createElement('span');
        const filename = document.createElement('small');

        tile.className = 'admin-product-gallery-tile admin-product-gallery-tile--pending';
        image.src = objectUrl;
        image.alt = file.name;
        badge.className = 'admin-product-gallery-tile__badge';
        badge.textContent = this.pendingLabel;
        filename.textContent = file.name;

        tile.append(image, badge, filename);

        return tile;
    }

    clearPreviews() {
        this.clearObjectUrls();
        this.previewListTarget.replaceChildren();
    }

    clearObjectUrls() {
        (this.objectUrls || []).forEach((objectUrl) => {
            URL.revokeObjectURL(objectUrl);
        });
        this.objectUrls = [];
    }

    get pendingLabel() {
        return this.element.dataset.adminProductGalleryPendingLabel || 'À enregistrer';
    }
}
