import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'cameraButton', 'finishButton', 'item', 'manualInput', 'message', 'preparedTotal',
        'progressBar', 'progressText', 'remainingTotal', 'status', 'video',
    ];

    static values = {
        cameraErrorText: String,
        completeUrl: String,
        confirmText: String,
        finishText: String,
        invalidText: String,
        readyText: String,
        savingText: String,
        scanUrl: String,
        scanningText: String,
        stopText: String,
        token: String,
    };

    connect() {
        this.detector = null;
        this.inFlight = false;
        this.lockedCode = null;
        this.scanFrame = null;
        this.scanning = false;
        this.stream = null;
        this.hardwareBuffer = '';
        this.hardwareTimer = null;
        this.handleHardwareScanner = this.handleHardwareScanner.bind(this);
        document.addEventListener('keydown', this.handleHardwareScanner);

        if (this.hasCameraButtonTarget) {
            this.cameraButtonContent = this.cameraButtonTarget.innerHTML;
        }

        if (this.hasManualInputTarget) {
            this.manualInputTarget.focus({ preventScroll: true });
        }
    }

    disconnect() {
        this.stopCamera();
        document.removeEventListener('keydown', this.handleHardwareScanner);

        if (this.hardwareTimer) {
            window.clearTimeout(this.hardwareTimer);
        }
    }

    async toggleCamera() {
        if (this.scanning || this.stream) {
            this.stopCamera();
            this.setStatus(this.readyTextValue);

            return;
        }

        await this.startCamera();
    }

    async startCamera() {
        if (!navigator.mediaDevices?.getUserMedia || !('BarcodeDetector' in window)) {
            this.setStatus(this.cameraErrorTextValue, 'error');

            return;
        }

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false,
            });
            this.videoTarget.srcObject = this.stream;
            await this.videoTarget.play();
            this.detector = new window.BarcodeDetector({ formats: await this.supportedBarcodeFormats() });
            this.scanning = true;
            this.cameraButtonTarget.classList.add('is-active');
            this.cameraButtonTarget.innerHTML = `<i class="fa-solid fa-stop"></i> ${this.escapeHtml(this.stopTextValue)}`;
            this.setStatus(this.scanningTextValue);
            this.detectBarcode();
        } catch (error) {
            this.stopCamera();
            this.setStatus(this.cameraErrorTextValue, 'error');
        }
    }

    stopCamera() {
        this.scanning = false;

        if (this.scanFrame) {
            window.cancelAnimationFrame(this.scanFrame);
            this.scanFrame = null;
        }

        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;

        if (this.hasVideoTarget) {
            this.videoTarget.srcObject = null;
        }

        if (this.hasCameraButtonTarget) {
            this.cameraButtonTarget.classList.remove('is-active');
            this.cameraButtonTarget.innerHTML = this.cameraButtonContent;
        }
    }

    async detectBarcode() {
        if (!this.scanning || !this.detector) {
            return;
        }

        try {
            const barcodes = await this.detector.detect(this.videoTarget);
            const code = barcodes.find((candidate) => candidate.rawValue)?.rawValue || null;

            if (!code) {
                this.lockedCode = null;
            } else if (!this.inFlight && code !== this.lockedCode) {
                this.lockedCode = code;
                await this.submitCode(code);
            }
        } catch (error) {
            this.setStatus(this.cameraErrorTextValue, 'error');
        }

        if (this.scanning) {
            this.scanFrame = window.requestAnimationFrame(() => this.detectBarcode());
        }
    }

    submitManual(event) {
        event.preventDefault();
        const code = this.manualInputTarget.value;
        this.manualInputTarget.value = '';
        this.submitCode(code);
        this.manualInputTarget.focus();
    }

    async submitCode(rawCode) {
        const code = String(rawCode || '').replace(/\D/g, '');

        if (!/^\d{8,13}$/.test(code)) {
            this.showMessage(this.invalidTextValue, 'error');

            return;
        }

        if (this.inFlight) {
            return;
        }

        this.inFlight = true;
        this.setStatus(`${this.scanningTextValue} ${code}`);

        try {
            const payload = await this.request(this.scanUrlValue, { ean: code });
            this.applyPayload(payload);
            this.showMessage(payload.message, 'success');
        } catch (error) {
            this.showMessage(error.message, 'error');
        } finally {
            this.inFlight = false;
            this.setStatus(this.scanning ? this.scanningTextValue : this.readyTextValue);
        }
    }

    adjustQuantity(event) {
        const row = event.currentTarget.closest('[data-item-id]');
        const input = row?.querySelector('input[type="number"]');

        if (!input) {
            return;
        }

        const next = Number(input.value) + Number(event.params.step || 0);
        this.saveQuantity(row, Math.max(0, Math.min(Number(input.max), next)));
    }

    changeQuantity(event) {
        const row = event.currentTarget.closest('[data-item-id]');
        const input = event.currentTarget;
        const quantity = Math.max(0, Math.min(Number(input.max), Number(input.value)));
        this.saveQuantity(row, quantity);
    }

    async saveQuantity(row, quantity) {
        if (!row || this.inFlight) {
            return;
        }

        this.inFlight = true;
        row.classList.add('is-saving');
        this.setStatus(this.savingTextValue);

        try {
            const payload = await this.request(row.dataset.quantityUrl, { quantity });
            this.applyPayload(payload);
        } catch (error) {
            this.showMessage(error.message, 'error');
        } finally {
            row.classList.remove('is-saving');
            this.inFlight = false;
            this.setStatus(this.scanning ? this.scanningTextValue : this.readyTextValue);
        }
    }

    async complete() {
        if (!window.confirm(this.confirmTextValue)) {
            return;
        }

        const button = this.finishButtonTarget;
        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${this.escapeHtml(this.finishTextValue)}`;

        try {
            const payload = await this.request(this.completeUrlValue, {});
            this.stopCamera();
            this.showMessage(payload.message, 'success');
            window.setTimeout(() => { window.location.href = payload.redirectUrl; }, 800);
        } catch (error) {
            button.disabled = false;
            button.innerHTML = originalContent;
            this.showMessage(error.message, 'error');
        }
    }

    applyPayload(payload) {
        if (payload.item) {
            const row = this.itemTargets.find((candidate) => Number(candidate.dataset.itemId) === Number(payload.item.id));

            if (row) {
                row.classList.toggle('is-complete', Boolean(payload.item.complete));
                const input = row.querySelector('input[type="number"]');
                const label = row.querySelector('[data-prepared-label]');

                if (input) input.value = payload.item.prepared_quantity;
                if (label) label.textContent = `${payload.item.prepared_quantity} / ${payload.item.quantity}`;
            }
        }

        if (payload.preparation) {
            const preparation = payload.preparation;
            this.progressBarTarget.style.width = `${preparation.progress}%`;
            this.progressTextTarget.textContent = `${preparation.progress}%`;
            this.preparedTotalTarget.textContent = preparation.prepared_quantity;
            this.remainingTotalTarget.textContent = preparation.remaining_quantity;
            this.finishButtonTarget.disabled = !preparation.complete;
        }
    }

    async request(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.tokenValue,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload.ok === false) {
            throw new Error(payload.message || `Erreur HTTP ${response.status}`);
        }

        return payload;
    }

    handleHardwareScanner(event) {
        if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
            return;
        }

        if (event.key === 'Enter' && this.hardwareBuffer) {
            const code = this.hardwareBuffer;
            this.hardwareBuffer = '';
            this.submitCode(code);
            event.preventDefault();

            return;
        }

        if (!/^\d$/.test(event.key)) {
            return;
        }

        this.hardwareBuffer += event.key;
        window.clearTimeout(this.hardwareTimer);
        this.hardwareTimer = window.setTimeout(() => { this.hardwareBuffer = ''; }, 120);
    }

    showMessage(message, tone) {
        this.messageTarget.className = `admin-preparation-message is-${tone}`;
        this.messageTarget.innerHTML = `<i class="fa-solid ${tone === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}"></i><span>${this.escapeHtml(message)}</span>`;
    }

    setStatus(message, tone = '') {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message || '';
            this.statusTarget.classList.toggle('is-error', tone === 'error');
        }
    }

    escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    async supportedBarcodeFormats() {
        const requested = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128'];

        if (!window.BarcodeDetector.getSupportedFormats) {
            return requested;
        }

        const supported = await window.BarcodeDetector.getSupportedFormats();
        const formats = requested.filter((format) => supported.includes(format));

        return formats.length ? formats : requested;
    }
}
