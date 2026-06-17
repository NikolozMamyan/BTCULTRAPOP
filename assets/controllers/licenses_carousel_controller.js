import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['viewport', 'group'];

    connect() {
        this.speed = 32;
        this.paused = false;
        this.pressed = false;
        this.dragging = false;
        this.suppressClick = false;
        this.position = this.hasViewportTarget ? this.viewportTarget.scrollLeft : 0;
        this.lastTimestamp = null;
        this.frame = requestAnimationFrame((timestamp) => this.animate(timestamp));
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        window.clearTimeout(this.resumeTimer);
    }

    pause() {
        this.paused = true;
    }

    resume() {
        if (this.dragging) {
            return;
        }

        this.paused = false;
        this.lastTimestamp = null;
    }

    startDrag(event) {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        window.clearTimeout(this.resumeTimer);
        this.pressed = true;
        this.dragging = false;
        this.suppressClick = false;
        this.dragStartX = event.clientX;
        this.dragStartScrollLeft = this.viewportTarget.scrollLeft;
        this.pause();
    }

    drag(event) {
        if (!this.pressed) {
            return;
        }

        const distance = event.clientX - this.dragStartX;

        if (Math.abs(distance) <= 5) {
            return;
        }

        if (!this.dragging) {
            this.dragging = true;
            this.suppressClick = true;

            if (!this.viewportTarget.hasPointerCapture(event.pointerId)) {
                this.viewportTarget.setPointerCapture(event.pointerId);
            }

            this.viewportTarget.classList.add('is-dragging');
        }

        event.preventDefault();
        this.position = this.dragStartScrollLeft - distance;
        this.viewportTarget.scrollLeft = this.position;
    }

    endDrag(event) {
        if (!this.pressed && !this.dragging) {
            return;
        }

        const wasDragging = this.dragging;
        this.pressed = false;
        this.dragging = false;
        this.viewportTarget.classList.remove('is-dragging');

        if (this.viewportTarget.hasPointerCapture(event.pointerId)) {
            this.viewportTarget.releasePointerCapture(event.pointerId);
        }

        this.resumeTimer = window.setTimeout(() => this.resume(), wasDragging ? 800 : 120);
    }

    preventClick(event) {
        if (!this.suppressClick) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        this.suppressClick = false;
    }

    animate(timestamp) {
        if (!this.paused && this.hasViewportTarget && this.hasGroupTarget) {
            if (this.lastTimestamp !== null) {
                const elapsed = Math.min(timestamp - this.lastTimestamp, 50);
                this.position += this.speed * elapsed / 1000;

                const groupWidth = this.groupTarget.getBoundingClientRect().width;
                if (groupWidth > 0 && this.position >= groupWidth) {
                    this.position -= groupWidth;
                }

                this.viewportTarget.scrollLeft = this.position;
            }

            this.lastTimestamp = timestamp;
        }

        this.frame = requestAnimationFrame((nextTimestamp) => this.animate(nextTimestamp));
    }
}
