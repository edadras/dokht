import { readFileSync, writeFileSync } from 'node:fs';
import { buildDrape, supportGarment, weldSeams } from '/app/resources/js/lib/pattern-drape.js';
import { ClothWorld, Collider } from '/app/resources/js/lib/cloth-solver.js';
import { finishGarment, heightsOf, sewAnchored, shoulderAnchors, bodyColliders, seamBand } from '/app/resources/js/components/garment-solid.js';
import { buildBody, drapeBody } from '/app/resources/js/lib/mannequin.js';

const input = process.argv[2];
const output = process.argv[3];
const payload = JSON.parse(readFileSync(input, 'utf8')).payload || {};

if (!payload.drape?.pieces?.length) {
    throw new Error('This pattern has no sew3d pieces.');
}

const raw = payload.avatar?.body || buildBody(payload.avatar || {});
const body = drapeBody(raw);
const drape = buildDrape(payload.drape, body, { fabric: payload.fabric || {}, warp: true });
const world = new ClothWorld({ fabric: payload.fabric || {}, skin: 0.006, floor: 0 });

drape.patches.forEach(({ patch }) => world.addPatch(patch));
drape.seams.forEach((seam) => world.addSeam(seam));

const dress = (grow) => world.setColliders(bodyColliders(Collider, raw, body, grow));
world.substeps = drape.stats.solver.substeps;
world.iterations = Math.max(6, drape.stats.solver.iterations);
dress(1);

const gravity = world.law.gravity;
world.law.gravity = 0;
const placed = heightsOf(drape);
sewAnchored(world, drape, placed, body.level.armhole, Math.max(240, drape.stats.presettle), 40, shoulderAnchors(drape, body, payload.drape));
supportGarment(drape, { band: 0.08, strength: 1 });
world.law.gravity = gravity;
world.presettle(40);
/*
 * روی سرور وقت هست: دو برابرِ مرورگر تکرار و گام، تا درزها بسته‌تر و پارچه
 * آرام‌تر بنشیند. SEW_FAST=1 همان تنظیمِ مرورگر را می‌دهد، برای آزمونِ سریع.
 */
const fast = process.env.SEW_FAST === '1';
world.iterations = Math.max(fast ? 12 : 24, drape.stats.solver.iterations);
world.seamPasses = drape.stats.solver.seamPasses ?? world.seamPasses;
world.presettle(fast ? 300 : 600);
world.enableContact();
world.presettle(fast ? 150 : 300);
// اتو: قیدِ خمش سفت می‌شود و چین‌های یخ‌زده باز می‌شوند؛ ببینید ClothWorld.iron
world.iron(0.9);
world.presettle(fast ? 100 : 200);
finishGarment(world, drape, weldSeams);

const meshes = drape.patches
    .filter(({ piece }) => (piece?.layer || 'outer') === 'outer')
    .map(({ id, piece, patch, mesh }) => ({
        id,
        name: piece?.name || id,
        role: piece?.role || 'detail',
        positions: Array.from(patch.positions),
        indices: Array.from(mesh.indices),
    }));

/*
 * نوارِ خودِ درزها هم صادر می‌شود — همان که مرورگر می‌کشد. درزِ بسته‌شده چند
 * میلی‌متر باز می‌ماند و بی‌این نوار، در رندر مثلِ پارگیِ سرشانه دیده می‌شد.
 */
const band = seamBand(drape);

/*
 * جوشِ درزها برای رندر: هر سوزنِ زنده یک جفت رأس (قطعه، رأس) ↔ (قطعه، رأس).
 *
 * رندر هر قطعه را مشِ جدا می‌گرفت و هموارسازی لبهٔ آزادِ هر مش را تو می‌کشید؛
 * درزِ بسته در عکس نواری روشن می‌شد. جوشِ فاصله‌ای (remove_doubles) هم جواب
 * نبود: لایه‌های پارچه‌ای که روی هم خوابیده‌اند به هم می‌چسبیدند و سوراخ
 * می‌ساختند (شورت، پهلو و پشت). پس دقیقاً همان رأس‌هایی که به هم دوخته‌اند جوش
 * می‌خورند، نه بیشتر.
 */
const meshIndex = new Map();

meshes.forEach((entry, index) => meshIndex.set(entry.id, index));

const welds = [];

for (const seam of drape.seams) {
    if (seam.kind === 'crease' || ! seam.a) {
        continue;
    }

    const other = seam.b || seam.a;
    const ia = meshIndex.get(drape.patches.find((entry) => entry.patch === seam.a)?.id);
    const ib = meshIndex.get(drape.patches.find((entry) => entry.patch === other)?.id);

    if (ia === undefined || ib === undefined) {
        continue;
    }

    const pa = seam.a.positions;
    const pb = other.positions;

    for (let i = 0; i < seam.count; i++) {
        const a = seam.pairs[i * 2] * 3;
        const b = seam.pairs[i * 2 + 1] * 3;
        const gap = Math.hypot(pa[a] - pb[b], pa[a + 1] - pb[b + 1], pa[a + 2] - pb[b + 2]);

        // سوزنِ خاموش (ناسازگار) فقط اگر خودش نزدیک افتاده جوش می‌خورد؛ درزِ واقعاً باز باز می‌ماند
        if ((seam.dead && seam.dead[i] && gap > 0.012) || gap > 0.03) {
            continue;
        }

        welds.push(ia, seam.pairs[i * 2], ib, seam.pairs[i * 2 + 1]);
    }
}

/*
 * دکمه‌ها: روی بستِ جلو، و یکی روی کمربند.
 *
 * بستِ جلو در حل‌کننده یک درز است و در عکس چیزی نشان نمی‌دهد؛ کاربر لباسِ
 * بسته را «رها روی مانکن» می‌دید. هر نُه سانتی‌متر از سرِ بست یک دکمه، و روی
 * کمربندِ شلوار و دامن یکی در مرکزِ جلو. جای هر دکمه وسطِ جفت‌رأسِ درز است و
 * جهتش رو به بیرونِ تن (از محورِ تن)؛ رندر قرصی همان‌جا می‌گذارد.
 */
const buttons = [];
const outward = (x, y, z) => {
    const cz = body.profile ? (body.profile.find((row) => row[0] >= y) || body.profile[body.profile.length - 1])[5] || 0 : 0;
    const len = Math.hypot(x, z - cz) || 1;

    return [x / len, 0, (z - cz) / len];
};

for (const seam of drape.seams) {
    if (! /بستن مرکز جلو/.test(seam.label || '') || ! seam.b) {
        continue;
    }

    const pa = seam.a.positions;
    const pb = seam.b.positions;
    const spots = [];

    for (let i = 0; i < seam.count; i++) {
        if (seam.dead && seam.dead[i]) continue;
        const a = seam.pairs[i * 2] * 3;
        const b = seam.pairs[i * 2 + 1] * 3;
        spots.push([(pa[a] + pb[b]) / 2, (pa[a + 1] + pb[b + 1]) / 2, (pa[a + 2] + pb[b + 2]) / 2]);
    }

    spots.sort((one, two) => two[1] - one[1]);

    let lastY = Infinity;

    for (const [x, y, z] of spots) {
        if (y > lastY - 0.09) continue;
        // اولین دکمه دو سانتی‌متر زیرِ سرِ بست
        if (lastY === Infinity && spots.length > 1 && y > spots[0][1] - 0.02) continue;
        lastY = y;
        buttons.push({ at: [x, y, z], normal: outward(x, y, z) });
    }
}

for (const { piece, patch } of drape.patches) {
    if (! piece || piece.wraps !== true || ! piece.placement || piece.placement.radius_hint !== 'waist') continue;
    let best = -1;
    let bestZ = -Infinity;
    let top = -Infinity;
    let low = Infinity;

    for (let v = 0; v < patch.count; v++) {
        top = Math.max(top, patch.positions[v * 3 + 1]);
        low = Math.min(low, patch.positions[v * 3 + 1]);
    }

    const mid = (top + low) / 2;

    for (let v = 0; v < patch.count; v++) {
        const z = patch.positions[v * 3 + 2];
        if (Math.abs(patch.positions[v * 3 + 1] - mid) < 0.012 && Math.abs(patch.positions[v * 3]) < 0.03 && z > bestZ) {
            bestZ = z;
            best = v;
        }
    }

    if (best >= 0) {
        const x = patch.positions[best * 3], y = patch.positions[best * 3 + 1], z = patch.positions[best * 3 + 2];
        buttons.push({ at: [x, y, z], normal: outward(x, y, z) });
    }
}

// وقتی رأس‌های دوخته جوش خورده‌اند، نوارِ درز مشِ تکراری و شناور می‌سازد.
if (band && welds.length === 0) {
    meshes.push({ id: 'seams', name: 'درزها', role: 'seam', positions: band.positions, indices: band.indices });
}

writeFileSync(output, JSON.stringify({
    format: 'dokht-sewn-mesh',
    seam_error: world.seamError(),
    stats: drape.stats,
    // همان تنی که پارچه رویش دوخته شد (حلقه‌ها، سانتی‌متر)؛ رندر مانکن را از همین می‌سازد
    body: raw,
    // پوستِ برخوردگر (متر): [y, نیم‌پهنا، میانگینِ عمق، جلو، پشت، مرکزِ z]؛ زیرِ بغل گود است تا آستین جا بگیرد
    hull: body.hull,
    meshes,
    // [شمارهٔ مشِ a، رأسِ a، شمارهٔ مشِ b، رأسِ b] پشتِ سرِ هم؛ ببینید بالا
    welds,
    // جای دکمه‌ها (متر) و جهتِ بیرون؛ ببینید بالا
    buttons,
}));


