/**
 * فرم اندازه‌گیری.
 *
 * کار این مؤلفه ساده نگه داشتن اندازه‌گیری است: رقم‌های فارسی را لاتین می‌کند،
 * سایز استاندارد پیشنهادی را زنده حساب می‌کند، هشدارهای منطقی می‌دهد و می‌تواند
 * فرم را از یک سایز استاندارد یا از برگ پیشین همان مشتری پر کند.
 */

const PERSIAN_DIGITS = /[۰-۹٠-٩]/g;

const toLatinDigits = (value) =>
    String(value ?? '').replace(PERSIAN_DIGITS, (digit) => {
        const code = digit.charCodeAt(0);

        return String(code >= 0x06f0 ? code - 0x06f0 : code - 0x0660);
    });

const toPersianDigits = (value) =>
    String(value ?? '').replace(/[0-9]/g, (digit) => String.fromCharCode(0x06f0 + Number(digit)));

export default (config = {}) => ({
    fields: config.fields || {},
    essentials: config.essentials || [],
    sizeTable: config.sizeTable || {},
    values: { ...(config.values || {}) },
    previous: config.previous || null,
    pickedSize: '',
    notice: '',
    noticeTimer: null,

    init() {
        Object.keys(this.fields).forEach((key) => {
            if (this.values[key] === undefined || this.values[key] === null) {
                this.values[key] = '';
            }
        });

        Object.keys(this.values).forEach((key) => this.normalize(key));
    },

    /** رقم فارسی و نویسه‌های اضافی ورودی را تمیز می‌کند. */
    normalize(key) {
        const cleaned = toLatinDigits(this.values[key])
            .replace(/٫/g, '.')
            .replace(/[^0-9.]/g, '')
            .replace(/(\..*)\./g, '$1');

        if (cleaned !== this.values[key]) {
            this.values[key] = cleaned;
        }
    },

    number(key) {
        const parsed = parseFloat(this.values[key]);

        return Number.isFinite(parsed) ? parsed : null;
    },

    /** فقط اندازه‌هایی که کاربر واقعاً نوشته است. */
    get filled() {
        const out = {};

        Object.keys(this.fields).forEach((key) => {
            const value = this.number(key);

            if (value !== null) {
                out[key] = value;
            }
        });

        return out;
    },

    get essentialsDone() {
        return this.essentials.filter((key) => this.number(key) !== null).length;
    },

    get essentialsLabel() {
        return `${toPersianDigits(this.essentialsDone)} از ${toPersianDigits(this.essentials.length)}`;
    },

    get isReady() {
        return this.essentialsDone === this.essentials.length;
    },

    /** نزدیک‌ترین سایز استاندارد بر پایه دور سینه. */
    get suggestedSize() {
        const bust = this.number('bust');

        if (bust === null) {
            return null;
        }

        let best = null;
        let bestDiff = Infinity;

        Object.entries(this.sizeTable).forEach(([size, row]) => {
            const diff = Math.abs(Number(row.bust) - bust);

            if (diff < bestDiff) {
                bestDiff = diff;
                best = size;
            }
        });

        return best;
    },

    get suggestedSizeLabel() {
        return this.suggestedSize ? toPersianDigits(this.suggestedSize) : '—';
    },

    /** هشدارهای منطقی برای اندازه‌ای که احتمالاً اشتباه گرفته شده. */
    get warnings() {
        const messages = [];
        const value = this.filled;

        Object.entries(this.fields).forEach(([key, field]) => {
            const current = value[key];

            if (current === undefined) {
                return;
            }

            if (current < field.min || current > field.max) {
                messages.push(
                    `${field.label} باید بین ${toPersianDigits(field.min)} و ${toPersianDigits(field.max)} سانتی‌متر باشد.`,
                );
            }
        });

        if (value.bust && value.waist && value.waist > value.bust + 8) {
            messages.push('دور کمر از دور سینه بیشتر شده است؛ یک بار دیگر متر را ببینید.');
        }

        if (value.waist && value.hip && value.hip < value.waist - 4) {
            messages.push('دور باسن کمتر از دور کمر است؛ معمولاً جای متر جابه‌جا شده.');
        }

        if (value.height && value.arm_length && value.arm_length > value.height * 0.45) {
            messages.push('قد آستین نسبت به قد بلند به نظر می‌رسد.');
        }

        if (value.height && value.shoulder_width && value.shoulder_width > value.height * 0.32) {
            messages.push('عرض سرشانه نسبت به قد زیاد است؛ سرشانه را از استخوان تا استخوان بگیرید.');
        }

        return messages;
    },

    /** فرم را از یک سایز استاندارد پر می‌کند. */
    fillFromSize() {
        const row = this.sizeTable[this.pickedSize];

        if (!row) {
            return;
        }

        Object.entries(row).forEach(([key, value]) => {
            if (this.fields[key] !== undefined) {
                this.values[key] = String(value);
            }
        });

        this.flash(`اندازه‌های سایز ${toPersianDigits(this.pickedSize)} پر شد؛ هرکدام را لازم بود تغییر دهید.`);
    },

    /** اندازه‌های برگ پیشین همین مشتری را کپی می‌کند. */
    copyPrevious() {
        if (!this.previous) {
            return;
        }

        Object.entries(this.previous).forEach(([key, value]) => {
            if (this.fields[key] !== undefined) {
                this.values[key] = String(value);
            }
        });

        this.flash('اندازه‌های برگ پیشین کپی شد.');
    },

    clearAll() {
        Object.keys(this.values).forEach((key) => {
            this.values[key] = '';
        });

        this.flash('همه خانه‌ها خالی شد.');
    },

    flash(message) {
        this.notice = message;
        clearTimeout(this.noticeTimer);
        this.noticeTimer = setTimeout(() => {
            this.notice = '';
        }, 5000);
    },
});
