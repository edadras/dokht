/*
 * درز باید دو کار را با هم بکند: قطعه‌ها را واقعاً به هم برساند، و در راه
 * رساندنشان مش را پاره نکند. آزمون‌های زیر دقیقاً همین دو تا را می‌سنجند.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { ClothWorld, SeamSet, TriPatch, fabricLaw } from '../../resources/js/lib/cloth-solver.js';
import { buildDrape } from '../../resources/js/lib/pattern-drape.js';
import { flatSheet, worstStretch } from './fixtures/sheet.js';
import { makeBody, twoSquares } from './fixtures/payload.js';

const square = (offsetX) => {
    const { flat, positions, grain } = flatSheet(
        [
            [0, 0],
            [10, 0],
            [10, 10],
            [0, 10],
        ],
        { target: 2 },
    );

    for (let i = 0; i < positions.length / 3; i++) {
        positions[i * 3] += offsetX;
    }

    return {
        flat,
        patch: new TriPatch({ positions, indices: flat.indices, grain, fabric: fabricLaw({}) }),
    };
};

/* رأس‌های یک لبه‌ی عمودی، مرتب‌شده بر اساس ارتفاع */
const edgeOf = (flat, x) => {
    const found = [];

    for (let i = 0; i < flat.loop.length; i++) {
        const v = flat.loop[i];

        if (Math.abs(flat.positions[v * 2] - x) < 1e-6) {
            found.push(v);
        }
    }

    return found.sort((a, b) => flat.positions[a * 2 + 1] - flat.positions[b * 2 + 1]);
};

test('دو مربع جدا با یک درز روی هم می‌افتند', () => {
    const left = square(0);
    const right = square(0.24);
    const leftEdge = edgeOf(left.flat, 10);
    const rightEdge = edgeOf(right.flat, 0);
    const pairs = [];

    for (let i = 0; i < leftEdge.length; i++) {
        pairs.push(leftEdge[i], rightEdge[i]);
    }

    const seam = new SeamSet({ a: left.patch, b: right.patch, pairs, label: 'درز آزمایشی' });

    assert.ok(seam.error() > 0.1, 'دو مربع از همان اول به هم چسبیده‌اند');

    const world = new ClothWorld({ fabric: {} });

    world.addPatch(left.patch);
    world.addPatch(right.patch);
    world.addSeam(seam);
    world.presettle(200);

    assert.ok(seam.error() < 0.002, `میانگین فاصله‌ی جفت‌ها ${(seam.error() * 1000).toFixed(2)} میلی‌متر ماند`);
    assert.ok(worstStretch(left.patch) < 1.15, 'مش سمت چپ زیر کشش درز پاره شد');
    assert.ok(worstStretch(right.patch) < 1.15, 'مش سمت راست زیر کشش درز پاره شد');
});

test('درز بی‌پرش می‌رسد: هیچ رأسی در یک گام بیش از چند میلی‌متر نمی‌پرد', () => {
    const left = square(0);
    const right = square(0.24);
    const leftEdge = edgeOf(left.flat, 10);
    const rightEdge = edgeOf(right.flat, 0);
    const pairs = [];

    for (let i = 0; i < leftEdge.length; i++) {
        pairs.push(leftEdge[i], rightEdge[i]);
    }

    const world = new ClothWorld({ fabric: {} });

    // جاذبه خاموش می‌شود تا آنچه اندازه می‌گیریم فقط کارِ درز باشد؛ سقوط آزادِ
    // دو مربعِ بی‌تکیه‌گاه خودش به تنهایی از این آستانه رد می‌شود
    world.law.gravity = 0;
    world.addPatch(left.patch);
    world.addPatch(right.patch);
    world.addSeam(new SeamSet({ a: left.patch, b: right.patch, pairs }));

    const before = new Float32Array(left.patch.positions);
    let worst = 0;

    for (let step = 0; step < 200; step++) {
        before.set(left.patch.positions);
        world.stepOnce(1 / 60);

        for (let i = 0; i < left.patch.count; i++) {
            worst = Math.max(
                worst,
                Math.hypot(
                    left.patch.positions[i * 3] - before[i * 3],
                    left.patch.positions[i * 3 + 1] - before[i * 3 + 1],
                    left.patch.positions[i * 3 + 2] - before[i * 3 + 2],
                ),
            );
        }
    }

    assert.ok(worst < 0.01, `یک رأس در یک گام ${(worst * 1000).toFixed(1)} میلی‌متر پرید`);
});

test('شدت درز از صفر شروع می‌شود و به یک می‌رسد', () => {
    const left = square(0);
    const seam = new SeamSet({ a: left.patch, pairs: [0, 1], duration: 0.5 });

    assert.equal(seam.strength, 0);
    seam.advance(0.25);
    assert.ok(seam.strength > 0.4 && seam.strength < 0.6);
    seam.advance(10);
    assert.equal(seam.strength, 1);

    seam.reset();
    assert.equal(seam.strength, 0);
    seam.weld();
    assert.equal(seam.strength, 1);
});

test('جفت‌سازی با کسر طول کمانی کار می‌کند، نه با شماره‌ی رأس', () => {
    // دو لبه با ریزی متفاوت: یکی با یال ۲ سانتی و دیگری با یال ۰.۸ سانتی
    const payload = twoSquares();

    payload.budget.target_edge = 2;

    const coarse = buildDrape(payload, makeBody(), {});
    const fine = buildDrape({ ...payload, budget: { target_edge: 0.9, max_vertices: 6000 } }, makeBody(), {});

    assert.equal(coarse.seams.length, 1);
    assert.equal(fine.seams.length, 1);
    assert.ok(fine.seams[0].count > coarse.seams[0].count, 'مش ریزتر سوزن بیشتری نگرفت');

    for (const drape of [coarse, fine]) {
        const world = new ClothWorld({ fabric: {} });

        drape.patches.forEach((entry) => world.addPatch(entry.patch));
        drape.seams.forEach((seam) => world.addSeam(seam));
        world.presettle(220);

        assert.ok(
            drape.seams[0].error() < 0.002,
            `درز با ${drape.seams[0].count} سوزن ${(drape.seams[0].error() * 1000).toFixed(2)} میلی‌متر باز ماند`,
        );
    }
});

test('هر رأسِ دو لبه دست‌کم یک سوزن می‌خورد', () => {
    const drape = buildDrape(twoSquares(), makeBody(), {});
    const seam = drape.seams[0];
    const sideA = new Set();
    const sideB = new Set();

    for (let i = 0; i < seam.count; i++) {
        sideA.add(seam.pairs[i * 2]);
        sideB.add(seam.pairs[i * 2 + 1]);
    }

    // لبه‌ی ۱۰ سانتی با یال ۲ سانتی، شش رأس دارد
    assert.ok(sideA.size >= 6, `سمت a فقط ${sideA.size} رأس دوخته شد`);
    assert.ok(sideB.size >= 6, `سمت b فقط ${sideB.size} رأس دوخته شد`);
});
