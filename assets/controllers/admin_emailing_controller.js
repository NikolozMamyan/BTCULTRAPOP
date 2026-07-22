import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'name',
        'subject',
        'html',
        'preview',
        'previewSubject',
        'templateId',
        'template',
        'recipientToggle',
        'recipientPanel',
        'recipientSummary',
        'recipientCheckbox',
        'selectAll',
        'manualInput',
        'manualList',
        'selectedList',
        'visualCount',
    ];

    static values = {
        recipientEmpty: String,
        recipientCount: String,
        manualInvalid: String,
        previewVariables: Object,
        subjectEmpty: String,
        selectionEmpty: String,
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
        const variables = this.currentPreviewVariables();

        if (this.hasPreviewTarget && this.hasHtmlTarget) {
            const html = this.htmlTarget.value.trim();
            this.previewTarget.srcdoc = html
                ? this.renderVariables(html, variables, true)
                : this.emptyPreview();
        }

        if (this.hasPreviewSubjectTarget) {
            const subject = this.hasSubjectTarget ? this.subjectTarget.value.trim() : '';
            this.previewSubjectTarget.textContent = subject
                ? this.renderVariables(subject, variables, false)
                : this.subjectEmptyValue;
        }
    }

    toggleRecipients(event) {
        event.preventDefault();

        if (!this.hasRecipientPanelTarget) {
            return;
        }

        this.recipientPanelTarget.hidden = !this.recipientPanelTarget.hidden;
        this.recipientToggleTarget.classList.toggle('is-open', !this.recipientPanelTarget.hidden);
    }

    closeRecipients(event) {
        event?.preventDefault();
        this.hideRecipientPanel();
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
        this.commitManualEmail();
    }

    addManualEmail(event) {
        event?.preventDefault();
        this.commitManualEmail();
    }

    prepareSubmit(event) {
        if (!this.hasManualInputTarget || this.manualInputTarget.value.trim() === '') {
            return;
        }

        if (!this.commitManualEmail()) {
            event.preventDefault();
        }
    }

    removeManualEmail(event) {
        event.preventDefault();

        event.currentTarget.closest('.admin-emailing-chip')?.remove();
        this.updateRecipientSummary();
    }

    removeVisualRecipient(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const recipientId = button.dataset.recipientId;
        const externalEmail = button.dataset.externalEmail;

        if (recipientId) {
            const checkbox = this.recipientCheckboxTargets.find((item) => item.value === recipientId);

            if (checkbox) {
                checkbox.checked = false;
            }
        }

        if (externalEmail && this.hasManualListTarget) {
            Array.from(this.manualListTarget.querySelectorAll('.admin-emailing-chip')).forEach((chip) => {
                if (chip.dataset.email === externalEmail) {
                    chip.remove();
                }
            });
        }

        this.updateRecipientSummary();
    }

    updateRecipientSummary() {
        const customers = this.selectedCustomers();
        const recipients = new Map();

        customers.forEach((customer) => {
            recipients.set(customer.email.toLowerCase(), customer);
        });

        this.manualEmails().forEach((email) => {
            if (!recipients.has(email)) {
                recipients.set(email, {
                    type: 'external',
                    email,
                    name: email,
                    id: null,
                    variables: null,
                });
            }
        });

        const total = recipients.size;

        if (this.hasRecipientSummaryTarget) {
            this.recipientSummaryTarget.textContent = total > 0
                ? this.recipientCountValue.replace('__COUNT__', total.toString())
                : this.recipientEmptyValue;
        }

        if (this.hasVisualCountTarget) {
            this.visualCountTarget.textContent = total.toString();
        }

        this.renderSelectedRecipients(Array.from(recipients.values()));

        if (this.hasSelectAllTarget) {
            const checkedCount = this.recipientCheckboxTargets.filter((checkbox) => checkbox.checked).length;
            const selectableCount = this.recipientCheckboxTargets.length;
            this.selectAllTarget.checked = selectableCount > 0 && checkedCount === selectableCount;
            this.selectAllTarget.indeterminate = checkedCount > 0 && checkedCount < selectableCount;
        }

        this.refreshPreview();
    }

    loadTemplate(event) {
        const params = event.params;

        if (this.hasTemplateIdTarget) {
            this.templateIdTarget.value = params.id || '';
        }

        if (this.hasNameTarget) {
            this.nameTarget.value = params.name || '';
        }

        if (this.hasSubjectTarget) {
            this.subjectTarget.value = params.subject || '';
        }

        if (this.hasHtmlTarget) {
            this.htmlTarget.value = params.html || '';
        }

        this.templateTargets.forEach((template) => {
            const selected = template === event.currentTarget;
            template.classList.toggle('is-selected', selected);
            template.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });

        this.refreshPreview();
    }

    insertVariable(event) {
        if (!this.hasHtmlTarget) {
            return;
        }

        const variable = event.params.variable;

        if (!variable) {
            return;
        }

        const token = `{{ ${variable} }}`;
        const start = this.htmlTarget.selectionStart ?? this.htmlTarget.value.length;
        const end = this.htmlTarget.selectionEnd ?? start;

        this.htmlTarget.setRangeText(token, start, end, 'end');
        this.htmlTarget.focus();
        this.refreshPreview();
    }

    closeRecipientsOnOutsideClick(event) {
        if (!this.hasRecipientPanelTarget || this.recipientPanelTarget.hidden) {
            return;
        }

        if (this.recipientPanelTarget.contains(event.target) || this.recipientToggleTarget.contains(event.target)) {
            return;
        }

        this.hideRecipientPanel();
    }

    hideRecipientPanel() {
        if (this.hasRecipientPanelTarget) {
            this.recipientPanelTarget.hidden = true;
        }

        if (this.hasRecipientToggleTarget) {
            this.recipientToggleTarget.classList.remove('is-open');
        }
    }

    commitManualEmail() {
        if (!this.hasManualInputTarget || !this.hasManualListTarget) {
            return true;
        }

        const email = this.manualInputTarget.value.trim().toLowerCase();
        this.manualInputTarget.setCustomValidity('');

        if (email === '') {
            return true;
        }

        if (!this.isValidEmail(email)) {
            this.manualInputTarget.setCustomValidity(this.manualInvalidValue || 'Email invalide.');
            this.manualInputTarget.reportValidity();
            return false;
        }

        if (!this.manualEmails().includes(email)) {
            this.manualListTarget.appendChild(this.buildManualEmailChip(email));
        }

        this.manualInputTarget.value = '';
        this.updateRecipientSummary();

        return true;
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

    buildSelectionChip(recipient) {
        const chip = document.createElement('span');
        chip.className = `admin-emailing-selection-chip admin-emailing-selection-chip--${recipient.type}`;

        const icon = document.createElement('i');
        icon.className = recipient.type === 'customer' ? 'fa-solid fa-user' : 'fa-solid fa-envelope';

        const text = document.createElement('span');
        const name = document.createElement('strong');
        name.textContent = recipient.name;
        text.appendChild(name);

        if (recipient.type === 'customer' && recipient.name !== recipient.email) {
            const email = document.createElement('small');
            email.textContent = recipient.email;
            text.appendChild(email);
        }

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.dataset.action = 'admin-emailing#removeVisualRecipient';
        removeButton.setAttribute('aria-label', `Retirer ${recipient.email}`);

        if (recipient.id) {
            removeButton.dataset.recipientId = recipient.id;
        } else {
            removeButton.dataset.externalEmail = recipient.email;
        }

        const removeIcon = document.createElement('i');
        removeIcon.className = 'fa-solid fa-xmark';
        removeButton.appendChild(removeIcon);
        chip.append(icon, text, removeButton);

        return chip;
    }

    renderSelectedRecipients(recipients) {
        if (!this.hasSelectedListTarget) {
            return;
        }

        this.selectedListTarget.replaceChildren();

        if (recipients.length === 0) {
            const empty = document.createElement('small');
            empty.className = 'admin-emailing-selected-list__empty';
            empty.textContent = this.selectionEmptyValue;
            this.selectedListTarget.appendChild(empty);
            return;
        }

        recipients.forEach((recipient) => {
            this.selectedListTarget.appendChild(this.buildSelectionChip(recipient));
        });
    }

    selectedCustomers() {
        return this.recipientCheckboxTargets
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => ({
                type: 'customer',
                id: checkbox.value,
                name: checkbox.dataset.recipientName || checkbox.dataset.recipientEmail,
                email: (checkbox.dataset.recipientEmail || '').toLowerCase(),
                variables: this.parseVariables(checkbox.dataset.recipientVariables),
            }))
            .filter((recipient) => recipient.email !== '');
    }

    currentPreviewVariables() {
        const selectedCustomer = this.selectedCustomers()[0];

        if (selectedCustomer?.variables) {
            return selectedCustomer.variables;
        }

        const previewVariables = this.hasPreviewVariablesValue ? this.previewVariablesValue : {};
        const externalEmail = this.manualEmails()[0];

        if (!externalEmail) {
            return previewVariables;
        }

        const externalVariables = {
            'client.name': externalEmail,
            'client.firstName': 'Client',
            'client.email': externalEmail,
            'client.loyaltyPoints': '0',
            'client.locale': 'fr',
        };

        return Object.fromEntries(
            Object.keys(previewVariables).map((key) => [key, externalVariables[key] ?? '']),
        );
    }

    parseVariables(value) {
        try {
            return JSON.parse(value || '{}');
        } catch {
            return null;
        }
    }

    renderVariables(content, variables, escapeValues) {
        return content.replace(/\{\{\s*([^{}]+?)\s*\}\}/gu, (token, key) => {
            const variable = key.trim();

            if (!Object.prototype.hasOwnProperty.call(variables, variable)) {
                return token;
            }

            const value = String(variables[variable] ?? '');
            return escapeValues ? this.escapeHtml(value) : value;
        });
    }

    escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value;

        return element.innerHTML;
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
