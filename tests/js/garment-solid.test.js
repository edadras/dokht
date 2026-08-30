/*
 * لباس روی مانکن — بخش‌هایی که ریاضی‌اند، نه تصویری.
 *
 * «قشنگ دیده می‌شود» را تست نمی‌شود نوشت، ولی چیزهایی که این نما را خراب
 * می‌کنند همه شمردنی‌اند: حلقه‌ای که حذف شد و لباس مخروط شد، پایی که زیر دامن
 * نبود، دستی که وسطِ قفسهٔ سینه فرو رفته بود. همان‌ها این‌جا بسته می‌شوند.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { armCentre, armJoint, buildBody } from '../../resources/js/lib/mannequin.js';
import {
    bodyColliders, bodyEnvelope, chainAt, depthAt, smooth,
} from '../../resources/js/components/garment-solid.js';

const body = buildBody({
    height: 168, bust: 92, under_bust: 78, waist: 74, hip: 100,
    shoulder_width: 39, back_length: 41, waist_to_hip: 21, inseam: 76, arm_length: 58,
});

/* حلقه‌های لباس همان‌طور که سرور می‌دهد: هر هشت میلی‌متر یکی */
const ladder = (count, step = 0.8) => Array.from({ length: count }, (_, i) => ({
    y: i * step, rx: 10 + i * 0.1, front: 7, back: 7,
}));

test('رقیق‌کردن حلقه‌ها، لباس را به مخروط تبدیل نمی‌کند', () => {
    const out = smooth(ladder(130));

    /*
     * یک بار شرطِ فاصله را با حلقهٔ قبلیِ *فهرست اصلی* سنجیدم، نه با آخرین
     * حلقهٔ نگه‌داشته‌شده. چون فاصلهٔ اصلی همیشه از گام کمتر بود، فقط اولی و
     * آخری ماندند و پیراهن یک مخروط از سرشانه تا زمین شد.
     */
    assert.ok(out.length > 30, `باید ده‌ها حلقه بماند، نه ${out.length}`);
    assert.ok(out.length < 130, 'ولی همه‌شان هم نه');

    out.forEach((ring, i) => {
        if (i > 0) {
            assert.ok(ring.y - out[i - 1].y >= 1.5, 'فاصلهٔ حلقه‌ها باید باز شده باشد');
        }
    });

    assert.equal(out[0].y, 0, 'بالای لباس سرِ جایش می‌ماند');
    assert.equal(out[out.length - 1].y, 129 * 0.8, 'پایین لباس هم');
});

test('رقیق‌کردن، بلندی و پهنای کلی لباس را عوض نمی‌کند', () => {
    const rings = ladder(60);
    const out = smooth(rings);

    assert.ok(Math.abs(out[out.length - 1].rx - rings[rings.length - 1].rx) < 1e-9);
    assert.ok(out.every((ring) => ring.rx > 9 && ring.rx < 17));
});

test('فهرست کوتاه دست‌نخورده برمی‌گردد', () => {
    assert.deepEqual(smooth([]), []);
    assert.equal(smooth(ladder(2)).length, 2);
});

test('زیر خط فاق، پوششِ بدن از دو پا حساب می‌شود نه از تنه', () => {
    const knee = body.level.crotch + (body.level.ankle - body.level.crotch) * 0.5;
    const hip = bodyEnvelope(body, body.level.hip);
    const below = bodyEnvelope(body, knee);

    assert.ok(below.rx < hip.rx, 'زانو باید باریک‌تر از باسن باشد');
    assert.ok(below.front > 0 && below.back > 0);

    // درست بالای فاق هنوز تنه است، نه پا
    assert.ok(bodyEnvelope(body, body.level.crotch - 1).rx > below.rx);
});

test('پاها پایین به هم نزدیک می‌شوند', () => {
    const top = body.leg[0];
    const bottom = body.leg[body.leg.length - 1];

    assert.ok(top.x > bottom.x, `ران‌ها باید از مچ پا بازتر باشند: ${top.x} در برابر ${bottom.x}`);
    assert.ok(bottom.x > bottom.r, 'ولی دو پا روی هم نمی‌افتند');
});

test('دو پا در هیچ ارتفاعی داخل هم نمی‌روند', () => {
    body.leg.forEach((row) => {
        assert.ok(row.x >= row.r * 0.95, `در ارتفاع ${row.y} پاها تو رفته‌اند`);
    });
});

test('دست بیرونِ تنه آویزان است', () => {
    const joint = armJoint(body);
    const arm = body.arm[0].r;

    /*
     * لبهٔ بیرونیِ بازو باید کمی از نوکِ شانه بیرون بزند — آدم از روی بازو
     * پهن‌تر است تا از روی سرشانه. و مرکزش نباید تا وسطِ سینه تو برود، وگرنه
     * لباس روی دست می‌افتد و شنل می‌شود.
     */
    assert.ok(joint.x + arm > body.shoulderHalf, 'بازو باید از نوکِ شانه بیرون بزند');
    assert.ok(joint.x > body.shoulderHalf * 0.7, 'ولی نه اینکه وسطِ تنه باشد');
    assert.ok(joint.y > body.level.shoulder, 'مفصل کمی پایین‌تر از خط سرشانه است');
});

test('دستِ آویزان رو به پایین کمی از بدن فاصله می‌گیرد', () => {
    const top = armCentre(body, 0);
    const wrist = armCentre(body, 58);

    assert.ok(wrist > top, 'مچ باید از شانه بازتر باشد');
    assert.ok(wrist - top < 8, 'ولی دست‌ها باز نشده‌اند');
    assert.equal(armCentre(body, -5), top, 'بالاتر از مفصل، مرکز عقب نمی‌رود');
});

test('عمق در جلو و پشت جدا خوانده می‌شود و در پهلو یکی می‌شود', () => {
    const ring = { rx: 10, front: 9, back: 6 };

    assert.equal(depthAt(ring, Math.PI / 2), 9, 'جلو');
    assert.equal(depthAt(ring, -Math.PI / 2), 6, 'پشت');

    // در پهلو هر دو نیمه z=0 می‌دهند، پس درزی نمی‌ماند
    assert.ok(Math.abs(depthAt(ring, 0) * Math.sin(0)) < 1e-12);
    assert.ok(Math.abs(depthAt(ring, Math.PI) * Math.sin(Math.PI)) < 1e-12);
});

test('میان‌یابیِ زنجیره در دو سرش گیر نمی‌کند', () => {
    assert.equal(chainAt(body.arm, -10), body.arm[0].r);
    assert.equal(chainAt(body.arm, 1e6), body.arm[body.arm.length - 1].r);
    assert.equal(chainAt([], 5), 0);

    const mid = chainAt(body.arm, body.arm[1].y);

    assert.ok(Math.abs(mid - body.arm[1].r) < 1e-9);
});

/*
 * برخوردگرهای بدن باید همان بدنی باشند که دیده می‌شود.
 *
 * اگر پوستِ نامرئی با پوستِ دیده‌شده یکی نباشد، پارچه جایی می‌ایستد که چیزی
 * آن‌جا نیست — و کسی نمی‌فهمد چرا. پس همان اندازه‌ها، همان ترازها.
 */
test('برخوردگرها بدن را کامل می‌پوشانند', async () => {
    const { drapeBody } = await import('../../resources/js/lib/mannequin.js');
    const { Collider } = await import('../../resources/js/lib/cloth-solver.js');

    const table = drapeBody(body);
    const parts = bodyColliders(Collider, body, table);
    const names = parts.map((one) => one.name);

    ['torso', 'neck', 'head', 'armL', 'armR', 'legL', 'legR'].forEach((name) => {
        assert.ok(names.includes(name), `${name} برخوردگر ندارد`);
    });

    parts.forEach((one) => {
        assert.ok(one.active, `${one.name} جای‌گذاری نشده`);

        for (let i = 1; i < one.ys.length; i++) {
            assert.ok(one.ys[i] > one.ys[i - 1], `${one.name}: مقطع‌ها صعودی نیستند`);
        }
    });

    /* تنه باید از فاق تا بالای گردن برسد */
    const torso = parts.find((one) => one.name === 'torso');

    assert.ok(torso.ys[0] <= table.level.crotch + 1e-6);
    assert.ok(torso.ys[torso.ys.length - 1] >= table.level.neck);
});

test('مقطعِ تنه جلو و پشتش را جدا می‌دهد', async () => {
    const { drapeBody } = await import('../../resources/js/lib/mannequin.js');
    const { Collider } = await import('../../resources/js/lib/cloth-solver.js');

    const table = drapeBody(body);
    const torso = bodyColliders(Collider, body, table).find((one) => one.name === 'torso');
    const out = [0, 0, 0];

    torso.sectionAt(table.level.hip, out);

    assert.ok(out[2] > out[1] + 0.005, `باسن باید پشت بزند: جلو ${out[1]}، پشت ${out[2]}`);

    torso.sectionAt(table.level.bust, out);

    assert.ok(out[1] > out[2] + 0.005, `سینه باید جلو بزند: جلو ${out[1]}، پشت ${out[2]}`);
});

/*
 * جدولِ بدن برای حل‌کننده باید همان بدنِ مانکن باشد، فقط به زبانِ دیگر: متر،
 * y رو به بالا، صفر روی زمین.
 */
test('ترجمهٔ بدن به دستگاهِ حل‌کننده وارونه نمی‌شود', async () => {
    const { drapeBody } = await import('../../resources/js/lib/mannequin.js');
    const table = drapeBody(body);

    assert.ok(Math.abs(table.level.top - body.height / 100) < 1e-9);
    assert.ok(table.level.ankle < table.level.knee, 'مچ پا پایین‌تر از زانوست');
    assert.ok(table.level.knee < table.level.crotch);
    assert.ok(table.level.waist < table.level.bust, 'کمر پایین‌تر از سینه است');
    assert.ok(table.level.bust < table.level.shoulder);
    assert.ok(table.level.shoulder < table.level.neck);
    assert.ok(table.level.neck < table.level.chin);
    assert.ok(table.level.chin < table.level.top);

    // نیم‌رخ از پایین به بالا مرتب است و هر سطر پنج عدد دارد
    table.profile.forEach((row, i) => {
        assert.equal(row.length, 5, 'هر سطرِ نیم‌رخ پنج عدد دارد');
        assert.ok(row.slice(1).every((v) => v > 0), 'شعاع مثبت است');

        if (i > 0) {
            assert.ok(row[0] > table.profile[i - 1][0], 'نیم‌رخ باید صعودی باشد');
        }
    });

    /* دور کمر همان دور کمر می‌ماند */
    const waist = table.radii.waist * 2 * Math.PI * 100;

    assert.ok(Math.abs(waist - 74) < 3, `دور کمرِ ترجمه‌شده ${waist.toFixed(1)} شد`);

    // جدول دست از مچ (منفی) تا مفصل (صفر) بالا می‌رود
    assert.ok(table.armTable[0][0] < 0);
    assert.ok(table.armTable[table.armTable.length - 1][0] <= 0);

    for (let i = 1; i < table.armTable.length; i++) {
        assert.ok(table.armTable[i][0] > table.armTable[i - 1][0], 'جدول دست صعودی است');
    }
});

/*
 * زیرِ بغل باید جا برای بازو باشد.
 *
 * «عرض سرشانه» تا نوکِ شانه است و بازو از همان‌جا آویزان می‌شود؛ یعنی آن عدد،
 * بازو را هم در خودش دارد. اگر تنه را تا همان پهنا ادامه بدهیم، بازو تویِ تنه
 * دفن می‌شود و آستین جایی برای نشستن ندارد: پارچه از هر دو طرف بیرون رانده
 * می‌شود. اندازه گرفته شد — مرکزِ آستین‌ها روی −۲۱٫۹ و +۲۴٫۱ می‌افتاد در حالی که
 * محورِ بازو ۱۷٫۸ بود، و حلقهٔ آستین همان‌قدر باز می‌ماند و بدن از میانش پیدا
 * بود. با باز شدنِ زیرِ بغل، آستین‌ها روی ±۱۸٫۷ نشستند.
 */
test('تنه زیرِ بغل برای بازو عقب می‌نشیند', async () => {
    const { drapeBody } = await import('../../resources/js/lib/mannequin.js');
    const table = drapeBody(body);
    const joint = armJoint(body);
    const inner = (joint.x - body.arm[0].r) / 100;

    /* در دستگاهِ حل‌کننده y رو به بالاست، پس سینه *پایین‌تر* از سرشانه است */
    const rows = table.profile.filter(
        ([y]) => y >= table.level.bust - 1e-6 && y <= table.level.shoulder + 1e-6,
    );

    assert.ok(rows.length >= 2, 'بازهٔ زیرِ بغل روی نیم‌رخ پیدا نشد');

    rows.forEach(([y, rx]) => {
        assert.ok(
            rx <= inner + 1e-9,
            `در ارتفاع ${(y * 100).toFixed(0)} تنه ${(rx * 100).toFixed(1)} پهناست و بازو از ${(inner * 100).toFixed(1)} شروع می‌شود`,
        );
    });

    /* ولی بیرونِ آن بازه، تنه همان تنه می‌ماند */
    const waist = table.profile.find(([y]) => Math.abs(y - table.level.waist) < 0.01);

    assert.ok(waist, 'تراز کمر روی نیم‌رخ نیست');
    assert.ok(waist[1] > inner * 0.5, 'کمر نباید بی‌دلیل باریک شده باشد');
});
