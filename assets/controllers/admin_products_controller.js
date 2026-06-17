import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['manualInput', 'result', 'scanner', 'status', 'video'];

    static values = {
        cameraErrorText: String,
        createProductText: String,
        disabledText: String,
        enabledText: String,
        foundText: String,
        invalidText: String,
        manualText: String,
        missingText: String,
        openProductText: String,
        scanToken: String,
        scanUrl: String,
        searchingText: String,
    };

    connect() {
        this.detector = null;
        this.inFlight = false;
        this.lastCode = null;
        this.scanFrame = null;
        this.scanning = false;
        this.stream = null;
        this.handleKeydown = this.handleKeydown.bind(this);
        document.addEventListener('keydown', this.handleKeydown);
    }

    disconnect() {
        this.stopCamera();
        document.removeEventListener('keydown', this.handleKeydown);
    }

    openScanner() {
        this.scannerTarget.classList.remove('is-hidden');
        this.scannerTarget.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-scanner-open');
        this.resultTarget.replaceChildren();
        this.setStatus(this.hasManualTextValue ? this.manualTextValue : '');
        this.startCamera();
    }

    closeScanner() {
        this.scannerTarget.classList.add('is-hidden');
        this.scannerTarget.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admin-scanner-open');
        this.stopCamera();
    }

    async toggleActive(event) {
        const button = event.currentTarget;
        const wasActive = button.classList.contains('is-active');

        this.updateSwitch(button, !wasActive);
        button.disabled = true;

        try {
            const response = await fetch(event.params.url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-Token': event.params.token,
                },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'Unable to update product.');
            }

            this.updateSwitch(button, Boolean(payload.active));
        } catch (error) {
            this.updateSwitch(button, wasActive);
            this.setStatus(error.message);
        } finally {
            button.disabled = false;
        }
    }

    submitManual(event) {
        event.preventDefault();
        this.lookupCode(this.manualInputTarget.value);
    }

    handleKeydown(event) {
        if (!this.hasScannerTarget) {
            return;
        }

        if (event.key === 'Escape' && !this.scannerTarget.classList.contains('is-hidden')) {
            this.closeScanner();
        }
    }

    async startCamera() {
        if (!navigator.mediaDevices?.getUserMedia) {
            this.setStatus(this.manualTextValue);

            return;
        }

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
                audio: false,
            });
            this.videoTarget.srcObject = this.stream;
            await this.videoTarget.play();
        } catch (error) {
            this.setStatus(this.cameraErrorTextValue);

            return;
        }

        if (!('BarcodeDetector' in window)) {
            this.setStatus(this.manualTextValue);

            return;
        }

        try {
            const formats = await this.supportedBarcodeFormats();
            this.detector = new window.BarcodeDetector({ formats });
            this.scanning = true;
            this.setStatus(this.searchingTextValue);
            this.detectBarcode();
        } catch (error) {
            this.setStatus(this.manualTextValue);
        }
    }

    stopCamera() {
        this.scanning = false;

        if (this.scanFrame) {
            window.cancelAnimationFrame(this.scanFrame);
            this.scanFrame = null;
        }

        if (this.stream) {
            this.stream.getTracks().forEach((track) => track.stop());
            this.stream = null;
        }

        if (this.hasVideoTarget) {
            this.videoTarget.srcObject = null;
        }
    }

    async detectBarcode() {
        if (!this.scanning || !this.detector || this.inFlight) {
            return;
        }

        try {
            const barcodes = await this.detector.detect(this.videoTarget);
            const barcode = barcodes.find((candidate) => candidate.rawValue);

            if (barcode) {
                await this.lookupCode(barcode.rawValue);

                return;
            }
        } catch (error) {
            this.setStatus(this.manualTextValue);

            return;
        }

        this.scanFrame = window.requestAnimationFrame(() => this.detectBarcode());
    }

    async lookupCode(rawCode) {
        const code = String(rawCode || '').replace(/\D/g, '');

        if (!/^\d{8,13}$/.test(code)) {
            this.setStatus(this.invalidTextValue);

            return;
        }

        if (this.inFlight || this.lastCode === code) {
            return;
        }

        this.inFlight = true;
        this.lastCode = code;
        this.stopCamera();
        this.setStatus(`${this.searchingTextValue} ${code}`);
        this.resultTarget.replaceChildren();

        try {
            const response = await fetch(this.scanUrlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.scanTokenValue,
                },
                body: JSON.stringify({ ean: code }),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || this.invalidTextValue);
            }

            if (payload.found) {
                this.renderResult(
                    `${this.foundTextValue} ${payload.productName}`,
                    payload.redirectUrl,
                    this.openProductTextValue,
                    'success',
                );

                return;
            }

            this.renderResult(
                `${this.missingTextValue} ${code}`,
                payload.createUrl,
                this.createProductTextValue,
                'warning',
            );
        } catch (error) {
            this.setStatus(error.message);
            this.lastCode = null;
        } finally {
            this.inFlight = false;
        }
    }

    renderResult(message, url, label, tone) {
        const wrapper = document.createElement('div');
        wrapper.className = `admin-scanner-result__card admin-scanner-result__card--${tone}`;

        const text = document.createElement('strong');
        text.textContent = message;

        const link = document.createElement('a');
        link.href = url;
        link.textContent = label;

        wrapper.append(text, link);
        this.resultTarget.replaceChildren(wrapper);
        this.setStatus('');
    }

    setStatus(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message || '';
        }
    }

    updateSwitch(button, active) {
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');

        const label = button.querySelector('em');

        if (label) {
            label.textContent = active ? this.enabledTextValue : this.disabledTextValue;
        }
    }

    async supportedBarcodeFormats() {
        const requestedFormats = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128'];

        if (!window.BarcodeDetector.getSupportedFormats) {
            return requestedFormats;
        }

        const supportedFormats = await window.BarcodeDetector.getSupportedFormats();
        const formats = requestedFormats.filter((format) => supportedFormats.includes(format));

        return formats.length > 0 ? formats : requestedFormats;
    }
}
