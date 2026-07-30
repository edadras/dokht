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
import { bodyColliders, makeBody } from './fixtures/payload.js';

const IDENTITY = new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]);

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
    const world = new ClothWorld({ fabric: payload.fabric, skin: 0.006 });

    drape.patches.forEach((entry) => world.addPatch(entry.patch));
    drape.seams.forEach((seam) => world.addSeam(seam));
    // بدن، با بازو و پا — بی این‌ها آستین چیزی برای نشستن ندارد
    world.setColliders(bodyColliders(Collider, body, payload.avatar));
    world.iterations = 6;

    // اول در بی‌وزنی دوخته می‌شود، بعد سرشانه گرفته و وزن برمی‌گردد
    const gravity = world.law.gravity;

    world.law.gravity = 0;
    world.presettle(drape.stats.presettle);

    const stitched = world.seamError();

    supportGarment(drape, { band: 0.08, strength: 1 });
    drape.patches.forEach((entry) => entry.patch.applyPins(IDENTITY));
    world.law.gravity = gravity;
    world.presettle(60);

    const settled = stretchOf(drape);
    const before = world.seamError();

    // همان کاری که نماگر پیش از نمایش می‌کند؛ اگر خراب کند، همین‌جا دیده شود
    weldSeams(drape);

    const welded = stretchOf(drape);
    const hinge = hingeOf(drape);
    const bare = bareOf(drape, body);
    const name = file.split('/').pop().replace('p-', '').replace('.json', '');

    console.log(
        `${name.padEnd(20)} چیدن: بدترین=${(gaps.worst * 100).toFixed(1)} میانگین=${(gaps.mean * 100).toFixed(1)}` +
            ` | کشش چیدن: ${placed.worst.toFixed(1)}× خراب=${placed.bad}` +
            ` | خطای درز: دوخت=${(stitched * 100).toFixed(1)} نهایی=${(before * 100).toFixed(1)}` +
            ` جوش=${(world.seamError() * 100).toFixed(1)}` +
            ` | کشش نهایی: ${settled.worst.toFixed(1)}× خراب=${settled.bad}/${settled.tris}` +
            ` | تیغه‌ای: نشستن=${settled.slivers} جوش=${welded.slivers}` +
            ` | لولا: میانگین=${hinge.mean.toFixed(2)} بدترین=${hinge.worst.toFixed(2)}` +
            ` | پوستِ لخت: ${bare.bare}/${bare.seen} بدترین=${(bare.worst * 100).toFixed(1)}` +
            ` | ناقرینگیِ چیدن: میانگین=${(mirror.mean * 100).toFixed(1)} بدترین=${(mirror.worst * 100).toFixed(1)} (${mirror.pairs} جفت)`,
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
