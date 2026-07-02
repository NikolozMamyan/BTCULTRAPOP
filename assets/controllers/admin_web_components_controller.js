import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    submitOnFileSelect(event) {
        const input = event.currentTarget;

        if (!input.files || input.files.length === 0 || !input.form) {
            return;
        }

        input.form.requestSubmit();
    }
}
