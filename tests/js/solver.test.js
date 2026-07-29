/*
 * دو چیز که هر بار باید کنترل شوند:
 *
 *   • ClothPatch — نمای پارامتریِ امروز روی آن سوار است. با آمدنِ TriPatch،
 *     کدِ مشترک یک پله بالاتر رفت؛ این آزمون‌ها گواهی می‌دهند که رفتارش عوض
 *     نشده. اگر روزی شکستند، یعنی لباس‌های موجود هم شکسته‌اند.
 *   • هزینه‌ی حلقه‌ی حل — یک لباس مجلسی حدود بیست قطعه دارد. اگر گامِ حل روی
 *     شش هزار رأس از بودجه‌ی سی‌فریم رد شود، باید همین‌جا بفهمیم، نه روی
 *     دستگاه کاربر.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { ClothPatch, ClothWorld, SeamSet, fabricLaw } from '../../resources/js/lib/cloth-solver.js';
import { buildDrape } from '../../resources/js/lib/pattern-drape.js';
import { bodicePayload, makeBody } from './fixtures/payload.js';

const cylinder = ({ rows = 10, segments = 16, radius = 0.2, height = 0.5 } = {}) => {
    const positions = new Float32Array(rows * segments * 3);
    const pinned = new Uint8Array(rows * segments);

    for (let row = 0; row < rows; row++) {
        for (let col = 0; col < segments; col++) {
            const at = (row * segments + col) * 3;
            const angle = (col / segments) * Math.PI * 2;

            positions[at] = radius * Math.cos(angle);
            positions[at + 1] = (row / (rows - 1)) * height;
            positions[at + 2] = radius * Math.sin(angle);
        }
    }

    for (let col = 0; col < segments; col++) {
        pinned[(rows - 1) * segments + col] = 1;
    }

    return new ClothPatch({ positions, rows, segments, pinned, fabric: fabricLaw({}) });
};

test('ClothPatch همان پنج خانواده‌ی قید و همان مهارها را می‌سازد', () => {
    const patch = cylinder();

    assert.equal(patch.groups.length, 5, 'خانواده‌های تار/پود/برش/دو خمش عوض شده‌اند');
    assert.equal(patch.count, 160);
    assert.equal(patch.pins.length, 16);
    assert.ok(patch.tetherMax.length > 0, 'مهار بلند ساخته نشد');
    assert.equal(
        patch.constraintCount,
        patch.groups.reduce((sum, group) => sum + group.rest.length, 0) + patch.tetherMax.length,
    );

    for (let i = 0; i < patch.count; i++) {
        assert.equal(patch.invMass[i], patch.pins.includes(i) ? 0 : 1, 'وزن رأس‌های شبکه عوض شده');
    }
});

test('ClothPatch هنوز می‌افتد، می‌نشیند و می‌خوابد', () => {
    const patch = cylinder();
    const world = new ClothWorld({ fabric: {} });

    world.addPatch(patch);
    world.presettle(60);

    const top = patch.positions[(9 * 16) * 3 + 1];

    assert.ok(Math.abs(top - 0.5) < 1e-6, 'رأس دوخته‌شده جابه‌جا شد');
    assert.ok(patch.positions[1] < 0.02, 'لبه‌ی پایین بالا رفت');

    for (let i = 0; i < 400 && ! world.sleeping; i++) {
        world.update(1 / 60);
    }

    assert.ok(world.sleeping, 'صحنه‌ی آرام نخوابید');
});

test('تا دوختن تمام نشده، صحنه نمی‌خوابد', () => {
    const patch = cylinder();
    const world = new ClothWorld({ fabric: {} });
    const seam = new SeamSet({ a: patch, pairs: [0, 1], duration: 4 });

    world.addPatch(patch);
    world.addSeam(seam);

    assert.equal(world.seams.length, 1);
    assert.ok(world.sewing);

    for (let i = 0; i < 120; i++) {
        world.update(1 / 60);
    }

    assert.ok(world.sewing, 'رمپ درز خیلی زود تمام شد');
    assert.ok(! world.sleeping, 'صحنه پیش از تمام شدن دوخت خوابید');

    while (world.sewing) {
        world.update(1 / 60);
    }

    assert.equal(seam.strength, 1);
});

test('گام حل روی شش هزار رأس از بودجه‌ی سی فریم رد نمی‌شود', () => {
    /*
     * یک لباس مجلسیِ ساختگی: بیست نمونه از همان بالاتنه، با درزهای خودشان. عددِ
     * مهم «رأس» است، نه «قطعه»؛ هزینه‌ی حل تقریباً خطیِ تعداد قید است.
     */
    const payload = bodicePayload();
    const pieces = [];
    const seams = [];

    for (let copy = 0; copy < 10; copy++) {
        for (const piece of payload.pieces) {
            if (piece.layer !== 'outer') {
                continue;
            }

            pieces.push({ ...piece, id: `${piece.id}~${copy}` });
        }

        for (const seam of payload.seams) {
            seams.push({
                ...seam,
                a: { ...seam.a, piece: `${seam.a.piece}~${copy}` },
                b: { ...seam.b, piece: `${seam.b.piece}~${copy}` },
            });
        }
    }

    const drape = buildDrape(
        { ...payload, pieces, seams, budget: { target_edge: 2.2, max_vertices: 6000 } },
        makeBody(),
        {},
    );

    assert.ok(drape.stats.vertices > 4500, `فقط ${drape.stats.vertices} رأس ساخته شد`);
    assert.ok(drape.stats.vertices <= 6000);

    const world = new ClothWorld({ fabric: {} });

    drape.patches.forEach((entry) => world.addPatch(entry.patch));
    drape.seams.forEach((seam) => world.addSeam(seam));

    // پیشنهاد خودِ buildDrape برای مشِ سنگین
    world.substeps = drape.stats.solver.substeps;
    world.iterations = drape.stats.solver.iterations;

    world.stepOnce(1 / 60);

    const started = performance.now();
    const steps = 20;

    for (let i = 0; i < steps; i++) {
        world.stepOnce(1 / 60);
    }

    const each = (performance.now() - started) / steps;

    assert.ok(
        each < 33,
        `هر گام ${each.toFixed(1)} میلی‌ثانیه شد؛ روی ${drape.stats.vertices} رأس و ${world.constraints} قید`,
    );
});
