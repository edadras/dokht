/*
 * لباس روی مانکن — بخش‌هایی که ریاضی‌اند، نه تصویری.
 *
 * «قشنگ دیده می‌شود» را تست نمی‌شود نوشت، ولی چیزهایی که این نما را خراب
 * می‌کنند همه شمردنی‌اند: حلقه‌ای که حذف شد و لباس مخروط شد، پایی که زیر دامن
 * نبود، دستی که وسطِ قفسهٔ سینه فرو رفته بود. همان‌ها این‌جا بسته می‌شوند.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { buildBody } from '../../resources/js/lib/mannequin.js';
import {
    armCentre, armJoint, bodyEnvelope, chainAt, depthAt, smooth,
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
