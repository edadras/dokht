/**
 * ویرایشگر شناسنامه پارچه.
 *
 * لغزنده‌ها به مقادیر شناسنامه وصل می‌شوند، برچسب فارسی هر مقدار زنده به‌روز
 * می‌شود و یک پیش‌نمای کوچک از ریزش پارچه (بر پایه لختی و سفتی) رسم می‌گردد.
 */

const DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

const fa = (value) => String(value).replace(/[0-9]/g, (d) => DIGITS[+d]);

// پله‌های برچسب‌گذاری: [آستانه, برچسب] از بزرگ به کوچک
const SCALES = {
    drape: [
        [0.8, 'خیلی لخت'],
        [0.6, 'لخت'],
        [0.4, 'متوسط'],
        [0.2, 'کم‌لختی'],
        [0, 'بسیار سفت و ایستا'],
    ],
    stiffness: [
        [0.8, 'بسیار سفت'],
        [0.6, 'سفت'],
        [0.4, 'متوسط'],
        [0.2, 'نرم'],
        [0, 'بسیار نرم'],
    ],
    weight_gsm: [
        [350, 'بسیار سنگین'],
        [250, 'سنگین'],
        [160, 'متوسط'],
        [100, 'سبک'],
        [0, 'بسیار سبک'],
    ],
    thickness_mm: [
        [2, 'بسیار ضخیم'],
        [1, 'ضخیم'],
        [0.45, 'متوسط'],
        [0.25, 'نازک'],
        [0, 'بسیار نازک'],
    ],
    generic: [
        [0.8, 'خیلی زیاد'],
        [0.6, 'زیاد'],
        [0.4, 'متوسط'],
        [0.2, 'کم'],
        [0, 'خیلی کم'],
    ],
};

const pick = (scale, value) => (scale.find(([threshold]) => value >= threshold) || scale[scale.length - 1])[1];

export default (config = {}) => ({
    schema: config.schema || {},
    types: config.types || {},
    base: { ...(config.base || {}) },
    profile: { ...(config.base || {}), ...(config.profile || {}) },
    typeId: config.typeId ? String(config.typeId) : '',

    init() {
        // مقادیر عددی که از HTML می‌آیند رشته‌اند؛ یک بار به عدد تبدیل می‌شوند
        Object.keys(this.schema).forEach((key) => {
            const type = this.schema[key].type;

            if (type === 'ratio' || type === 'percent' || type === 'number') {
                this.profile[key] = Number(this.profile[key] ?? 0);
                this.base[key] = Number(this.base[key] ?? 0);
            }

            if (type === 'bool') {
                this.profile[key] = Boolean(Number(this.profile[key]) || this.profile[key] === true);
                this.base[key] = Boolean(Number(this.base[key]) || this.base[key] === true);
            }
        });
    },

    /** با تغییر پارچه پایه، همه مقادیر از بانک پارچه دوباره خوانده می‌شود. */
    applyType(id) {
        this.typeId = String(id || '');

        const type = this.types[this.typeId];

        if (!type) {
            return;
        }

        this.base = { ...type.profile };
        this.profile = { ...type.profile };
    },

    get typeName() {
        return this.types[this.typeId]?.name || 'پارچه پایه';
    },

    value(key) {
        return this.profile[key];
    },

    /** برچسب فارسی مقدار جاری یک ویژگی. */
    valueLabel(key) {
        const definition = this.schema[key] || {};
        const raw = this.profile[key];

        if (definition.type === 'bool') {
            return raw ? 'بله' : 'خیر';
        }

        if (definition.type === 'option') {
            return (definition.options || {})[raw] || String(raw ?? '');
        }

        const numeric = Number(raw ?? 0);

        if (definition.type === 'percent') {
            return `${fa(Math.round(numeric))}٪`;
        }

        if (definition.type === 'number') {
            const scale = SCALES[key];
            const shown = fa(Number.isInteger(numeric) ? numeric : numeric.toFixed(2));

            return scale ? `${shown} — ${pick(scale, numeric)}` : shown;
        }

        return `${fa(Math.round(numeric * 100))}٪ — ${pick(SCALES[key] || SCALES.generic, numeric)}`;
    },

    /** آیا این ویژگی با پارچه پایه تفاوت دارد؟ */
    isOverridden(key) {
        const definition = this.schema[key] || {};

        if (definition.type === 'bool') {
            return Boolean(this.profile[key]) !== Boolean(this.base[key]);
        }

        if (definition.type === 'option') {
            return String(this.profile[key]) !== String(this.base[key]);
        }

        return Math.abs(Number(this.profile[key] ?? 0) - Number(this.base[key] ?? 0)) > 1e-6;
    },

    get overriddenCount() {
        return Object.keys(this.schema).filter((key) => this.isOverridden(key)).length;
    },

    reset(key) {
        this.profile[key] = this.base[key];
    },

    resetAll() {
        this.profile = { ...this.base };
    },

    /** مسیر SVG یک تکه پارچه آویزان؛ خمش از لختی و پهن‌شدگی از سفتی می‌آید. */
    drapePath() {
        const drape = Math.max(0, Math.min(1, Number(this.profile.drape ?? 0.5)));
        const stiffness = Math.max(0, Math.min(1, Number(this.profile.stiffness ?? 0.4)));

        const top = 10;
        const bottom = 104;
        const spread = 6 + stiffness * 26;
        const left = 42 - spread;
        const right = 78 + spread;
        const pull = 18 + drape * 46;
        const sag = 4 + drape * 14;
        const middle = (left + right) / 2;

        return [
            `M 42 ${top}`,
            `C 42 ${top + pull}, ${left} ${bottom - pull}, ${left} ${bottom}`,
            `Q ${middle} ${bottom + sag}, ${right} ${bottom}`,
            `C ${right} ${bottom - pull}, 78 ${top + pull}, 78 ${top}`,
            'Z',
        ].join(' ');
    },

    /** خط راهنمای داخلی برای نشان دادن چین پارچه. */
    foldPath() {
        const drape = Math.max(0, Math.min(1, Number(this.profile.drape ?? 0.5)));
        const bend = 60 - drape * 14;

        return `M 60 12 C ${bend} 46, ${bend} 74, 60 102`;
    },

    get drapeLabel() {
        return pick(SCALES.drape, Number(this.profile.drape ?? 0.5));
    },

    get weightLabel() {
        return pick(SCALES.weight_gsm, Number(this.profile.weight_gsm ?? 150));
    },
});
