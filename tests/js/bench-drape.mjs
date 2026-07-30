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
import { ClothWorld } from '../../resources/js/lib/cloth-solver.js';
import { makeBody } from './fixtures/payload.js';

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
    const drape = buildDrape(payload.drape, makeBody(payload.avatar), {
        fabric: payload.fabric,
        warp: process.env.WARP !== '0',
    });

    const gaps = gapsOf(drape);
    const placed = stretchOf(drape);
    const world = new ClothWorld({ fabric: payload.fabric, skin: 0.006 });

    drape.patches.forEach((entry) => world.addPatch(entry.patch));
    drape.seams.forEach((seam) => world.addSeam(seam));
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
    const name = file.split('/').pop().replace('p-', '').replace('.json', '');

    console.log(
        `${name.padEnd(20)} چیدن: بدترین=${(gaps.worst * 100).toFixed(1)} میانگین=${(gaps.mean * 100).toFixed(1)}` +
            ` | کشش چیدن: ${placed.worst.toFixed(1)}× خراب=${placed.bad}` +
            ` | خطای درز: دوخت=${(stitched * 100).toFixed(1)} نهایی=${(before * 100).toFixed(1)}` +
            ` جوش=${(world.seamError() * 100).toFixed(1)}` +
            ` | کشش نهایی: ${settled.worst.toFixed(1)}× خراب=${settled.bad}/${settled.tris}` +
            ` | تیغه‌ای: نشستن=${settled.slivers} جوش=${welded.slivers}`,
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
