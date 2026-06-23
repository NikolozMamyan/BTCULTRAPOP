import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'login', 'register'];

    connect() {
        this.resizeHandler = () => this.updateHeight();
        window.addEventListener('resize', this.resizeHandler);

        this.show('login');
        this.heightTimer = window.setTimeout(() => this.updateHeight(), 150);

        if (document.fonts?.ready) {
            document.fonts.ready.then(() => {
                if (this.element.isConnected) {
                    this.updateHeight();
                }
            });
        }
    }

    disconnect() {
        window.removeEventListener('resize', this.resizeHandler);
        window.clearTimeout(this.heightTimer);
    }

    showRegister() {
        this.show('register');
    }

    showLogin() {
        this.show('login');
    }

    show(face) {
        const showRegister = face === 'register';

        this.cardTarget.classList.toggle('is-register', showRegister);
        this.loginTarget.toggleAttribute('inert', showRegister);
        this.loginTarget.setAttribute('aria-hidden', showRegister ? 'true' : 'false');
        this.registerTarget.toggleAttribute('inert', !showRegister);
        this.registerTarget.setAttribute('aria-hidden', showRegister ? 'false' : 'true');
        this.updateHeight(face);
    }

    updateHeight(face = this.cardTarget.classList.contains('is-register') ? 'register' : 'login') {
        requestAnimationFrame(() => {
            const activeFace = face === 'register' ? this.registerTarget : this.loginTarget;
            this.cardTarget.style.height = `${activeFace.scrollHeight}px`;
        });
    }
}
