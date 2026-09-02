/*
 * گزارشِ فیت از دلِ شبیه‌سازی — چشمِ حلقهٔ اصلاحِ الگو.
 *
 * لباس با همان خطِ لولهٔ نماگر دوخته می‌شود و بعد، در چهار ناحیه‌ای که آزادیِ
 * الگو تعریف می‌شود (سینه، کمر، باسن، بازو)، دو چیز اندازه گرفته می‌شود:
 *
 *   کشش   نسبتِ طولِ یال‌ها به طولشان روی الگوی تخت، در نوارِ همان تراز.
 *          عددِ گزارش‌شده دهکِ نهم است: «سفت‌ترین جای این ناحیه چقدر زیرِ
 *          فشار است؟» — میانگین دردی را که فقط یک سمت دارد پنهان می‌کند.
 *   آزادی فاصلهٔ پارچه تا پوست (سانتی‌متر)، دهکِ یکم: «تنگ‌ترین جا چقدر جا
 *          دارد؟»
 *
 * خروجی JSON روی stdout است تا فرمانِ drape:fit بخواندش:
 *
 *   node tests/js/fit-report.mjs storage/app/drape-bench/p-shirt_classic.json
 */

import { readFileSync } from 'node:fs';
import { buildDrape, supportGarment, weldSeams } from '../../resources/js/lib/pattern-drape.js';
import { ClothWorld, Collider, clearanceAt } from '../../resources/js/lib/cloth-solver.js';
import { finishGarment, heightsOf, sewAnchored } from '../../resources/js/components/garment-solid.js';
import { bodyColliders, makeBody, rawBody } from './fixtures/payload.js';

const IDENTITY = new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]);
const SETTLE_PASSES = 12;

const payload = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const body = makeBody(payload.avatar);
const drape = buildDrape(payload.drape, body, { fabric: payload.fabric, warp: true });
const world = new ClothWorld({ fabric: payload.fabric, skin: 0.006, floor: 0 });

drape.patches.forEach((entry) => world.addPatch(entry.patch));
drape.seams.forEach((seam) => world.addSeam(seam));
world.setColliders(bodyColliders(Collider, rawBody(payload.avatar), body, 1));
world.substeps = drape.stats.solver.substeps;
world.iterations = Math.max(6, drape.stats.solver.iterations);

const gravity = world.law.gravity;

world.law.gravity = 0;

const placed = heightsOf(drape);

/* دوختِ بی‌وزنِ لنگردار؛ ببینید sewAnchored در garment-solid.js */
sewAnchored(world, drape, placed, body.level.armhole, Math.max(240, drape.stats.presettle));

supportGarment(drape, { band: 0.08, strength: 1 });
drape.patches.forEach((entry) => entry.patch.applyPins(IDENTITY));
world.law.gravity = gravity;
world.presettle(40);
world.iterations = Math.max(SETTLE_PASSES, drape.stats.solver.iterations);
world.seamPasses = drape.stats.solver.seamPasses ?? world.seamPasses;
world.presettle(300);
world.enableContact();
world.presettle(150);
finishGarment(world, drape, weldSeams);

/* کششِ هر رأس، همان محاسبهٔ نقشهٔ فیتِ نماگر */
const strainOf = (patch) => {
    const sum = new Float64Array(patch.count);
    const seen = new Uint16Array(patch.count);

    for (const group of patch.groups) {
        for (let i = 0; i < group.rest.length; i++) {
            const a = group.a[i];
            const b = group.b[i];
            const rest = group.rest[i];

            if (rest < 1e-6) {
                continue;
            }

            const now = Math.hypot(
                patch.positions[a * 3] - patch.positions[b * 3],
                patch.positions[a * 3 + 1] - patch.positions[b * 3 + 1],
                patch.positions[a * 3 + 2] - patch.positions[b * 3 + 2],
            ) / rest;

            sum[a] += now;
            sum[b] += now;
            seen[a]++;
            seen[b]++;
        }
    }

    const out = new Float32Array(patch.count);

    for (let v = 0; v < patch.count; v++) {
        out[v] = seen[v] ? sum[v] / seen[v] : 1;
    }

    return out;
};

const strains = drape.patches.map((entry) => strainOf(entry.patch));

/* نوارِ هر ناحیه: ±۳ سانتی‌مترِ ترازِ خودش */
const BAND = 0.03;
const zones = {
    bust: { level: body.level.bust, arm: false },
    waist: { level: body.level.waist, arm: false },
    hip: { level: body.level.hip, arm: false },
    // بازو: ده سانتی‌متر زیرِ مفصل، جایی که دورِ بازو اندازه گرفته می‌شود
    bicep: { level: (body.armTop ?? body.level.shoulder) - 0.10, arm: true },
};

const quantile = (list, share) => {
    if (! list.length) {
        return null;
    }

    const sorted = [...list].sort((a, b) => a - b);

    return sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * share))];
};

/*
 * دورِ پوشیدهٔ لباس در یک تراز — همان چیزی که خیاط با متر دورِ تنِ پوشیده
 * می‌گیرد.
 *
 * کشش برای این کار سنجهٔ خوبی نیست: چند مثلثِ تیغه‌ایِ کنارِ درز همیشه
 * کشیده‌اند و دهکِ نهم را در ۱٫۰۷ نگه می‌دارند، چه لباس تنگ باشد چه گشاد —
 * اندازه گرفته شد: پیراهن با آزادیِ صفر ۱٫۰۶۷ می‌داد و با ۱۴ سانتی‌متر آزادی
 * ۱٫۰۸۱. عددی که واقعاً به آزادیِ الگو جواب می‌دهد، دورِ پوششِ بیرونیِ
 * پارچه است: در هر زاویه دورِ محور، دورترین رأسِ پارچه، و محیطِ همان
 * چندضلعی. چینِ پارچه آن را بزرگ نمی‌کند؟ می‌کند — ولی چین همان چیزی است که
 * تن‌خور را گشاد می‌کند، پس باید هم شمرده شود.
 */
const WEDGES = 48;

const girthOf = (level, arm, side) => {
    const spokes = new Float64Array(WEDGES).fill(-1);
    let cx = 0;

    if (arm) {
        cx = side * (body.armOffset ?? 0.18);
    }

    for (const entry of drape.patches) {
        const patch = entry.patch;
        const sleeve = (entry.piece?.placement?.zone || '') === 'sleeve';

        if (arm !== sleeve) {
            continue;
        }

        for (let v = 0; v < patch.count; v++) {
            const x = patch.positions[v * 3];
            const y = patch.positions[v * 3 + 1];
            const z = patch.positions[v * 3 + 2];

            if (Math.abs(y - level) > BAND || (arm && Math.sign(x) !== Math.sign(side))) {
                continue;
            }

            const reach = Math.hypot(x - cx, z);
            const bin = Math.floor(((Math.atan2(x - cx, z) + Math.PI) / (2 * Math.PI)) * WEDGES) % WEDGES;

            spokes[bin] = Math.max(spokes[bin], reach);
        }
    }

    const filled = spokes.filter((reach) => reach > 0).length;

    // حلقهٔ نیمه‌کاره دورِ واقعی نیست؛ گزارشش گمراه می‌کند
    if (filled < WEDGES * 0.7) {
        return null;
    }

    // گوه‌های خالی از همسایه پر می‌شوند تا محیط پاره نشود
    for (let i = 0; i < WEDGES; i++) {
        if (spokes[i] < 0) {
            for (let step = 1; step < WEDGES; step++) {
                const near = spokes[(i + step) % WEDGES] > 0
                    ? spokes[(i + step) % WEDGES]
                    : spokes[(i - step + WEDGES * 2) % WEDGES];

                if (near > 0) {
                    spokes[i] = near;
                    break;
                }
            }
        }
    }

    let girth = 0;

    for (let i = 0; i < WEDGES; i++) {
        const a = ((i / WEDGES) * 2 * Math.PI) - Math.PI;
        const b = (((i + 1) % WEDGES / WEDGES) * 2 * Math.PI) - Math.PI;
        const j = (i + 1) % WEDGES;

        girth += Math.hypot(
            (spokes[i] * Math.sin(a)) - (spokes[j] * Math.sin(b)),
            (spokes[i] * Math.cos(a)) - (spokes[j] * Math.cos(b)),
        );
    }

    return girth * 100;
};

const report = {};

for (const [name, zone] of Object.entries(zones)) {
    const strain = [];

    drape.patches.forEach((entry, index) => {
        const patch = entry.patch;
        const sleeve = (entry.piece?.placement?.zone || '') === 'sleeve';

        if (zone.arm !== sleeve) {
            return;
        }

        for (let v = 0; v < patch.count; v++) {
            if (Math.abs(patch.positions[v * 3 + 1] - zone.level) > BAND) {
                continue;
            }

            strain.push(strains[index][v]);
        }
    });

    let girth = null;

    if (zone.arm) {
        const left = girthOf(zone.level, true, -1);
        const right = girthOf(zone.level, true, 1);

        girth = left !== null && right !== null ? (left + right) / 2 : (left ?? right);
    } else {
        girth = girthOf(zone.level, false, 0);
    }

    const around = (payload.avatar || {})[name];

    report[name] = girth === null || strain.length < 8 ? null : {
        // دورِ پوشیده و آزادیِ پوشیده: دورِ لباس منهای دورِ تن (سانتی‌متر)
        girth: Number(girth.toFixed(1)),
        worn: typeof around === 'number' ? Number((girth - around).toFixed(1)) : null,
        // دهکِ نهمِ کشش، فقط برای گزارش
        strain: Number(quantile(strain, 0.9).toFixed(3)),
        samples: strain.length,
    };
}

console.log(JSON.stringify({ zones: report, seamError: Number((world.seamError() * 100).toFixed(2)) }));
