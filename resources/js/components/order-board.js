/**
 * تخته تولید سفارش‌ها.
 *
 * کارت‌ها با کشیدن و رها کردن میان ستون‌ها جابه‌جا می‌شوند: کارت فوراً منتقل
 * می‌شود (خوش‌بینانه) و اگر ذخیره روی سرور شکست بخورد سر جای خودش برمی‌گردد.
 * برای کاربر بی‌موس روی هر کارت یک فهرست وضعیت هم هست که همان درخواست را
 * به شکل فرم معمولی می‌فرستد.
 */

const toPersianDigits = (value) =>
    String(value ?? '').replace(/[0-9]/g, (digit) => String.fromCharCode(0x06f0 + Number(digit)));

export default (config = {}) => ({
    // نشانی به‌روزرسانی وضعیت؛ __ID__ با شناسه سفارش جایگزین می‌شود
    statusUrl: config.statusUrl || '/orders/__ID__/status',
    dragging: null,
    hovered: null,
    toast: '',
    toastTimer: null,

    /** آدرس PATCH یک سفارش. */
    url(orderId) {
        return this.statusUrl.replace('__ID__', String(orderId));
    },

    token() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    },

    onDragStart(event, orderId, fromStatus) {
        this.dragging = { id: String(orderId), from: fromStatus };

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(orderId));
        }
    },

    onDragEnd() {
        this.dragging = null;
        this.hovered = null;
    },

    onDragOver(event, status) {
        if (! this.dragging) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }

        this.hovered = status;
    },

    onDragLeave(status) {
        if (this.hovered === status) {
            this.hovered = null;
        }
    },

    async onDrop(event, status) {
        event.preventDefault();
        this.hovered = null;

        const drag = this.dragging;
        this.dragging = null;

        if (! drag || drag.from === status) {
            return;
        }

        const card = this.$root.querySelector(`[data-order-card="${drag.id}"]`);
        const target = this.$root.querySelector(`[data-drop-zone="${status}"]`);

        if (! card || ! target) {
            return;
        }

        const origin = card.parentElement;
        const originNext = card.nextElementSibling;

        // جابه‌جایی خوش‌بینانه: کاربر نتیجه را بی‌درنگ می‌بیند
        target.appendChild(card);
        card.dataset.status = status;
        this.syncCard(card, status);
        this.refreshCounts();

        try {
            const response = await fetch(this.url(drag.id), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.token(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ status }),
            });

            if (! response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
        } catch (error) {
            // برگرداندن کارت به جای اولش
            if (originNext) {
                origin.insertBefore(card, originNext);
            } else {
                origin.appendChild(card);
            }

            card.dataset.status = drag.from;
            this.syncCard(card, drag.from);
            this.refreshCounts();
            this.flash('جابه‌جایی ذخیره نشد؛ اتصال شبکه را بررسی کنید و دوباره امتحان کنید.');
        }
    },

    /** فهرست وضعیت روی کارت را با ستون تازه هم‌خوان می‌کند. */
    syncCard(card, status) {
        const select = card.querySelector('[data-status-select]');

        if (select) {
            select.value = status;
        }
    },

    /** شمارنده بالای هر ستون را تازه می‌کند. */
    refreshCounts() {
        this.$root.querySelectorAll('[data-drop-zone]').forEach((zone) => {
            const status = zone.dataset.dropZone;
            const count = zone.querySelectorAll('[data-order-card]').length;
            const badge = this.$root.querySelector(`[data-count-for="${status}"]`);

            if (badge) {
                badge.textContent = toPersianDigits(count);
            }

            const empty = zone.querySelector('[data-empty-column]');

            if (empty) {
                empty.style.display = count === 0 ? '' : 'none';
            }
        });
    },

    flash(message) {
        this.toast = message;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => {
            this.toast = '';
        }, 6000);
    },
});
