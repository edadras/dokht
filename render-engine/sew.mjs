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

    for (let i = 0; i < seam.count; i++) {
        if (seam.dead && seam.dead[i]) {
            continue;
        }

        welds.push(ia, seam.pairs[i * 2], ib, seam.pairs[i * 2 + 1]);
    }
}

if (band) {
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
}));

