export function kingdomMapEditor() {
    return {
        canEdit: false,
        csrf: '',
        drag: null,

        init() {
            this.canEdit = this.$el.dataset.canEdit === '1';
            this.csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },

        startDrag(event) {
            if (! this.canEdit || event.button > 0) {
                return;
            }

            const pin = event.currentTarget;
            event.preventDefault();
            pin.setPointerCapture(event.pointerId);
            pin.classList.add('is-dragging');

            this.drag = {
                pin,
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                moved: false,
            };
        },

        moveDrag(event) {
            if (! this.drag || event.pointerId !== this.drag.pointerId) {
                return;
            }

            const surface = this.$refs.surface;
            if (! surface) {
                return;
            }

            const rect = surface.getBoundingClientRect();
            if (rect.width < 1 || rect.height < 1) {
                return;
            }

            const x = this.clamp(((event.clientX - rect.left) / rect.width) * 100);
            const y = this.clamp(((event.clientY - rect.top) / rect.height) * 100);

            this.drag.pin.style.left = `${x}%`;
            this.drag.pin.style.top = `${y}%`;

            const distance = Math.hypot(event.clientX - this.drag.startX, event.clientY - this.drag.startY);
            if (distance > 6) {
                this.drag.moved = true;
            }
        },

        async endDrag(event) {
            if (! this.drag || event.pointerId !== this.drag.pointerId) {
                return;
            }

            const { pin, moved } = this.drag;
            pin.classList.remove('is-dragging');

            try {
                pin.releasePointerCapture(event.pointerId);
            } catch {
                // already released
            }

            this.drag = null;

            if (! moved) {
                const href = pin.dataset.href;
                if (href) {
                    window.location.href = href;
                }

                return;
            }

            const mapX = Math.round(this.clamp(parseFloat(pin.style.left)));
            const mapY = Math.round(this.clamp(parseFloat(pin.style.top)));
            pin.style.left = `${mapX}%`;
            pin.style.top = `${mapY}%`;

            try {
                const response = await fetch(pin.dataset.saveUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ map_x: mapX, map_y: mapY }),
                });

                if (! response.ok) {
                    return;
                }

                const data = await response.json();
                pin.style.left = `${data.map_x}%`;
                pin.style.top = `${data.map_y}%`;
            } catch {
                // keep the local position if the network fails
            }
        },

        clamp(value) {
            if (Number.isNaN(value)) {
                return 50;
            }

            return Math.min(95, Math.max(5, value));
        },
    };
}
