/*
 * TriPatch باید همان چیزی باشد که ClothPatch بود، فقط روی مشِ مثلثی: پارچه زیر
 * وزن خودش می‌افتد ولی کش نمی‌آید و از تکیه‌گاهش کنده نمی‌شود.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { ClothWorld, TriPatch, fabricLaw } from '../../resources/js/lib/cloth-solver.js';
import { flatSheet, worstStretch } from './fixtures/sheet.js';

const hangingSheet = ({ target = 3 } = {}) => {
    const { flat, positions, grain } = flatSheet(
        [
            [0, 0],
            [40, 0],
            [40, 60],
            [0, 60],
        ],
        { target },
    );

    const count = flat.positions.length / 2;
    const pinned = new Uint8Array(count);

    // لبه‌ی بالا (y الگو نزدیک صفر) به سقف دوخته می‌شود
    for (let i = 0; i < count; i++) {
        if (flat.positions[i * 2 + 1] < 1e-6) {
            pinned[i] = 1;
        }
    }

    const patch = new TriPatch({
        positions,
        indices: flat.indices,
        grain,
        pinned,
        fabric: fabricLaw({}),
    });

    return { patch, pinned, flat };
};

test('ورقه‌ی آویزان بیش از چند درصد کش نمی‌آید', () => {
    const { patch } = hangingSheet();
    const world = new ClothWorld({ fabric: {} });

    world.addPatch(patch);
    world.presettle(240);

    const stretch = worstStretch(patch);

    assert.ok(stretch < 1.06, `بیشترین کشش ${((stretch - 1) * 100).toFixed(1)}٪ شد`);
});

test('وزن رأس‌ها از مساحت می‌آید و هیچ رأسِ آزادی بی‌وزن نمی‌ماند', () => {
    const { patch, pinned, flat } = hangingSheet();
    let free = 0;

    for (let i = 0; i < patch.count; i++) {
        if (pinned[i]) {
            assert.equal(patch.invMass[i], 0, 'رأس دوخته‌شده وزن‌دار شد');
        } else {
            assert.ok(patch.invMass[i] > 0, `رأس ${i} بی‌وزن ماند`);
            free++;
        }
    }

    assert.ok(free > 0);

    // رأس‌های میانی (شش مثلث دورشان) باید سنگین‌تر از رأس‌های لبه باشند
    let inner = 0;
    let edge = 0;
    let innerCount = 0;
    let edgeCount = 0;
    const onRim = new Uint8Array(patch.count);

    for (let i = 0; i < flat.loop.length; i++) {
        onRim[flat.loop[i]] = 1;
    }

    for (let i = 0; i < patch.count; i++) {
        if (patch.invMass[i] === 0) {
            continue;
        }

        if (onRim[i]) {
            edge += patch.invMass[i];
            edgeCount++;
        } else {
            inner += patch.invMass[i];
            innerCount++;
        }
    }

    if (innerCount && edgeCount) {
        assert.ok(inner / innerCount <= edge / edgeCount, 'رأس داخلی سبک‌تر از رأس لبه شد');
    }
});

test('قیدها روی هر یال و هر جفت مثلثِ همسایه ساخته می‌شوند', () => {
    const { patch, flat } = hangingSheet();
    const triangles = flat.indices.length / 3;
    const constraints = patch.groups.reduce((sum, group) => sum + group.rest.length, 0);

    // اویلر: برای مشِ ساده، یال ≈ ۳ت/۲ و جفت مثلث همسایه ≈ ت*۳/۲ − مرز
    assert.ok(patch.edgeCount > triangles, 'تعداد یال‌ها کمتر از تعداد مثلث‌هاست');
    assert.ok(constraints > patch.edgeCount, 'قید خمش ساخته نشده است');
    assert.equal(patch.triangles, triangles);
});

test('طول استراحت از الگوی تخت می‌آید، نه از جایی که قطعه چیده شده', () => {
    const { flat, positions, grain } = flatSheet(
        [
            [0, 0],
            [20, 0],
            [20, 20],
            [0, 20],
        ],
        { target: 4 },
    );

    // همان مش، ولی چیده‌شده روی یک استوانه‌ی تنگ: فاصله‌های سه‌بعدی کوتاه‌ترند
    const bent = new Float32Array(positions);

    for (let i = 0; i < bent.length / 3; i++) {
        const angle = grain[i * 2] * 12;

        bent[i * 3] = 0.08 * Math.sin(angle);
        bent[i * 3 + 2] = 0.08 * Math.cos(angle);
    }

    const straight = new TriPatch({ positions, indices: flat.indices, grain, fabric: fabricLaw({}) });
    const curved = new TriPatch({ positions: bent, indices: flat.indices, grain, fabric: fabricLaw({}) });

    for (let g = 0; g < straight.groups.length; g++) {
        assert.deepEqual(
            Array.from(curved.groups[g].rest),
            Array.from(straight.groups[g].rest),
            'طول استراحت به چیدنِ اولیه وابسته شد',
        );
    }
});
