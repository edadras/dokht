/*
 * ورقه‌های آزمایشی.
 *
 * برای سنجیدن خودِ حل‌کننده نه به بستهٔ سرور نیاز است نه به مانکن؛ یک ورقهٔ
 * پارچه‌ی ساده کافی است و وقتی آزمونی می‌شکند، دلیلش هم همان‌جا پیداست.
 */

import { hash } from '../../../resources/js/lib/cloth-solver.js';
import { triangulate } from '../../../resources/js/lib/pattern-drape.js';

/**
 * یک ورقهٔ تخت روی صفحه‌ی xy، با یک تلنگرِ ریزِ قطعی روی z.
 *
 * تلنگر لازم است چون ورقه‌ی کاملاً تخت در برابر نیروهای درون‌صفحه‌ای یک تعادلِ
 * ناپایدار است: بدون آن، ریاضی هیچ‌وقت تصمیم نمی‌گیرد به کدام سو تا بخورد.
 *
 * @param {number[][]} polygon چندضلعی به سانتی‌متر
 * @param {object} [options]
 * @returns {{flat: object, positions: Float32Array, grain: Float64Array}}
 */
export const flatSheet = (polygon, { target = 2, scale = 0.01, wobble = 1e-4 } = {}) => {
    const flat = triangulate(polygon, { target });
    const count = flat.positions.length / 2;
    const positions = new Float32Array(count * 3);
    const grain = new Float64Array(count * 2);

    for (let i = 0; i < count; i++) {
        const x = flat.positions[i * 2] * scale;
        const y = flat.positions[i * 2 + 1] * scale;

        positions[i * 3] = x;
        positions[i * 3 + 1] = -y;
        positions[i * 3 + 2] = (hash(i) - 0.5) * wobble;
        grain[i * 2] = x;
        grain[i * 2 + 1] = y;
    }

    return { flat, positions, grain };
};

/* بیشترین کششِ یال نسبت به طول استراحتش */
export const worstStretch = (patch) => {
    let worst = 0;

    for (const group of patch.groups) {
        for (let i = 0; i < group.rest.length; i++) {
            const a = group.a[i] * 3;
            const b = group.b[i] * 3;
            const length = Math.hypot(
                patch.positions[a] - patch.positions[b],
                patch.positions[a + 1] - patch.positions[b + 1],
                patch.positions[a + 2] - patch.positions[b + 2],
            );

            worst = Math.max(worst, length / group.rest[i]);
        }
    }

    return worst;
};

/* بیشترین فاصله از صفحه‌ی z = ۰ */
export const maxDepth = (patch) => {
    let worst = 0;

    for (let i = 0; i < patch.count; i++) {
        worst = Math.max(worst, Math.abs(patch.positions[i * 3 + 2]));
    }

    return worst;
};
