/*
 * سنجهٔ دوختِ سه‌بعدی — چهار عدد به‌جای یک عکس.
 *
 * کیفیتِ دوخت را با چشم نمی‌شود قضاوت کرد: چند بار پیش آمد که تغییری روی یک
 * لباس بهتر و روی لباس دیگر بدتر جواب داد، و عکسِ همان یکی «درست» به‌نظر رسید.
 * این سنجه همان چهار چیزی را می‌دهد که اهمیت دارند:
 *
 *   ۱. فاصلهٔ دو سرِ هر درز در لحظهٔ چیدن — اگر بزرگ باشد، قید درز پارچه را از
 *      روی بدن می‌کشد و گره می‌زند.
 *   ۲. کششِ مثلث‌ها در همان لحظه — چیدنی که خودش پارچه را مچاله کند غلط است.
 *   ۳. خطای درز پس از نشستن — چقدر از دوخت واقعاً بسته شد.
 *   ۴. کششِ پایانی و شمار مثلث خراب — سلامتِ نهاییِ پارچه.
 *
 * روش کار:
 *   php artisan drape:bench      # نوشتن بسته‌ها
 *   npm run bench:drape          # سنجش
 *   WARP=0 npm run bench:drape   # مقایسه با حالتی که خم‌کردنِ قطعه خاموش است
 */

import { readFileSync, readdirSync } from 'node:fs';
import { buildDrape, supportGarment, weldSeams } from '../../resources/js/lib/pattern-drape.js';
import { ClothWorld, Collider } from '../../resources/js/lib/cloth-solver.js';
import { heightsOf, relax, sagOf, shift } from '../../resources/js/components/garment-solid.js';
import { bodyColliders, makeBody, rawBody } from './fixtures/payload.js';

const IDENTITY = new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]);

const SETTLE_PASSES = Number(process.env.SETTLE || 12);

/* کششِ هر مثلث نسبت به همان مثلث روی الگوی تخت */
const stretchOf = (drape) => {
    let worst = 1;
    let bad = 0;
    let tris = 0;
    let slivers = 0;

    for (const { mesh } of drape.patches) {
        const { positions, indices, grain } = mesh;

        for (let t = 0; t < indices.length; t += 3) {
            const a = indices[t];
            const b = indices[t + 1];
            const c = indices[t + 2];
            const flat = Math.abs(
                ((grain[b * 2] - grain[a * 2]) * (grain[c * 2 + 1] - grain[a * 2 + 1]) -
                    (grain[c * 2] - grain[a * 2]) * (grain[b * 2 + 1] - grain[a * 2 + 1])) / 2,
            );
            const ux = positions[b * 3] - positions[a * 3];
            const uy = positions[b * 3 + 1] - positions[a * 3 + 1];
            const uz = positions[b * 3 + 2] - positions[a * 3 + 2];
            const vx = positions[c * 3] - positions[a * 3];
            const vy = positions[c * 3 + 1] - positions[a * 3 + 1];
            const vz = positions[c * 3 + 2] - positions[a * 3 + 2];
            const now = Math.hypot(uy * vz - uz * vy, uz * vx - ux * vz, ux * vy - uy * vx) / 2;

            tris++;

            if (flat > 1e-9) {
                const ratio = now / flat;

                worst = Math.max(worst, ratio);

                if (ratio > 2.5 || ratio < 0.25) {
                    bad++;
                }
            }

            /*
             * مثلثِ تیغه‌ای: کشش را نشان نمی‌دهد چون مساحتش کوچک می‌شود، نه
             * بزرگ — ولی روی مانکن همان چیزی است که چشم «پارچهٔ پاره» می‌خواند.
             * نسبتِ بلندترین به کوتاه‌ترین ضلع، جدا شمرده می‌شود.
             */
            const ab = Math.hypot(ux, uy, uz);
            const ac = Math.hypot(vx, vy, vz);
            const bc = Math.hypot(
                positions[c * 3] - positions[b * 3],
                positions[c * 3 + 1] - positions[b * 3 + 1],
                positions[c * 3 + 2] - positions[b * 3 + 2],
            );

            if (Math.max(ab, ac, bc) / Math.max(1e-6, Math.min(ab, ac, bc)) > 12) {
                slivers++;
            }
        }
    }

    return { worst, bad, tris, slivers };
};

/*
 * بازیِ لولای درز: ۱ یعنی پارچه از آن‌سوی درز صاف ادامه دارد، صفر یعنی روی
 * خودش برگشته. شکافِ درز این را نشان نمی‌دهد — دو لبه می‌توانند دقیقاً روی هم
 * باشند و پارچه پشت‌به‌پشت تا خورده باشد؛ از بیرون «آستین وصل نیست» دیده می‌شود.
 */
const hingeOf = (drape) => {
    let worst = 1;
    let sum = 0;
    let count = 0;

    for (const seam of drape.seams) {
        if (! seam.hinge) {
            continue;
        }

        const pa = seam.a.positions;
        const pb = seam.b.positions;

        for (let i = 0; i < seam.hinges; i++) {
            const na = seam.hinge[i * 3];
            const nb = seam.hinge[i * 3 + 1];
            const goal = seam.hinge[i * 3 + 2];

            if (goal <= 0) {
                continue;
            }

            const ratio = Math.hypot(
                pa[na * 3] - pb[nb * 3],
                pa[na * 3 + 1] - pb[nb * 3 + 1],
                pa[na * 3 + 2] - pb[nb * 3 + 2],
            ) / goal;

            worst = Math.min(worst, ratio);
            sum += ratio;
            count++;
        }
    }

    return { worst, mean: count ? sum / count : 1 };
};

/*
 * پوستِ بی‌پوشش — سنجه‌ای که با چشمِ کاربر می‌خواند.
 *
 * چند بار شد که همهٔ عددها خوب بودند و کاربر گفت «آستین و یقه وصل نیست». علتش
 * این بود که هیچ‌کدام از سنجه‌ها *پوشش* را نمی‌سنجید: درز می‌توانست کامل بسته
 * باشد و پارچه سالم، ولی لباس یک‌وری بیفتد و شانه لخت بماند. این‌جا روی پوستِ
 * بدن نقطه می‌گذاریم و می‌پرسیم نزدیک‌ترین پارچه چقدر دور است.
 */
const bareOf = (drape, body) => {
    const points = [];

    for (const { patch } of drape.patches) {
        for (let v = 0; v < patch.count; v++) {
            points.push(patch.positions[v * 3], patch.positions[v * 3 + 1], patch.positions[v * 3 + 2]);
        }
    }

    const profile = body.profile.slice().sort((one, two) => one[0] - two[0]);
    const skinAt = (y) => {
        for (let i = 1; i < profile.length; i++) {
            if (y <= profile[i][0]) {
                const span = Math.max(1e-9, profile[i][0] - profile[i - 1][0]);
                const t = Math.max(0, (y - profile[i - 1][0]) / span);

                return [
                    profile[i - 1][1] + (profile[i][1] - profile[i - 1][1]) * t,
                    profile[i - 1][2] + (profile[i][2] - profile[i - 1][2]) * t,
                ];
            }
        }

        return [profile[profile.length - 1][1], profile[profile.length - 1][2]];
    };

    let worst = 0;
    let bare = 0;
    let seen = 0;

    for (const name of ['shoulder', 'armhole', 'bust', 'waist', 'hip']) {
        const y = body.level[name];
        const [rx, rz] = skinAt(y);
        let atLevel = 0;

        for (let i = 1; i < points.length; i += 3) {
            if (Math.abs(points[i] - y) < 0.05) {
                atLevel++;
            }
        }

        // ارتفاعی که لباس اصلاً به آن نمی‌رسد سنجیده نمی‌شود؛ دامن شانه ندارد
        if (atLevel < 10) {
            continue;
        }

        for (let k = 0; k < 48; k++) {
            const u = (k / 48) * Math.PI * 2;
            const x = Math.cos(u) * rx;
            const z = Math.sin(u) * rz;
            let close = Infinity;

            for (let i = 0; i < points.length; i += 3) {
                close = Math.min(close, Math.hypot(points[i] - x, points[i + 1] - y, points[i + 2] - z));
            }

            worst = Math.max(worst, close);
            seen++;

            if (close > 0.04) {
                bare++;
            }
        }
    }

    return { worst, bare, seen };
};

/*
 * پوستِ لختِ سرِ بازو — همان گوه‌ای که کاربر روی سرشانه می‌دید.
 *
 * bareOf فقط تنه را می‌سنجد و سرِ بازو در هیچ‌کدام از ترازهایش نیست، پس این
 * شکاف از دید همهٔ سنجه‌ها پنهان بود: فاصله تا نزدیک‌ترین مثلث روی خودِ تنه
 * همه‌جا زیر ۲٫۵ سانتی‌متر بود و باز هم در عکس پوست دیده می‌شد. آنچه لخت
 * می‌ماند سرشانهٔ گوشتی است — بالای بازو، بیرون از بیضیِ تنه.
 *
 * لباسِ بی‌آستین طبیعتاً این‌جا لخت است، پس فقط وقتی سنجیده می‌شود که آستینی
 * در کار باشد.
 */
const armCapOf = (drape, body, avatar = {}) => {
    const sleeves = drape.patches.filter(
        ({ id, piece }) => (piece?.placement?.zone || piece?.role) === 'sleeve' && ! /cuff|strap/.test(id),
    );

    if (sleeves.length === 0) {
        return { bare: 0, seen: 0, worst: 0, gap: 0 };
    }

    const points = [];

    for (const { patch } of drape.patches) {
        for (let v = 0; v < patch.count; v++) {
            points.push(patch.positions[v * 3], patch.positions[v * 3 + 1], patch.positions[v * 3 + 2]);
        }
    }

    /*
     * روی محورِ *مایلِ* بازو نمونه می‌گیریم، نه یک خطِ عمودی.
     *
     * مانکن بازوهایش را هشت درجه باز نگه می‌دارد (armLZ/armRZ در poseAngles،
     * برای همهٔ حالت‌ها از جمله ایستاده). سنجه بازو را عمودی می‌ساخت و همین یک
     * عدد را جا می‌انداخت: در مرورگر آستین هرچه پایین‌تر می‌رفت از بازو دورتر
     * می‌شد و در ساعد کنارش آویزان می‌ماند، ولی هر نُه عددِ سنجه سالم بود.
     * کاربر دیدش، سنجه نه.
     */
    /*
     * تا آن‌جا که آستین می‌رسد، نه تا مچ.
     *
     * آستینِ کوتاه پایین‌تر از دمش پارچه ندارد و لختیِ آن‌جا ایراد نیست. بی این
     * مرز، هر آستینِ کوتاهی «۲۱۳ از ۳۳۶ لخت» گزارش می‌شد و عدد بی‌معنی بود.
     */
    let reach = 0;

    for (const { patch } of sleeves) {
        for (let v = 0; v < patch.count; v++) {
            reach = Math.max(reach, body.level.shoulder - 0.035 - patch.positions[v * 3 + 1]);
        }
    }

    const armLength = Math.min((avatar.arm_length || 58) / 100, reach);
    const top = body.level.shoulder - 0.035;
    const tilt = body.armTilt ?? 0;
    const rows = body.armTable.slice().sort((one, two) => one[0] - two[0]);
    const thick = (along) => {
        const y = -along;

        for (let i = 1; i < rows.length; i++) {
            if (y <= rows[i][0]) {
                const span = Math.max(1e-9, rows[i][0] - rows[i - 1][0]);
                const t = (y - rows[i - 1][0]) / span;

                return rows[i - 1][1] + (rows[i][1] - rows[i - 1][1]) * t;
            }
        }

        return rows[rows.length - 1][1];
    };

    let bare = 0;
    let seen = 0;
    let worst = 0;
    let gap = 0;

    for (const side of [-1, 1]) {
        const middle = side * body.armOffset;

        for (let along = 0.02; along <= armLength - 0.04; along += 0.04) {
            const y = top - along * Math.cos(tilt);
            const axis = middle + side * along * Math.sin(tilt);
            const radius = thick(along);
            let off = 0;

            for (let k = 0; k < 12; k++) {
                const u = (k / 12) * Math.PI * 2;
                const x = axis + Math.cos(u) * radius;
                const z = Math.sin(u) * radius;
                let close = Infinity;

                for (let i = 0; i < points.length; i += 3) {
                    close = Math.min(close, Math.hypot(points[i] - x, points[i + 1] - y, points[i + 2] - z));
                }

                seen++;
                worst = Math.max(worst, close);

                if (close > 0.04) {
                    bare++;
                    off++;
                }
            }

            // پایین‌ترین جایی که بازو لخت است: نشانهٔ «آستین از بازو افتاده»
            if (off >= 6) {
                gap = Math.max(gap, along);
            }
        }
    }

    return { bare, seen, worst, gap };
};

/*
 * پوششِ آستین دورِ بازو، روی یک حلقهٔ نازک.
 *
 * نوارِ ارتفاعیِ ضخیم این را نمی‌سنجد: زاویه‌های چند ارتفاع روی هم جمع می‌شوند و
 * آستینِ نیم‌باز هم ۳۶۰ درجه نشان می‌دهد. یک بار همین اشتباه را کردم و عددِ ۳۶۰
 * گرفتم در حالی که مرورگر ۲۱۰ می‌داد و بازو در عکس لخت بود.
 */
const sleeveOf = (drape, body) => {
    /*
     * پوشش برای هر *آستین* شمرده می‌شود، نه هر قطعه.
     *
     * آستین دوتکه دو پنل دارد و هیچ‌کدام تنها تمامِ دور بازو را نمی‌پوشاند —
     * پنل بالا ۲۳۹ درجه و پنل زیر ۱۲۱. سنجهٔ قبلی هر قطعه را جدا می‌سنجید و
     * *کمینه* را می‌گرفت، پس آستینِ سالمِ دوتکه هم ۱۲۱ درجه گزارش می‌شد و وقتی
     * پنل‌ها باریک‌تر شدند اصلاً «قطعه‌ای پیدا نشد» داد. سبد را برای دو سوی بدن
     * جدا نگه می‌داریم و پنل‌های هر سو را روی هم می‌ریزیم.
     */
    const arms = new Map();

    for (const { id, piece, patch } of drape.patches) {
        if ((piece?.placement?.zone || piece?.role) !== 'sleeve' || /cuff|strap/.test(id)) {
            continue;
        }

        const side = piece.side === 'left' ? 'left' : 'right';
        const arm = arms.get(side) || { patches: [], top: -Infinity };

        arm.patches.push(patch);

        for (let v = 0; v < patch.count; v++) {
            arm.top = Math.max(arm.top, patch.positions[v * 3 + 1]);
        }

        arms.set(side, arm);
    }

    let worst = 360;
    let count = 0;
    let sag = 0;

    for (const [side, arm] of arms) {
        const middle = side === 'left' ? -body.armOffset : body.armOffset;

        /*
         * حلقه را آن‌جا می‌گذاریم که پارچه هست.
         *
         * ارتفاعِ ثابتِ «۱۲ سانتی‌متر زیر حلقهٔ آستین» فرض می‌کرد آستین سرِ جایش
         * است. آستینِ ترنچ‌کت ۵ سانتی‌متر از شانه سُر خورده بود و حلقه بالای
         * پارچه می‌افتاد، پس سنجه «قطعه‌ای پیدا نشد» می‌داد — بی‌خبری، نه قبولی.
         * حالا حلقه چهار سانتی‌متر زیر سرِ خودِ آستین است (و نه بالاتر از حلقهٔ
         * استاندارد)، و سُر خوردن جدا گزارش می‌شود.
         */
        const standard = body.level.armhole - 0.12;
        const ring = Math.min(standard, arm.top - 0.04);
        const bins = new Uint8Array(24);
        let seen = 0;

        for (const patch of arm.patches) {
            for (let v = 0; v < patch.count; v++) {
                if (Math.abs(patch.positions[v * 3 + 1] - ring) > 0.025) {
                    continue;
                }

                const u = Math.atan2(patch.positions[v * 3 + 2], patch.positions[v * 3] - middle);

                bins[Math.min(23, Math.floor((u + Math.PI) / (2 * Math.PI) * 24))] = 1;
                seen++;
            }
        }

        sag = Math.max(sag, body.level.armhole - arm.top);

        if (seen < 8) {
            continue;
        }

        worst = Math.min(worst, bins.reduce((sum, value) => sum + value, 0) / 24 * 360);
        count++;
    }

    return { worst: count ? worst : null, count, sag };
};

/*
 * قرینگی: قطعهٔ آینه‌شده باید آینهٔ جفتِ خودش باشد.
 *
 * لباس یک‌وری نشستن با هیچ‌کدام از سنجه‌های دیگر دیده نمی‌شد — درز بسته بود،
 * پارچه سالم، ولی یک آستین پایین‌تر از دیگری. این‌جا برای هر جفتِ آینه (همان کد،
 * یکی mirrored) فاصلهٔ هر رأس تا نزدیک‌ترین رأسِ آینه‌شدهٔ جفتش سنجیده می‌شود.
 */
const mirrorOf = (drape) => {
    const byCode = new Map();

    for (const entry of drape.patches) {
        const code = entry.id.split('#')[0];

        byCode.set(code, [...(byCode.get(code) || []), entry]);
    }

    let worst = 0;
    let sum = 0;
    let pairs = 0;

    for (const group of byCode.values()) {
        if (group.length !== 2) {
            continue;
        }

        const [one, two] = group;
        let inner = 0;

        for (let v = 0; v < one.patch.count; v++) {
            const x = -one.patch.positions[v * 3];
            const y = one.patch.positions[v * 3 + 1];
            const z = one.patch.positions[v * 3 + 2];
            let best = Infinity;

            for (let w = 0; w < two.patch.count; w++) {
                best = Math.min(best, Math.hypot(
                    two.patch.positions[w * 3] - x,
                    two.patch.positions[w * 3 + 1] - y,
                    two.patch.positions[w * 3 + 2] - z,
                ));
            }

            inner += best / one.patch.count;
        }

        worst = Math.max(worst, inner);
        sum += inner;
        pairs++;
    }

    return { worst, mean: pairs ? sum / pairs : 0, pairs };
};

/*
 * دروازهٔ کیفیت — تنها عددی که کاربر واقعاً می‌بیند.
 *
 * نماگر پیش از نشان دادنِ پارچه همین را می‌پرسد و اگر جواب «نه» باشد، به نمای
 * چرخشیِ قدیمی برمی‌گردد. بقیهٔ سنجه‌ها می‌گویند چقدر خوب است؛ این یکی می‌گوید
 * اصلاً دیده می‌شود یا نه. همان شرط‌های landedWell در garment-solid.js.
 */
const gateOf = (drape, body, seamError) => {
    if (drape.stats.checks.some((row) => row.measured !== undefined)) {
        return 'کمانِ درز با الگو نمی‌خواند';
    }

    if (! (seamError < 0.06)) {
        return 'درزها بسته نشدند';
    }

    let lowest = Infinity;
    let highest = -Infinity;

    for (const { patch } of drape.patches) {
        for (let v = 0; v < patch.count; v++) {
            const y = patch.positions[v * 3 + 1];

            if (y < lowest) lowest = y;
            if (y > highest) highest = y;
        }
    }

    if (lowest < -0.03) {
        return `قطعه‌ای زیر کف افتاد (${(lowest * 100).toFixed(0)})`;
    }

    if (highest > body.level.chin + 0.08) {
        return 'قطعه‌ای بالای سر رفت';
    }

    /*
     * لنگرِ «پایین‌تر نشست» جای *چیدنِ خودِ لباس* است، نه سرشانهٔ بدن.
     *
     * تا امروز این شرط سرشانه را می‌گرفت و برای بالاتنه درست بود؛ ولی دامن و
     * شلوار و شورت و پیش‌بند اصلاً به سرشانه نمی‌رسند. در نمونهٔ پهنِ کاتالوگ،
     * هر هفده مدلی که این پیام را می‌گرفتند پایین‌تنه بودند: لگینگ، جین،
     * دامنِ ترک، شورت، پیش‌بندِ آشپزی، ساق‌پوش. سنجه خرابی گزارش می‌کرد که
     * وجود نداشت — و همان یک شرطِ غلط، دامنِ کلوش را ماه‌ها «رد» نگه داشته بود.
     *
     * بالاترین ترازی که سرور برای قطعه‌ها گفته، همان جایی است که لباس باید
     * بماند؛ چه سرشانه باشد چه خط کمر.
     */
    let anchor = -Infinity;

    for (const { piece } of drape.patches) {
        const top = piece?.placement?.y_top;

        if (typeof top === 'number') {
            anchor = Math.max(anchor, top * body.level.top);
        }
    }

    if (anchor === -Infinity) {
        anchor = body.level.shoulder;
    }

    if (highest < anchor - 0.10) {
        return `از جای خودش پایین‌تر نشست (${((anchor - highest) * 100).toFixed(0)})`;
    }

    return null;
};

/* فاصلهٔ دو سرِ هر درز، پیش از هر شبیه‌سازی */
const gapsOf = (drape) => {
    let worst = 0;
    let sum = 0;

    for (const seam of drape.seams) {
        const pa = seam.a.positions;
        const pb = (seam.b || seam.a).positions;
        let total = 0;

        for (let i = 0; i < seam.count; i++) {
            const at = seam.pairs[i * 2] * 3;
            const to = seam.pairs[i * 2 + 1] * 3;

            total += Math.hypot(pa[at] - pb[to], pa[at + 1] - pb[to + 1], pa[at + 2] - pb[to + 2]);
        }

        const mean = total / Math.max(1, seam.count);

        sum += mean;
        worst = Math.max(worst, mean);
    }

    return { worst, mean: sum / Math.max(1, drape.seams.length) };
};

const bench = (file) => {
    const payload = JSON.parse(readFileSync(file, 'utf8'));
    const body = makeBody(payload.avatar);
    const drape = buildDrape(payload.drape, body, {
        fabric: payload.fabric,
        warp: process.env.WARP !== '0',
        ...(process.env.HINGE === undefined ? {} : { seamHinge: Number(process.env.HINGE) }),
    });

    const mirror = mirrorOf(drape);
    const gaps = gapsOf(drape);
    const placed = stretchOf(drape);
    const world = new ClothWorld({ fabric: payload.fabric, skin: 0.006, floor: 0 });

    drape.patches.forEach((entry) => world.addPatch(entry.patch));
    drape.seams.forEach((seam) => world.addSeam(seam));

    // بدن، با بازو و پا — بی این‌ها آستین چیزی برای نشستن ندارد
    const dress = (grow) => world.setColliders(bodyColliders(Collider, rawBody(payload.avatar), body, grow));

    dress(1);

    /*
     * همان تنظیمی که نماگر به کار می‌برد.
     *
     * تا امروز سنجه پیشنهادِ stats.solver را نادیده می‌گرفت و با زیرگامِ پیش‌فرض
     * کار می‌کرد؛ نماگر ولی برای مشِ سنگین زیرگام را کم می‌کرد. نتیجه‌اش این شد
     * که سنجه پوششِ آستین دورِ بازو را ۳۶۰ درجه می‌دید و مرورگر ۲۱۰ — و چند بار
     * روی عددِ سنجه تکیه کردم در حالی که کاربر خرابی را در عکس می‌دید. سنجه‌ای که
     * مثل نماگر رفتار نکند، دروغ می‌گوید.
     */
    world.substeps = drape.stats.solver.substeps;
    world.iterations = Math.max(6, drape.stats.solver.iterations);

    // اول در بی‌وزنی دوخته می‌شود، بعد سرشانه گرفته و وزن برمی‌گردد
    const gravity = world.law.gravity;

    world.law.gravity = 0;

    /*
     * همان ترتیبِ نماگر: دوخت، برداشتن‌و‌سرِ‌جا‌گذاشتن، تن کردن.
     *
     * دو عددِ SEWING_BODY و DRESS_GIVE در garment-solid.js هستند و این‌جا باید
     * مو‌به‌مو همان‌ها باشند. یک بار نبودند — سنجه بی بدن می‌دوخت و نماگر با
     * بدنِ کامل — و آن وقت دروازهٔ «بالای سر» این‌جا لبه‌ای می‌شد و عددی را رد
     * می‌کرد که در مرورگر سالم بود.
     */
    const SEWING_BODY = 1;
    const DRESS_GIVE = 1;

    const middleOf = () => {
        let x = 0;
        let z = 0;
        let count = 0;
        let top = -Infinity;

        for (const { patch } of drape.patches) {
            for (let v = 0; v < patch.count; v++) {
                x += patch.positions[v * 3];
                z += patch.positions[v * 3 + 2];
                top = Math.max(top, patch.positions[v * 3 + 1]);
                count++;
            }
        }

        return count ? { x: x / count, y: top, z: z / count } : { x: 0, y: 0, z: 0 };
    };

    dress(SEWING_BODY);

    const placedAt = middleOf();
    const placedYs = heightsOf(drape);

    world.presettle(Math.max(240, drape.stats.presettle));

    /* لباسِ دوخته‌شده دوباره روی شانه؛ ببینید sagOf در garment-solid.js */
    const sag = sagOf(drape, placedYs, body.level.armhole);

    if (sag < 0) {
        shift(drape, { x: 0, y: 0, z: 0 }, { x: 0, y: sag, z: 0 });
    }

    const stitched = world.seamError();

    /* لباسِ دوخته‌شده را برمی‌داریم و سرِ جایش می‌گذاریم — فقط اگر بی‌بدن دوخته باشیم */
    if (SEWING_BODY < 1) {
        const sewnAt = middleOf();

        for (const { patch } of drape.patches) {
            for (let v = 0; v < patch.count; v++) {
                patch.positions[v * 3] += placedAt.x - sewnAt.x;
                patch.positions[v * 3 + 1] += placedAt.y - sewnAt.y;
                patch.positions[v * 3 + 2] += placedAt.z - sewnAt.z;
            }

            patch.remember();
        }
    }

    /*
     * پارچه همان چند لحظه اجازهٔ کشش می‌گیرد، وگرنه کنار می‌رود به‌جای کشیدن.
     * و اگر بدن از اول کامل باشد، این مرحله فقط گامِ بی‌وزنیِ اضافه است و
     * پارچه در آن باد می‌کند — ببینید همین‌جا در garment-solid.js.
     */
    if (SEWING_BODY < 1) {
        world.allowStretch(DRESS_GIVE);

        for (let step = 1; step <= 12; step++) {
            dress(SEWING_BODY + (1 - SEWING_BODY) * (step / 12));
            world.presettle(14);
        }

        world.allowStretch(1);
        world.presettle(60);
    }

    supportGarment(drape, { band: 0.08, strength: 1 });
    drape.patches.forEach((entry) => entry.patch.applyPins(IDENTITY));
    world.law.gravity = gravity;
    world.presettle(40);
    world.iterations = Math.max(SETTLE_PASSES, drape.stats.solver.iterations);
    world.seamPasses = drape.stats.solver.seamPasses ?? world.seamPasses;
    world.presettle(300);

    const settled = stretchOf(drape);
    const before = world.seamError();

    /*
     * ترتیبِ پایان، مو‌به‌مو مثلِ نماگر: نشستن، بعد جوش، بعد صاف کردن.
     *
     * یک بار این‌جا اول جوش می‌زد و بعد می‌نشست، و همان جابه‌جایی باعث شد سنجه
     * برای کت و ترنچ‌کت «✓ روی مانکن» بدهد در حالی که مرورگر همان‌ها را با
     * «قطعه‌ای از لباس بالای سر رفت» رد می‌کرد و به نمای قدیمی برمی‌گشت.
     */
    world.presettle(150);
    weldSeams(drape);
    relax(drape);
    world.pushOutside();

    const snapped = world.seamError();

    /*
     * جوش، *قید* نیست — رأس‌ها را جابه‌جا می‌کند و بس.
     *
     * سنجه تا امروز همین‌جا اندازه می‌گرفت و ۰٫۱ سانتی‌متر می‌دید، ولی نماگر پس
     * از جوش هم شبیه‌سازی را ادامه می‌دهد: قیدِ درز نرم است و پارچه دوباره
     * می‌کشدش. اندازه گرفته شد — پیراهن پس از ۱۲۰ قدم به ۵٫۵ سانتی‌متر
     * برمی‌گشت، یعنی همان «یقه آزاد است»ی که روی مانکن دیده می‌شد. عددی که به
     * کار می‌آید، عددِ *ماندگار* است نه عددِ لحظهٔ جوش.
     */
    const durable = world.seamError();
    const welded = stretchOf(drape);
    const hinge = hingeOf(drape);
    const bare = bareOf(drape, body);
    const sleeve = sleeveOf(drape, body);
    const cap = armCapOf(drape, body, payload.avatar);
    const name = file.split('/').pop().replace('p-', '').replace('.json', '');
    const gate = gateOf(drape, body, durable);

    console.log(
        `${name.padEnd(20)} ${gate === null ? '✓ روی مانکن' : '✗ ' + gate}` +
            ` | چیدن: بدترین=${(gaps.worst * 100).toFixed(1)} میانگین=${(gaps.mean * 100).toFixed(1)}` +
            ` | کشش چیدن: ${placed.worst.toFixed(1)}× خراب=${placed.bad}` +
            ` | خطای درز: دوخت=${(stitched * 100).toFixed(1)} نهایی=${(before * 100).toFixed(1)}` +
            ` جوش=${(snapped * 100).toFixed(1)} ماندگار=${(durable * 100).toFixed(1)}` +
            ` | کشش نهایی: ${settled.worst.toFixed(1)}× خراب=${settled.bad}/${settled.tris}` +
            ` | تیغه‌ای: نشستن=${settled.slivers} جوش=${welded.slivers}` +
            ` | لولا: میانگین=${hinge.mean.toFixed(2)} بدترین=${hinge.worst.toFixed(2)}` +
            ` | پوستِ لخت: ${bare.bare}/${bare.seen} بدترین=${(bare.worst * 100).toFixed(1)}` +
            ` | ناقرینگیِ چیدن: میانگین=${(mirror.mean * 100).toFixed(1)} بدترین=${(mirror.worst * 100).toFixed(1)} (${mirror.pairs} جفت)` +
            (sleeve.worst === null ? '' : ` | آستین: پوشش=${sleeve.worst.toFixed(0)}° سُرخوردن=${(sleeve.sag * 100).toFixed(1)}`)
            + (cap.seen === 0 ? '' : ` | بازوی لخت: ${cap.bare}/${cap.seen} افتادگی=${(cap.gap * 100).toFixed(0)}`),
    );
};

const dir = process.env.BENCH_DIR || 'storage/app/drape-bench';
const files = process.argv.length > 2
    ? process.argv.slice(2)
    : readdirSync(dir).filter((name) => name.endsWith('.json')).map((name) => `${dir}/${name}`);

if (files.length === 0) {
    console.log('بسته‌ای نبود. اول: php artisan drape:bench');
}

for (const file of files) {
    bench(file);
}
