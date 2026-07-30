/*
 * buildDrape از دید کسی که سیم‌کشی سه‌بعدی را می‌نویسد: قرارداد را رعایت
 * می‌کند، بودجه را می‌شکند ولی صدایش را درمی‌آورد، و دو بار اجرا با همان ورودی
 * همان لباس را می‌دهد.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { ClothWorld, Collider } from '../../resources/js/lib/cloth-solver.js';
import { buildDrape, supportGarment, weldSeams } from '../../resources/js/lib/pattern-drape.js';
import { bodyColliders, bodicePayload, collarPayload, makeBody, twoSleeves, twoSquares } from './fixtures/payload.js';

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

    /*
     * دو حد، چون دو چیزِ متفاوت را می‌پایند.
     *
     * حلقهٔ حل درز را تا حدِ تعادل می‌بندد، نه کامل: از وقتی درز لولای خمشی هم
     * دارد، سرشانه — تندترین گوشهٔ لباس — حدود ۲٫۵ میلی‌متر باز می‌ماند. آن لولا
     * همان چیزی است که نمی‌گذارد آستین روی حلقه تا شود، پس نبودش بدتر است.
     * چیزی که به کاربر نشان داده می‌شود، پس از weldSeams است و آن باید بسته
     * باشد. حدِ اول جلوی پس‌رفتِ حل‌کننده را می‌گیرد و حدِ دوم جلوی شکافِ دیدنی.
     */
    for (const seam of drape.seams) {
        assert.ok(
            seam.error() < 0.004,
            `درز «${seam.label}» با ${(seam.error() * 1000).toFixed(2)} میلی‌متر باز ماند`,
        );
    }

    weldSeams(drape);

    for (const seam of drape.seams) {
        assert.ok(
            seam.error() < 0.002,
            `درز «${seam.label}» پس از جوش با ${(seam.error() * 1000).toFixed(2)} میلی‌متر باز ماند`,
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

/*
 * صرفه‌جویی از تکرار گرفته می‌شود، نه از زیرگام.
 *
 * زیرگام ۱ لولهٔ آستین را روی بازو می‌خواباند — پوششِ دورِ بازو از ۳۴۰ درجه به
 * ۲۱۰ می‌افتاد و بازو لخت بیرون می‌ماند. تکرار در آن ماجرا اثری نداشت. پس مشِ
 * سنگین تکرارش کم می‌شود و زیرگامش نه.
 */
test('پیشنهاد تنظیم حل‌کننده با سنگینی مش عوض می‌شود', () => {
    const light = buildDrape(bodicePayload(), makeBody(), {});

    assert.equal(light.stats.solver.substeps, 2);
    assert.equal(light.stats.solver.iterations, 3);

    const heavy = buildDrape(bodicePayload(), makeBody(), { comfortableVertices: 10 });

    assert.equal(heavy.stats.solver.substeps, 2, 'زیرگام نباید زیر دو برود');
    assert.equal(heavy.stats.solver.iterations, 2);
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

/*
 * پارچه از آن‌سوی درز روی خودش برنمی‌گردد.
 *
 * این همان ایرادی است که در عکس «آستین و یقه وصل نیست» دیده می‌شد و هیچ‌کدام
 * از سنجه‌های قبلی نمی‌گرفتش: فاصلهٔ دو لبه زیر یک سانتی‌متر بود — درز کاملاً
 * بسته — ولی آستین پشت‌به‌پشتِ حلقه تا خورده بود، پس از بیرون فقط ضخامتِ لبه
 * دیده می‌شد. علتش هم روشن بود: قید درز فقط *جای* دو لبه را یکی می‌کند و
 * چیزی دربارهٔ *جهت*ِ ادامهٔ پارچه نمی‌گوید. درز، لولای بی‌سختی بود.
 *
 * اندازه: دو رأسِ پشتِ درز باید دست‌کم به اندازهٔ زاویهٔ ۹۰ درجه از هم دور
 * بمانند. بدونِ لولا، روی همین بالاتنه به ۲٪ آن فاصله می‌رسید.
 */
test('پارچه روی درز به پشتِ خودش تا نمی‌خورد', () => {
    const play = (hinge) => {
        const drape = buildDrape(bodicePayload(), makeBody(), { seamHinge: hinge });

        settle(drape, drape.stats.presettle);

        let worst = Infinity;

        for (const seam of drape.seams) {
            if (! seam.hinge) {
                continue;
            }

            for (let i = 0; i < seam.hinges; i++) {
                const goal = seam.hinge[i * 3 + 2];

                if (goal <= 0) {
                    continue;
                }

                const a = seam.hinge[i * 3] * 3;
                const b = seam.hinge[i * 3 + 1] * 3;

                worst = Math.min(worst, Math.hypot(
                    seam.a.positions[a] - seam.b.positions[b],
                    seam.a.positions[a + 1] - seam.b.positions[b + 1],
                    seam.a.positions[a + 2] - seam.b.positions[b + 2],
                ) / goal);
            }
        }

        return worst;
    };

    const loose = play(0);
    const held = play(0.5);

    assert.ok(Number.isFinite(loose), 'بستهٔ آزمون باید درزِ لولادار داشته باشد');
    assert.ok(
        held > loose * 1.5,
        `لولا باید تا خوردن را کم کند؛ بی‌لولا ${loose.toFixed(2)} و با لولا ${held.toFixed(2)}`,
    );
    assert.ok(
        held > 0.45,
        `تندترین تا خوردگی ${held.toFixed(2)} برابرِ حدِ ۹۰ درجه است؛ زیر ۰٫۴۵ یعنی پارچه برگشته`,
    );
});

/*
 * لباس یک‌وری نمی‌نشیند: قطعهٔ آینه‌شده آینهٔ جفتِ خودش می‌ماند.
 *
 * دو ایراد این را می‌شکست و هر دو با اندازه‌گیری پیدا شدند، چون درز و کشش و
 * پارگی هیچ‌کدام نشانش نمی‌دادند:
 *
 *   ۱. قراردادِ سمت برعکس بود. سرور زاویهٔ منفی را «چپ» می‌نامد و زاویه با
 *      سینوس به x می‌رسد، پس چپ یعنی x منفی؛ این‌جا 'left' روی x مثبت چیده
 *      می‌شد. هر دو آستین سرِ سمتِ اشتباه می‌نشستند و بعد قیدِ درز آن‌ها را از
 *      روی تنه به سمتِ درست می‌کشید، هر کدام از راهی.
 *   ۲. جفت‌وجورِ صُلب هر قطعه را دورِ محورِ *مانکن* می‌چرخاند. آستین روی محورِ
 *      بازو نشسته و چرخشش دورِ تنه، آن را در عمق جابه‌جا می‌کند؛ دو آستینِ دو
 *      طرفِ بدن عمقِ مخالف می‌گیرند. اندازه: مرکزِ آستینِ چپ z=−۹٫۲ و راست
 *      z=+۶٫۰ — یکی پشتِ بدن و یکی جلویش.
 *
 * روی پیراهنِ سنجه، ناقرینگیِ چیدن ۹٫۳ سانتی‌متر بود و ۳٫۹ شد.
 */
test('آستینِ چپ و راست آینهٔ هم چیده می‌شوند', () => {
    const drape = buildDrape(twoSleeves(), makeBody(), {});
    const [left, right] = drape.patches;

    assert.equal(drape.patches.length, 2);

    const middle = (patch) => {
        const out = [0, 0, 0];

        for (let v = 0; v < patch.count; v++) {
            for (let k = 0; k < 3; k++) {
                out[k] += patch.positions[v * 3 + k] / patch.count;
            }
        }

        return out;
    };

    const one = middle(left.patch);
    const two = middle(right.patch);

    assert.ok(one[0] < -0.05, `آستینِ چپ باید روی x منفی بنشیند، نشست روی ${(one[0] * 100).toFixed(1)}`);
    assert.ok(two[0] > 0.05, `آستینِ راست باید روی x مثبت بنشیند، نشست روی ${(two[0] * 100).toFixed(1)}`);

    // آینهٔ x: عمق و ارتفاع باید یکی باشند و x قرینه
    assert.ok(
        Math.abs(one[0] + two[0]) < 0.02,
        `x دو آستین قرینه نیست: ${(one[0] * 100).toFixed(1)} و ${(two[0] * 100).toFixed(1)}`,
    );
    assert.ok(
        Math.abs(one[2] - two[2]) < 0.02,
        `عمقِ دو آستین یکی نیست: ${(one[2] * 100).toFixed(1)} و ${(two[2] * 100).toFixed(1)}`,
    );
    assert.ok(
        Math.abs(one[1] - two[1]) < 0.02,
        `ارتفاعِ دو آستین یکی نیست: ${(one[1] * 100).toFixed(1)} و ${(two[1] * 100).toFixed(1)}`,
    );
});

/*
 * بازو داخلِ تنه نیست — و همین بود که آستین را از روی بازو کنار می‌برد.
 *
 * محورِ بازو ۰٫۸۷ × نیم‌پهنای شانه بود: روی سایز ۴۰ می‌شود ۱۷٫۰ سانتی‌متر، در
 * حالی که شعاع تنه در حلقه ۱۴٫۹ و شعاع بازو ۴٫۵ است — یعنی بازو ۲٫۴ سانتی‌متر
 * *داخلِ* تنه. آن وقت لولهٔ آستین جا نداشت: برخوردگرِ تنه سمتِ داخلی‌اش را پس
 * می‌زد و آستین با برگشتنِ وزن ۴ سانتی‌متر بیرون می‌رفت و بازو لخت می‌ماند.
 *
 * اندازه‌گیری روی پیراهنِ سنجه: پوششِ لولهٔ آستین دورِ بازو ۱۱۰ درجه از ۳۶۰ بود
 * و ۳۶۰ شد؛ مقطعش ۶ سانتی‌متر از محورِ بازو فاصله داشت و به صفر رسید.
 *
 * قاعده‌اش هندسی و شمردنی است، پس همین‌جا قفل می‌شود: بازو باید بیرونِ تنه
 * بایستد. اگر روزی کسی ضریب را برگرداند، این آزمون می‌گیردش.
 */
test('بازوی مانکن بیرونِ تنه می‌ایستد، نه داخلش', () => {
    for (const avatar of [{}, { bust: 78, shoulder_width: 34 }, { bust: 118, shoulder_width: 46 }]) {
        const body = makeBody(avatar);
        const at = (y) => {
            const rows = body.profile.slice().sort((one, two) => one[0] - two[0]);

            for (let i = 1; i < rows.length; i++) {
                if (y <= rows[i][0]) {
                    const span = Math.max(1e-9, rows[i][0] - rows[i - 1][0]);
                    const t = (y - rows[i - 1][0]) / span;

                    return rows[i - 1][1] + (rows[i][1] - rows[i - 1][1]) * t;
                }
            }

            return rows[rows.length - 1][1];
        };

        const torso = at(body.level.armhole);
        const inner = body.armOffset - body.radii.bicep;

        assert.ok(
            inner >= torso - 1e-6,
            `لبهٔ داخلیِ بازو روی ${(inner * 100).toFixed(1)} است و تنه تا ${(torso * 100).toFixed(1)}؛ `
                + `بازو ${((torso - inner) * 100).toFixed(1)} سانتی‌متر داخلِ تنه فرو رفته و جایی برای آستین نمی‌ماند`,
        );
    }
});

/*
 * و قطعهٔ آستین روی همان محور چیده می‌شود، نه روی محورِ بدن.
 */
test('آستین روی محورِ بازو چیده می‌شود', () => {
    const body = makeBody();
    const drape = buildDrape(twoSleeves(), body, {});

    for (const { id, patch } of drape.patches) {
        let sum = 0;

        for (let v = 0; v < patch.count; v++) {
            sum += patch.positions[v * 3] / patch.count;
        }

        const want = id.endsWith('#0') ? -body.armOffset : body.armOffset;

        assert.ok(
            Math.abs(sum - want) < 0.02,
            `مرکزِ «${id}» روی ${(sum * 100).toFixed(1)} است، محورِ بازو روی ${(want * 100).toFixed(1)}`,
        );
    }
});

/*
 * هیچ سوزنی نوارِ پارچه را روی یک نقطه جمع نمی‌کند.
 *
 * این همان چیزی بود که کاربر «لبه‌های تیکه‌تیکه» می‌خواندش، و هیچ سنجهٔ دیگری
 * نمی‌گرفتش: درز بسته بود، لباس قرینه، آستین روی بازو — ولی جفت‌سازیِ سوزن‌ها
 * بادبزنی بود. هرجا یک سمتِ درز رأس‌های ریزتری داشت، چند رأسش روی *یک* رأسِ سمتِ
 * دیگر می‌افتاد؛ روی درزِ سرشانه دو سوزنِ پی‌درپی یک سمت ۱٫۴۴ سانتی‌متر جلو
 * می‌رفت و سمتِ دیگر صفر. آن نوار لِه می‌شد و همسایه‌اش جِر می‌خورد: مثلثِ
 * سی‌برابر کشیده در سرشانه و حلقه.
 *
 * حالا سرِ دومِ سوزن می‌تواند نقطه‌ای روی پاره‌خطِ میانِ دو رأس باشد، پس هر رأس
 * شریکِ خودش را دارد. این آزمون روی *الگوی تخت* می‌سنجد — فیزیک دخالتی ندارد و
 * اگر روزی جفت‌ساز به بادبزن برگردد، همین‌جا لو می‌رود.
 */
test('سوزن‌های پی‌درپی، هر دو سمت را به یک اندازه جلو می‌برند', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});

    assert.ok(drape.seams.length > 0);

    for (const seam of drape.seams) {
        const ga = seam.a.grain || drape.patches.find((entry) => entry.patch === seam.a).mesh.grain;
        const host = seam.b || seam.a;
        const gb = drape.patches.find((entry) => entry.patch === host).mesh.grain;

        const spot = (i) => {
            const w = seam.second && seam.weight ? seam.weight[i] : 0;
            const one = seam.pairs[i * 2 + 1];
            const two = w > 0 ? seam.second[i] : one;

            return [
                gb[one * 2] * (1 - w) + gb[two * 2] * w,
                gb[one * 2 + 1] * (1 - w) + gb[two * 2 + 1] * w,
            ];
        };

        for (let i = 1; i < seam.count; i++) {
            const before = seam.pairs[(i - 1) * 2];
            const now = seam.pairs[i * 2];
            const step = Math.hypot(ga[now * 2] - ga[before * 2], ga[now * 2 + 1] - ga[before * 2 + 1]);
            const [x0, y0] = spot(i - 1);
            const [x1, y1] = spot(i);
            const other = Math.hypot(x1 - x0, y1 - y0);

            if (step < 1e-5 && other < 1e-5) {
                continue;
            }

            assert.ok(
                Math.max(step, other) / Math.max(1e-5, Math.min(step, other)) < 6,
                `درز «${seam.label}» سوزنِ ${i}: یک سمت ${(step * 100).toFixed(2)} و سمتِ دیگر `
                    + `${(other * 100).toFixed(2)} سانتی‌متر جلو رفت؛ اختلافِ بیش از شش‌برابر یعنی `
                    + 'پارچه زیرِ یک سوزن جمع می‌شود',
            );
        }
    }
});

/*
 * نوارِ نگه‌داشته روی خودِ بدن میخ می‌شود، نه هرجا که پارچه رسیده.
 *
 * میخ جای امروزِ پارچه را ثبت می‌کند. اگر لباس در پایانِ دوختِ بی‌وزنی چند
 * سانتی‌متر بالای شانه مانده باشد، میخ همان بلندی را قفل می‌کند و وزن هم پایینش
 * نمی‌آورد. اندازه گرفتیم روی پیراهن: در نوکِ شانه ۳۶ رأس بیش از دو سانتی‌متر از
 * بدن دور بودند و *همه‌شان* میخکوب — بدترینشان ۵٫۴ سانتی‌متر، و از لای همان
 * بلندی پوستِ شانه دیده می‌شد. پس از این اصلاح ۳٫۹.
 */
test('نگه‌دارنده، نوارِ بالا را روی بدن می‌نشاند', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});
    const body = drape.body;

    assert.ok(body, 'خروجی باید بدن را همراه داشته باشد، وگرنه نشاندن ممکن نیست');

    // پارچه را عمداً از تن دور می‌کنیم تا کار نگه‌دارنده دیده شود
    for (const { patch } of drape.patches) {
        for (let v = 0; v < patch.count; v++) {
            patch.positions[v * 3] *= 1.6;
            patch.positions[v * 3 + 2] *= 1.6;
        }

        patch.remember();
    }

    const reach = (patch, v) => {
        const y = patch.positions[v * 3 + 1];
        const rows = body.profile.slice().sort((one, two) => one[0] - two[0]);
        let rx = rows[rows.length - 1][1];
        let rz = rows[rows.length - 1][2];

        for (let i = 1; i < rows.length; i++) {
            if (y <= rows[i][0]) {
                const t = (y - rows[i - 1][0]) / Math.max(1e-9, rows[i][0] - rows[i - 1][0]);

                rx = rows[i - 1][1] + (rows[i][1] - rows[i - 1][1]) * t;
                rz = rows[i - 1][2] + (rows[i][2] - rows[i - 1][2]) * t;

                break;
            }
        }

        return Math.hypot(patch.positions[v * 3] / rx, patch.positions[v * 3 + 2] / rz);
    };

    const before = drape.patches.map(({ patch }) => {
        let worst = 0;

        for (let v = 0; v < patch.count; v++) {
            worst = Math.max(worst, reach(patch, v));
        }

        return worst;
    });

    supportGarment(drape, { band: 0.08, strength: 1 });

    let checked = 0;

    drape.patches.forEach(({ patch }, at) => {
        let worst = 0;

        for (let v = 0; v < patch.count; v++) {
            if (patch.follow[v] > 0.9) {
                worst = Math.max(worst, reach(patch, v));
            }
        }

        if (worst === 0) {
            return;
        }

        checked++;

        assert.ok(
            worst < 1.2 && worst < before[at],
            `نوارِ نگه‌داشته روی ${worst.toFixed(2)} برابرِ سطحِ بدن ماند (پیش از نشاندن ${before[at].toFixed(2)})`,
        );
    });

    assert.ok(checked > 0, 'هیچ قطعه‌ای نوارِ نگه‌داشته نداشت؛ آزمون چیزی را نسنجید');
});

/*
 * یقه روی خط خوابش تا می‌شود، و جوشِ درز آن را باز نمی‌کند.
 *
 * قیدِ خمشِ پارچه حالتِ استراحتش را از الگوی *تخت* می‌گیرد، پس یقه‌ای که چیده
 * می‌شود خودش را باز می‌کند و راست می‌ایستد — روی پیراهن ۶٫۳ سانتی‌متر بالای خط
 * یقه اندازه گرفته شد، با یک نوکِ بیرون‌زده. یقهٔ واقعی روی خطِ خواب اتو می‌شود،
 * و آن اتو قیدِ جداگانه‌ای است.
 *
 * دو چیز اینجا قفل می‌شود:
 *   ۱. تا بسته می‌شود (فاصلهٔ آینه‌ها از ۴٫۱ به ۲٫۴ سانتی‌متر می‌رسد و بلندیِ
 *      یقه از ۷٫۵ به ۵٫۲ می‌آید).
 *   ۲. weldSeams تا را خطا نمی‌شمارد. نسخهٔ پیش از این، تای بسته را «درزِ
 *      کشیده» می‌دید و بازش می‌کرد: شعاعِ یقه از ۱۱٫۶ به ۶۱٫۶ سانتی‌متر می‌پرید.
 */
test('یقه روی خط خواب تا می‌شود و جوش تا را باز نمی‌کند', () => {
    const body = makeBody();
    const drape = buildDrape(collarPayload(), body, {});
    const creases = drape.seams.filter((seam) => seam.kind === 'crease');

    assert.equal(creases.length, 1, 'برای خط خواب یقه دقیقاً یک قید تا باید ساخته شود');

    const crease = creases[0];

    assert.ok(crease.count > 20, `تا فقط ${crease.count} جفت دارد؛ یک ردیف تا نمی‌سازد`);

    const entry = drape.patches[0];
    const { grain } = entry.mesh;

    /* فاصلهٔ میانگینِ جفت‌های آینه: روی الگوی تخت، و روی پارچه */
    const fold = () => {
        let flat = 0;
        let now = 0;

        for (let i = 0; i < crease.count; i++) {
            const a = crease.pairs[i * 2];
            const b = crease.pairs[i * 2 + 1];

            flat += Math.hypot(grain[a * 2] - grain[b * 2], grain[a * 2 + 1] - grain[b * 2 + 1]);
            now += Math.hypot(
                entry.patch.positions[a * 3] - entry.patch.positions[b * 3],
                entry.patch.positions[a * 3 + 1] - entry.patch.positions[b * 3 + 1],
                entry.patch.positions[a * 3 + 2] - entry.patch.positions[b * 3 + 2],
            );
        }

        return { flat: flat / crease.count, now: now / crease.count };
    };

    /* بلندی و پهنای یقه، سانتی‌متر */
    const size = () => {
        let lo = Infinity;
        let hi = -Infinity;
        let radius = 0;

        for (let v = 0; v < entry.patch.count; v++) {
            const y = entry.patch.positions[v * 3 + 1];

            lo = Math.min(lo, y);
            hi = Math.max(hi, y);
            radius = Math.max(radius, Math.hypot(entry.patch.positions[v * 3], entry.patch.positions[v * 3 + 2]));
        }

        return { tall: (hi - lo) * 100, radius: radius * 100 };
    };

    const world = new ClothWorld({ fabric: {} });

    drape.patches.forEach((one) => world.addPatch(one.patch));
    drape.seams.forEach((seam) => world.addSeam(seam));
    world.setColliders(bodyColliders(Collider, body));

    const cut = size();

    assert.ok(cut.tall > 7 && cut.tall < 8, `یقهٔ چیده ${cut.tall.toFixed(1)} سانتی‌متر بلند است، نه ۷٫۵`);

    supportGarment(drape, { band: 0.08, strength: 1 });
    world.presettle(drape.stats.presettle ?? 260);

    const shut = fold();
    const on = size();

    assert.ok(
        shut.now < shut.flat * 0.7,
        `تا بسته نشد: آینه‌ها روی پارچه ${(shut.now * 100).toFixed(1)} سانتی‌متر فاصله دارند و روی الگو ${(shut.flat * 100).toFixed(1)}`,
    );
    assert.ok(on.tall < cut.tall - 1.5, `بلندی یقه از ${cut.tall.toFixed(1)} به ${on.tall.toFixed(1)} نرسید؛ برنگشته است`);

    /*
     * تا درزِ باز نیست. اگر seamError آن را بشمارد، هر لباسِ یقه‌دار برای همیشه
     * «درزِ باز» گزارش می‌دهد و سنجه بی‌معنی می‌شود.
     */
    assert.equal(world.seamError(), 0, 'تا به عنوان درزِ باز شمرده شد');

    weldSeams(drape);
    world.presettle(60);

    const after = fold();
    const welded = size();

    assert.ok(
        Math.abs(after.now - shut.now) < 0.002,
        `جوش تا را ${((after.now - shut.now) * 1000).toFixed(1)} میلی‌متر جابه‌جا کرد`,
    );
    assert.ok(
        welded.radius < on.radius + 2,
        `جوش یقه را از ${on.radius.toFixed(1)} به ${welded.radius.toFixed(1)} سانتی‌متر باز کرد`,
    );
});

/*
 * درز، پس از جوش هم بسته می‌ماند.
 *
 * جوش قید نیست — رأس‌ها را جابه‌جا می‌کند و بس. سنجه تا امروز درست بعد از جوش
 * اندازه می‌گرفت و ۰٫۱ سانتی‌متر می‌دید، ولی نماگر شبیه‌سازی را ادامه می‌دهد و
 * قیدِ نرمِ درز دوباره باز می‌شد: روی کت ۱۲٫۷ سانتی‌متر، روی ترنچ ۱۲٫۷، روی
 * قپائو ۱۰٫۲. کاربر همین را دید و گفت «یقه در سمت به لباس وصل نیست، آزاده».
 *
 * درمانش پاسِ اضافیِ درز بود، نه سخت‌ترکردنش (با سختیِ ۱٫۰ کت به ۱۵٫۸ بدتر شد).
 * این آزمون همان را قفل می‌کند: عددِ *ماندگار*، نه عددِ لحظهٔ جوش.
 */
test('درز پس از جوش و ادامه‌ی شبیه‌سازی باز نمی‌شود', () => {
    const drape = buildDrape(bodicePayload(), makeBody(), {});

    assert.ok(
        (drape.stats.solver?.seamPasses ?? 1) > 1,
        'پاسِ اضافیِ درز در تنظیماتِ حل‌کننده نیست؛ نماگر آن را برنمی‌دارد',
    );

    const world = settle(drape, drape.stats.presettle);

    world.seamPasses = drape.stats.solver.seamPasses;
    world.presettle(120);

    const settled = world.seamError();

    weldSeams(drape);

    const snapped = world.seamError();

    assert.ok(snapped < 0.002, `جوش درز را نبست: ${(snapped * 1000).toFixed(1)} میلی‌متر`);

    world.presettle(150);

    const durable = world.seamError();

    assert.ok(
        durable < 0.006,
        `درز پس از جوش دوباره تا ${(durable * 1000).toFixed(1)} میلی‌متر باز شد`,
    );
    assert.ok(
        durable <= settled + 0.001,
        `جوش ماندگار نبود: پیش از جوش ${(settled * 1000).toFixed(1)} و پس از آن ${(durable * 1000).toFixed(1)} میلی‌متر`,
    );
});

/*
 * سرِ آستین روی سرشانه می‌نشیند، نه ده سانتی‌متر پایین‌تر.
 *
 * سرِ آستین همان کمانی است که به حلقه دوخته می‌شود و بالاترین نقطهٔ حلقه سرشانه
 * است، نه زیربغل. با تراز حلقه، آستین پایین می‌افتاد و «سرشانهٔ گوشتی» — بالای
 * بازو، بیرون از بیضیِ تنه — لخت می‌ماند. هیچ سنجه‌ای این را نمی‌دید چون همه روی
 * *تنه* نقطه می‌گذاشتند: فاصله تا نزدیک‌ترین مثلثِ تنه همه‌جا زیر ۲٫۵ سانتی‌متر
 * بود و باز هم در عکس پوست دیده می‌شد.
 */
test('سرِ آستین بالاتر از تراز حلقه می‌نشیند', () => {
    const body = makeBody();
    const payload = twoSleeves();

    /* y_top در بسته نسبتی از قدِ بدن است، نه متر */
    const armhole = body.level.armhole / body.level.top;
    const shoulder = body.level.shoulder / body.level.top;

    for (const piece of payload.pieces) {
        piece.placement.y_top = armhole + 0.5 * (shoulder - armhole);
    }

    const drape = buildDrape(payload, body, {});
    const lifted = drape.patches.map(({ patch }) => {
        let top = -Infinity;

        for (let v = 0; v < patch.count; v++) {
            top = Math.max(top, patch.positions[v * 3 + 1]);
        }

        return top;
    });

    for (const top of lifted) {
        assert.ok(
            top > body.level.armhole + 0.01,
            `سرِ آستین روی ${(top * 100).toFixed(1)} است و تراز حلقه ${(body.level.armhole * 100).toFixed(1)}؛ بالا نرفته`,
        );
        assert.ok(
            top < body.level.shoulder + 0.06,
            `سرِ آستین تا ${(top * 100).toFixed(1)} بالا رفت، بالاتر از سرشانه`,
        );
    }
});
