/*
 * پارچه هرگز نباید داخل بدن بماند.
 *
 * سنجه با همان تابعی گرفته می‌شود که خودِ حل‌کننده با آن تصمیم می‌گیرد: نقطه در
 * دستگاه محلیِ برخوردگر روی بیضی نرمال می‌شود و اگر فاصله‌اش از مرکز کمتر از یک
 * باشد، یعنی داخل بدن است. اینجا کمی حاشیه داده شده تا لرزشِ ریزِ عددی، آزمون
 * را الکی نشکند.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { ClothWorld, Collider, SeamSet, TriPatch, fabricLaw } from '../../resources/js/lib/cloth-solver.js';
import { flatSheet } from './fixtures/sheet.js';

const IDENTITY = new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]);

/* چقدر این رأس داخل برخوردگر است؛ ۰ یعنی روی پوست، منفی یعنی داخل */
const clearance = (collider, x, y, z) => {
    const section = [0, 0];
    const side = collider.sectionAt(y, section);

    let dy = 0;
    let ry = 0;

    if (side < 0) {
        ry = collider.capLow;
        dy = y - collider.ys[0];
    } else if (side > 0) {
        ry = collider.capHigh;
        dy = y - collider.ys[collider.ys.length - 1];
    }

    const ux = x / section[0];
    const uz = z / section[1];
    const uy = ry > 0 ? dy / ry : 0;

    return Math.sqrt(ux * ux + uy * uy + uz * uz) - 1;
};

test('پارچه بعد از نشستن داخل برخوردگر نمی‌ماند', () => {
    const { flat, positions, grain } = flatSheet(
        [
            [0, 0],
            [36, 0],
            [36, 40],
            [0, 40],
        ],
        { target: 2.5 },
    );

    const count = flat.positions.length / 2;

    // ورقه را افقی و بالای یک تنه‌ی بیضوی می‌گذاریم تا رویش بیفتد
    for (let i = 0; i < count; i++) {
        positions[i * 3] = flat.positions[i * 2] * 0.01 - 0.18;
        positions[i * 3 + 1] = 0.42;
        positions[i * 3 + 2] = flat.positions[i * 2 + 1] * 0.01 - 0.2;
    }

    const collider = new Collider({
        name: 'تنه',
        sections: [
            [0, 0.14, 0.1],
            [0.15, 0.12, 0.09],
            [0.3, 0.15, 0.1],
        ],
    });

    collider.setTransform(IDENTITY, IDENTITY, 0.05);

    const patch = new TriPatch({ positions, indices: flat.indices, grain, fabric: fabricLaw({}) });
    const world = new ClothWorld({ fabric: {} });

    world.addPatch(patch);
    world.setColliders([collider]);
    world.presettle(400);

    let worst = 0;
    let inside = 0;

    for (let i = 0; i < patch.count; i++) {
        const depth = clearance(collider, patch.positions[i * 3], patch.positions[i * 3 + 1], patch.positions[i * 3 + 2]);

        if (depth < 0) {
            inside++;
            worst = Math.min(worst, depth);
        }
    }

    assert.equal(inside, 0, `${inside} رأس داخل بدن ماند (بدترین ${worst.toFixed(4)})`);
});

test('درز هم نمی‌تواند پارچه را داخل بدن بکشد', () => {
    const sheet = (offset) => {
        const { flat, positions, grain } = flatSheet(
            [
                [0, 0],
                [16, 0],
                [16, 24],
                [0, 24],
            ],
            { target: 2 },
        );

        for (let i = 0; i < positions.length / 3; i++) {
            positions[i * 3] = positions[i * 3] * 0 + offset * 0.24;
            positions[i * 3 + 2] = flat.positions[i * 2] * 0.01 - 0.08;
            positions[i * 3 + 1] = 0.3 - flat.positions[i * 2 + 1] * 0.01;
        }

        return { flat, patch: new TriPatch({ positions, indices: flat.indices, grain, fabric: fabricLaw({}) }) };
    };

    // دو ورقه‌ی رو‌به‌روی هم، دو طرف یک استوانه؛ درز می‌خواهد آن‌ها را از میان
    // استوانه به هم برساند
    const left = sheet(-1);
    const right = sheet(1);
    const pairs = [];

    for (let i = 0; i < Math.min(left.patch.count, right.patch.count); i++) {
        pairs.push(i, i);
    }

    const collider = new Collider({
        name: 'تنه',
        sections: [
            [0, 0.12, 0.1],
            [0.32, 0.12, 0.1],
        ],
        capMin: false,
        capMax: false,
    });

    collider.setTransform(IDENTITY, IDENTITY, 0.05);

    const world = new ClothWorld({ fabric: {} });

    world.addPatch(left.patch);
    world.addPatch(right.patch);
    world.addSeam(new SeamSet({ a: left.patch, b: right.patch, pairs }));
    world.setColliders([collider]);
    world.presettle(300);

    for (const patch of [left.patch, right.patch]) {
        for (let i = 0; i < patch.count; i++) {
            const y = patch.positions[i * 3 + 1];

            if (y < 0 || y > 0.32) {
                continue;
            }

            const depth = clearance(collider, patch.positions[i * 3], y, patch.positions[i * 3 + 2]);

            assert.ok(depth >= 0, `رأس ${i} با فاصله‌ی ${depth.toFixed(4)} داخل بدن رفت`);
        }
    }
});
