import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'login', 'register'];

    connect() {
        this.resizeHandler = () => this.updateHeight();
        window.addEventListener('resize', this.resizeHandler);
        this.updateHeight();
    }

    disconnect() {
        window.removeEventListener('resize', this.resizeHandler);
    }

    showRegister() {
        this.show('register');
    }

    showLogin() {
        this.show('login');
    }

    show(face) {
        this.cardTarget.classList.toggle('is-register', face === 'register');
        this.updateHeight(face);
    }

    updateHeight(face = this.cardTarget.classList.contains('is-register') ? 'register' : 'login') {
        requestAnimationFrame(() => {
            const activeFace = face === 'register' ? this.registerTarget : this.loginTarget;
            this.cardTarget.style.height = `${activeFace.scrollHeight}px`;
        });
    }
}
