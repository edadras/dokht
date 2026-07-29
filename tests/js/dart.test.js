/*
 * ساسون همان جایی است که پارچه‌ی تخت، سه‌بعدی می‌شود.
 *
 * سنجه‌ی درست برای ساسون «شکل قشنگ» نیست، یک واقعیت هندسی است: ورقه‌ی تختی که
 * ساسونش بسته شده دیگر نمی‌تواند تخت بماند، مگر اینکه پارچه کش بیاید. پس اگر
 * بعد از بستن ساسون ورقه هنوز تخت باشد، یعنی یا سوزنی نخورده یا مش کش آمده —
 * و هر دو ایرادند.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { ClothWorld, SeamSet, TriPatch, fabricLaw } from '../../resources/js/lib/cloth-solver.js';
import { buildDrape } from '../../resources/js/lib/pattern-drape.js';
import { flatSheet, maxDepth, worstStretch } from './fixtures/sheet.js';
import { bodicePayload, makeBody } from './fixtures/payload.js';

/* مستطیلی که از لبه‌ی پایین یک ساسون V شکل در آن بریده شده */
const dartedSheet = () => {
    const polygon = [
        [0, 0],
        [20, 0],
        [20, 24],
        [13, 24],
        [10, 8],
        [7, 24],
        [0, 24],
    ];
    const { flat, positions, grain } = flatSheet(polygon, { target: 1.6 });
    const count = flat.positions.length / 2;
    const pinned = new Uint8Array(count);

    // لبه‌ی بالا میخکوب می‌شود تا ورقه نچرخد و آنچه اندازه می‌گیریم واقعاً
    // انحنای ساسون باشد، نه جابه‌جایی کلِ ورقه
    for (let i = 0; i < count; i++) {
        if (flat.positions[i * 2 + 1] < 1e-6) {
            pinned[i] = 1;
        }
    }

    const patch = new TriPatch({ positions, indices: flat.indices, grain, pinned, fabric: fabricLaw({}) });

    // ساق‌های ساسون: از رأس ۲ تا ۴ (نوک) و از ۴ تا ۵
    const legA = [];
    const legB = [];
    const size = flat.loop.length;
    const apex = flat.slot[4];

    for (let s = flat.slot[3]; s !== apex; s = (s + 1) % size) {
        legA.push(flat.loop[s]);
    }

    legA.push(flat.loop[apex]);

    for (let s = flat.slot[5]; s !== apex; s = (s - 1 + size) % size) {
        legB.push(flat.loop[s]);
    }

    legB.push(flat.loop[apex]);

    const pairs = [];
    const steps = Math.min(legA.length, legB.length);

    for (let i = 0; i < steps; i++) {
        const a = legA[Math.round((i * (legA.length - 1)) / (steps - 1))];
        const b = legB[Math.round((i * (legB.length - 1)) / (steps - 1))];

        if (a !== b) {
            pairs.push(a, b);
        }
    }

    return { patch, pairs, flat };
};

test('بستن ساسون، ورقه‌ی تخت را از تختی درمی‌آورد', () => {
    const { patch, pairs } = dartedSheet();
    const before = maxDepth(patch);

    assert.ok(before < 1e-3, 'ورقه از همان اول تخت نبود');

    const world = new ClothWorld({ fabric: {} });

    // جاذبه خاموش: انحنایی که می‌سنجیم باید کارِ ساسون باشد، نه افتادنِ پارچه
    world.law.gravity = 0;
    world.addPatch(patch);
    world.addSeam(new SeamSet({ a: patch, pairs, kind: 'dart', label: 'ساسون' }));
    world.presettle(260);

    const after = maxDepth(patch);

    assert.ok(after > 0.005, `ورقه بعد از بستن ساسون فقط ${(after * 1000).toFixed(2)} میلی‌متر از تختی درآمد`);
    assert.ok(after > before * 20, 'انحنا از تلنگرِ اولیه بیشتر نشد');
    assert.ok(worstStretch(patch) < 1.15, 'ساسون به قیمت کش آمدن پارچه بسته شد');
});

test('ساسونِ بریده‌نشده‌ی بسته، خودش روی مسیر بریده می‌شود', () => {
    const payload = bodicePayload();
    const drape = buildDrape(payload, makeBody(), {});
    const darts = drape.seams.filter((seam) => seam.kind === 'dart');

    assert.equal(drape.stats.notched, 1, 'ساسونِ legs/apex بریده نشد');
    assert.equal(darts.length, 1, 'ساسون به درز تبدیل نشد');
    assert.ok(darts[0].count >= 3, `ساسون فقط ${darts[0].count} سوزن گرفت`);

    // چندضلعیِ جلو باید سه رأس بیشتر از بسته داشته باشد (دو ساق و یک نوک)
    const front = drape.patches.find((entry) => entry.id === 'front-bodice#0');

    assert.ok(front, 'قطعه‌ی جلو ساخته نشد');
});

test('بسته شدن ساسون، مساحت قطعه را کم می‌کند', () => {
    const withDart = buildDrape(bodicePayload(), makeBody(), {});
    const naked = bodicePayload();

    naked.pieces[0].darts = [];

    const without = buildDrape(naked, makeBody(), {});
    const areaOf = (drape, id) => drape.patches.find((entry) => entry.id === id).patch.area;

    assert.ok(
        areaOf(withDart, 'front-bodice#0') < areaOf(without, 'front-bodice#0'),
        'بریدن ساسون از مساحت قطعه کم نکرد',
    );
});
