import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'editor', 'editButton', 'cancelButton'];

    connect() {
        this.showCard = this.hasCardTarget && !this.editorTarget.querySelector('.form-error-message, ul');
        this.render();
    }

    edit() {
        this.showCard = false;
        this.render();

        requestAnimationFrame(() => {
            this.editorTarget.querySelector('input:not([type="hidden"])')?.focus();
        });
    }

    cancel() {
        this.element.reset();
        this.showCard = true;
        this.render();
    }

    render() {
        if (this.hasCardTarget) {
            this.cardTarget.hidden = !this.showCard;
        }

        this.editorTarget.hidden = this.showCard;

        if (this.hasEditButtonTarget) {
            this.editButtonTarget.setAttribute('aria-expanded', String(!this.showCard));
        }

        if (this.hasCancelButtonTarget) {
            this.cancelButtonTarget.hidden = !this.hasCardTarget;
        }
    }
}
