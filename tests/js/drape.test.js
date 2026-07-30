/*
 * buildDrape از دید کسی که سیم‌کشی سه‌بعدی را می‌نویسد: قرارداد را رعایت
 * می‌کند، بودجه را می‌شکند ولی صدایش را درمی‌آورد، و دو بار اجرا با همان ورودی
 * همان لباس را می‌دهد.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { ClothWorld } from '../../resources/js/lib/cloth-solver.js';
import { buildDrape, supportGarment, weldSeams } from '../../resources/js/lib/pattern-drape.js';
import { bodicePayload, makeBody, twoSquares } from './fixtures/payload.js';

const settle = (drape, steps = 260) => {
    const world = new ClothWorld({ fabric: {} });

    drape.patches.forEach((entry) => world.addPatch(entry.patch));
    drape.seams.forEach((seam) => world.addSeam(seam));
    world.presettle(steps);

    return world;
};

test('خروجی همان شکلی است که قرارداد گفته', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});

    assert.ok(Array.isArray(drape.patches));
    assert.ok(Array.isArray(drape.seams));
    assert.ok(Array.isArray(drape.meshes));
    assert.equal(drape.patches.length, drape.meshes.length);

    for (const entry of drape.patches) {
        assert.equal(typeof entry.id, 'string');
        assert.ok(entry.piece);
        assert.ok(entry.patch.positions instanceof Float32Array);
        assert.ok(entry.mesh.indices instanceof Uint32Array);
        assert.ok(entry.mesh.uv instanceof Float32Array);
        assert.equal(entry.mesh.uv.length, entry.patch.count * 2);
        assert.equal(entry.mesh.positions, entry.patch.positions, 'مش و تکه باید یک بافر داشته باشند');
    }

    assert.equal(typeof drape.stats.vertices, 'number');
    assert.equal(typeof drape.stats.triangles, 'number');
    assert.ok(Array.isArray(drape.stats.dropped));
});

test('لایه‌های خاموش بی‌صدا حذف نمی‌شوند', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});

    assert.equal(drape.patches.length, 2, 'آستر نباید در لایه‌ی رو ساخته شود');
    assert.equal(drape.stats.skipped.length, 1);
    assert.equal(drape.stats.skipped[0].id, 'lining#0');

    const all = buildDrape(bodicePayload(), makeBody(), { layers: 'all' });

    assert.equal(all.patches.length, 3);
    assert.equal(all.stats.skipped.length, 0);
});

test('بودجه‌ی رأس رعایت می‌شود و بزرگ شدن یال گزارش می‌شود', () => {
    const payload = bodicePayload();

    payload.budget = { target_edge: 1, max_vertices: 500 };

    const drape = buildDrape(payload, makeBody(), {});

    assert.ok(drape.stats.vertices <= 500, `${drape.stats.vertices} رأس از بودجه‌ی ۵۰۰ بیشتر شد`);
    assert.ok(drape.stats.retries > 0, 'مثلث‌بندی دوباره انجام نشد');
    assert.ok(drape.stats.targetEdge > 1, 'طول یال بزرگ نشد');
    assert.ok(
        drape.stats.warnings.some((line) => line.includes('طول یال')),
        'بزرگ شدن یال گزارش نشد',
    );
    assert.equal(drape.patches.length, 2, 'قطعه‌ای بی‌صدا حذف شد');
});

test('درزی که سرش پیدا نشود گزارش می‌شود، نه اینکه دور ریخته شود', () => {
    const payload = bodicePayload();

    payload.seams.push({
        a: { piece: 'front-bodice#0', from: 0, to: 1, length: 10 },
        b: { piece: 'sleeve#0', from: 0, to: 1, length: 10 },
        label: 'درز حلقه',
        kind: 'seam',
    });

    const drape = buildDrape(payload, makeBody(), {});

    assert.equal(drape.stats.unmatched.length, 1);
    assert.ok(drape.stats.unmatched[0].reason.includes('sleeve#0'));
});

test('تای پارچه دوخته نمی‌شود ولی در گزارش می‌آید', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});
    const folds = drape.stats.checks.filter((row) => row.kind === 'fold');

    assert.equal(folds.length, 1);
    assert.equal(drape.seams.filter((seam) => seam.kind === 'fold').length, 0);
});

test('طول کمانِ اندازه‌گرفته‌شده با طول بسته می‌خواند', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});
    const drifted = drape.stats.checks.filter((row) => row.measured !== undefined);

    assert.equal(drifted.length, 0, `طول کمان با بسته نخواند: ${JSON.stringify(drifted)}`);
});

test('همه‌ی درزهای یک بالاتنه بعد از نشستن بسته می‌شوند', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});

    for (const seam of drape.seams) {
        assert.ok(seam.error() > 0.005, `درز «${seam.label}» از همان اول بسته بود`);
    }

    // با همان تعداد گامی که خودِ buildDrape پیشنهاد کرده
    assert.ok(drape.stats.presettle >= 160);
    settle(drape, drape.stats.presettle);

    for (const seam of drape.seams) {
        assert.ok(
            seam.error() < 0.002,
            `درز «${seam.label}» با ${(seam.error() * 1000).toFixed(2)} میلی‌متر باز ماند`,
        );
    }
});

test('دو بار اجرا با همان ورودی، بیت‌به‌بیت همان خروجی می‌دهد', () => {
    const run = () => {
        const drape = buildDrape(bodicePayload(), makeBody(), {});

        settle(drape, 150);

        return drape.patches.map((entry) => Buffer.from(entry.patch.positions.buffer.slice(0)));
    };

    const first = run();
    const second = run();

    assert.equal(first.length, second.length);

    for (let i = 0; i < first.length; i++) {
        assert.ok(first[i].equals(second[i]), `قطعه‌ی ${i} در دو اجرا فرق کرد`);
    }
});

test('مثلث‌بندی هم دو بار اجرا یکی است', () => {
    const one = buildDrape(twoSquares(), makeBody(), {});
    const two = buildDrape(twoSquares(), makeBody(), {});

    for (let i = 0; i < one.patches.length; i++) {
        assert.deepEqual(Array.from(one.meshes[i].indices), Array.from(two.meshes[i].indices));
        assert.deepEqual(Array.from(one.meshes[i].uv), Array.from(two.meshes[i].uv));
    }
});

test('نگه‌دارنده بعد از دوخته شدن، لبه‌ی بالا را می‌گیرد', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});

    for (const entry of drape.patches) {
        assert.ok(entry.patch.follow, 'آرایه‌ی چسبندگی ساخته نشده');
        assert.equal(
            entry.patch.follow.reduce((sum, value) => sum + value, 0),
            0,
            'نگه‌دارنده پیش از دوختن روشن بود',
        );
    }

    settle(drape, 120);
    supportGarment(drape);

    const held = drape.patches.filter((entry) =>
        entry.patch.follow.some((value) => value > 0),
    );

    assert.equal(held.length, 2, 'لبه‌ی بالای قطعه‌های تنه گرفته نشد');
});

test('پیشنهاد تنظیم حل‌کننده با سنگینی مش عوض می‌شود', () => {
    const light = buildDrape(bodicePayload(), makeBody(), {});

    assert.equal(light.stats.solver.substeps, 2);

    const heavy = buildDrape(bodicePayload(), makeBody(), { comfortableVertices: 10 });

    assert.equal(heavy.stats.solver.substeps, 1);
    assert.ok(heavy.stats.warnings.some((line) => line.includes('رأس')));
});

/*
 * قطعه‌ها باید کنارِ هم پارک شده باشند، پیش از آنکه فیزیک شروع شود.
 *
 * این آزمون همان چیزی را قفل می‌کند که گران‌ترین درسِ این ماژول بود: قید درز
 * فقط در خط راست می‌کشد و راهِ دور بدن را نمی‌شناسد. اگر دو سرِ یک درز دور از
 * هم شروع کنند، پارچه از روی مانکن کشیده می‌شود و لباس گره می‌خورد — حتی وقتی
 * الگو و برش و جفتِ درزها همه درست‌اند. اندازه‌ی اندازه‌گرفته‌شده روی پیراهن
 * کلاسیک: ۳۸ سانتی‌متر پیش از چیدنِ گرافی، ۱۰٫۶ سانتی‌متر پس از آن.
 */
test('دو سرِ هر درز نزدیک هم پارک می‌شوند', () => {
    const payload = typeof bodicePayload === 'function' ? bodicePayload() : bodicePayload;
    const drape = buildDrape(payload.drape || payload, makeBody(payload.avatar || {}), {});

    assert.ok(drape.seams.length > 0, 'بستهٔ آزمون باید درز داشته باشد');

    for (const seam of drape.seams) {
        const a = seam.a.positions;
        const b = (seam.b || seam.a).positions;
        let sum = 0;

        for (let i = 0; i < seam.count; i++) {
            const at = seam.pairs[i * 2] * 3;
            const to = seam.pairs[i * 2 + 1] * 3;

            sum += Math.hypot(a[at] - b[to], a[at + 1] - b[to + 1], a[at + 2] - b[to + 2]);
        }

        const mean = sum / Math.max(1, seam.count);

        assert.ok(
            mean < 0.15,
            `درز «${seam.label || '—'}» با ${(mean * 100).toFixed(1)} سانتی‌متر فاصله شروع می‌کند؛ بیش از ۱۵ سانتی‌متر یعنی پارچه از روی بدن کشیده می‌شود`,
        );
    }
});

test('هیچ رأسی پس از چیدن NaN نیست', () => {
    const payload = typeof bodicePayload === 'function' ? bodicePayload() : bodicePayload;
    const drape = buildDrape(payload.drape || payload, makeBody(payload.avatar || {}), {});

    for (const { id, patch } of drape.patches) {
        for (let i = 0; i < patch.positions.length; i++) {
            assert.ok(Number.isFinite(patch.positions[i]), `قطعهٔ «${id}» رأس بی‌مقدار دارد`);
        }
    }
});

/*
 * جوش دادنِ درز نباید پارچه را لِه کند.
 *
 * درسِ گران این یکی: خطای درز پایین آمد و همان لحظه مثلث تیغه‌ای ساخته شد.
 * جفت‌کردنِ رأس‌های درز چند-به-یک است، پس بستنِ کاملِ همهٔ جفت‌ها دو رأسِ کنارِ
 * هم را روی یک نقطه می‌نشاند؛ کوتاه‌ترین ضلعِ آن مثلث‌ها دقیقاً صفر بود، نه
 * کشیده. سنجهٔ کشش این را نمی‌دید چون مساحت کوچک می‌شود، نه بزرگ. کاربر ولی
 * می‌دید و «پارچهٔ تیکه‌پاره» می‌خواند. اینجا با ضلع اندازه می‌گیریم.
 */
test('جوش دادنِ درز ضلعِ پارچه را نمی‌خواباند', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});
    const world = settle(drape, 120);

    world.seams.forEach((seam) => seam.weld());
    world.presettle(30);
    weldSeams(drape);

    for (const { id, mesh } of drape.patches) {
        const { positions, indices, grain } = mesh;

        for (let t = 0; t < indices.length; t += 3) {
            for (const [x, y] of [[0, 1], [1, 2], [2, 0]]) {
                const a = indices[t + x];
                const b = indices[t + y];
                const rest = Math.hypot(
                    grain[b * 2] - grain[a * 2],
                    grain[b * 2 + 1] - grain[a * 2 + 1],
                );
                const now = Math.hypot(
                    positions[b * 3] - positions[a * 3],
                    positions[b * 3 + 1] - positions[a * 3 + 1],
                    positions[b * 3 + 2] - positions[a * 3 + 2],
                );

                assert.ok(
                    rest < 1e-9 || now > rest * 0.5,
                    `قطعهٔ «${id}»: ضلعی که روی الگو ${(rest * 100).toFixed(2)} سانتی‌متر است پس از جوش ${(now * 100).toFixed(2)} شد`,
                );
            }
        }
    }
});

/* و همان جوش باید کارِ خودش را هم بکند: درز از پیش‌ازِ خودش بازتر نشود */
test('جوش دادنِ درز، درز را بازتر نمی‌کند', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});
    const world = settle(drape, 120);

    world.seams.forEach((seam) => seam.weld());
    world.presettle(30);

    const before = world.seamError();

    weldSeams(drape);

    assert.ok(
        world.seamError() <= before + 1e-4,
        `خطای درز از ${(before * 100).toFixed(2)} به ${(world.seamError() * 100).toFixed(2)} سانتی‌متر رفت`,
    );
});
