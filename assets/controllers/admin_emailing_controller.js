import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'name',
        'subject',
        'html',
        'preview',
        'recipientToggle',
        'recipientPanel',
        'recipientSummary',
        'recipientCheckbox',
        'selectAll',
        'manualInput',
        'manualList',
    ];

    static values = {
        recipientEmpty: String,
        recipientCount: String,
        manualInvalid: String,
    };

    connect() {
        this.closeRecipientsHandler = this.closeRecipientsOnOutsideClick.bind(this);
        document.addEventListener('click', this.closeRecipientsHandler);

        this.refreshPreview();
        this.updateRecipientSummary();
    }

    disconnect() {
        document.removeEventListener('click', this.closeRecipientsHandler);
    }

    refreshPreview() {
        if (!this.hasPreviewTarget || !this.hasHtmlTarget) {
            return;
        }

        const html = this.htmlTarget.value.trim();
        this.previewTarget.srcdoc = html || this.emptyPreview();
    }

    toggleRecipients(event) {
        event.preventDefault();

        if (!this.hasRecipientPanelTarget) {
            return;
        }

        this.recipientPanelTarget.hidden = !this.recipientPanelTarget.hidden;
        this.recipientToggleTarget.classList.toggle('is-open', !this.recipientPanelTarget.hidden);
    }

    toggleAllRecipients() {
        const checked = this.hasSelectAllTarget && this.selectAllTarget.checked;

        this.recipientCheckboxTargets.forEach((checkbox) => {
            checkbox.checked = checked;
        });

        this.updateRecipientSummary();
    }

    manualKeydown(event) {
        if (event.key !== 'Enter' && event.key !== ',') {
            return;
        }

        event.preventDefault();
        this.addManualEmail(event);
    }

    addManualEmail(event) {
        event?.preventDefault();

        if (!this.hasManualInputTarget || !this.hasManualListTarget) {
            return;
        }

        const email = this.manualInputTarget.value.trim().toLowerCase();
        this.manualInputTarget.setCustomValidity('');

        if (email === '') {
            return;
        }

        if (!this.isValidEmail(email)) {
            this.manualInputTarget.setCustomValidity(this.manualInvalidValue || 'Email invalide.');
            this.manualInputTarget.reportValidity();
            return;
        }

        if (this.manualEmails().includes(email)) {
            this.manualInputTarget.value = '';
            return;
        }

        this.manualListTarget.appendChild(this.buildManualEmailChip(email));
        this.manualInputTarget.value = '';
        this.updateRecipientSummary();
    }

    removeManualEmail(event) {
        event.preventDefault();

        event.target.closest('.admin-emailing-chip')?.remove();
        this.updateRecipientSummary();
    }

    updateRecipientSummary() {
        const checkedCount = this.recipientCheckboxTargets.filter((checkbox) => checkbox.checked).length;
        const manualCount = this.manualEmails().length;
        const total = checkedCount + manualCount;

        if (this.hasRecipientSummaryTarget) {
            this.recipientSummaryTarget.textContent = total > 0
                ? this.recipientCountValue.replace('__COUNT__', total.toString())
                : this.recipientEmptyValue;
        }

        if (!this.hasSelectAllTarget) {
            return;
        }

        const selectableCount = this.recipientCheckboxTargets.length;
        this.selectAllTarget.checked = selectableCount > 0 && checkedCount === selectableCount;
        this.selectAllTarget.indeterminate = checkedCount > 0 && checkedCount < selectableCount;
    }

    loadTemplate(event) {
        const params = event.params;

        if (this.hasNameTarget) {
            this.nameTarget.value = params.name || '';
        }

        if (this.hasSubjectTarget) {
            this.subjectTarget.value = params.subject || '';
        }

        if (this.hasHtmlTarget) {
            this.htmlTarget.value = params.html || '';
        }

        this.refreshPreview();
        this.htmlTarget?.focus();
    }

    closeRecipientsOnOutsideClick(event) {
        if (!this.hasRecipientPanelTarget || this.recipientPanelTarget.hidden) {
            return;
        }

        if (this.recipientPanelTarget.contains(event.target) || this.recipientToggleTarget.contains(event.target)) {
            return;
        }

        this.recipientPanelTarget.hidden = true;
        this.recipientToggleTarget.classList.remove('is-open');
    }

    buildManualEmailChip(email) {
        const chip = document.createElement('span');
        chip.className = 'admin-emailing-chip';
        chip.dataset.email = email;

        const label = document.createElement('span');
        label.textContent = email;

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.setAttribute('aria-label', `Retirer ${email}`);
        removeButton.dataset.action = 'admin-emailing#removeManualEmail';

        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-xmark';
        removeButton.appendChild(icon);

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'manual_emails[]';
        hiddenInput.value = email;

        chip.append(label, removeButton, hiddenInput);

        return chip;
    }

    manualEmails() {
        if (!this.hasManualListTarget) {
            return [];
        }

        return Array.from(this.manualListTarget.querySelectorAll('input[name="manual_emails[]"]'))
            .map((input) => input.value.trim().toLowerCase())
            .filter((email) => email !== '');
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    emptyPreview() {
        return `
            <div style="min-height:100vh;display:grid;place-items:center;background:#f5f7fb;color:#667085;font-family:Arial,sans-serif;">
                <div style="text-align:center;">
                    <div style="font-size:32px;margin-bottom:10px;">ULTRAPOP</div>
                    <p style="margin:0;">Colle ton code HTML pour voir la preview.</p>
                </div>
            </div>
        `;
    }
}
