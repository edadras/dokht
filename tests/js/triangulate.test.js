/*
 * مثلث‌بندی باید سه چیز را تضمین کند و هر سه اینجا سنجیده می‌شوند:
 * مساحت (یعنی چیزی از قطعه کم و زیاد نشده)، جهت مثلث‌ها (یعنی هیچ مثلثی
 * وارونه یا صفر نیست) و نشانی رأس‌های مرزی (یعنی درز جای درستی دوخته می‌شود).
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { polygonArea, triangulate } from '../../resources/js/lib/pattern-drape.js';

/* حلقه‌ی آستین و گودی یقه: یک قطعه‌ی الگوی واقعاً مقعر */
const concavePiece = () => {
    const points = [[0, 9]];

    // گودی یقه، رو به داخلِ قطعه
    for (let i = 0; i <= 8; i++) {
        const t = i / 8;

        points.push([6 + 14 * t, 4 * Math.sin(Math.PI * t)]);
    }

    points.push([26, 6]);

    // حلقه‌ی آستین: کمانی که به داخل قطعه فرو رفته
    for (let i = 0; i <= 10; i++) {
        const t = i / 10;

        points.push([30 - 6 * Math.sin(Math.PI * t), 8 + 20 * t]);
    }

    points.push([34, 50], [0, 50]);

    return points;
};

const meshArea = (mesh) => {
    let total = 0;

    for (let t = 0; t < mesh.indices.length; t += 3) {
        const a = mesh.indices[t] * 2;
        const b = mesh.indices[t + 1] * 2;
        const c = mesh.indices[t + 2] * 2;

        total +=
            ((mesh.positions[b] - mesh.positions[a]) * (mesh.positions[c + 1] - mesh.positions[a + 1]) -
                (mesh.positions[b + 1] - mesh.positions[a + 1]) * (mesh.positions[c] - mesh.positions[a])) /
            2;
    }

    return total;
};

test('مساحت مثلث‌ها با مساحت چندضلعیِ مقعر یکی است', () => {
    const polygon = concavePiece();
    const mesh = triangulate(polygon, { target: 2.5 });
    const wanted = Math.abs(polygonArea(polygon));
    const got = meshArea(mesh);

    assert.ok(mesh.indices.length > 0, 'مثلثی ساخته نشد');
    assert.ok(
        Math.abs(got - wanted) / wanted < 0.01,
        `مساحت مش ${got.toFixed(3)} با چندضلعی ${wanted.toFixed(3)} بیش از یک درصد فرق دارد`,
    );
});

test('هیچ مثلثی وارونه یا با مساحت صفر نیست', () => {
    const mesh = triangulate(concavePiece(), { target: 2.5 });
    let worst = Infinity;

    for (let t = 0; t < mesh.indices.length; t += 3) {
        const a = mesh.indices[t] * 2;
        const b = mesh.indices[t + 1] * 2;
        const c = mesh.indices[t + 2] * 2;
        const area =
            ((mesh.positions[b] - mesh.positions[a]) * (mesh.positions[c + 1] - mesh.positions[a + 1]) -
                (mesh.positions[b + 1] - mesh.positions[a + 1]) * (mesh.positions[c] - mesh.positions[a])) /
            2;

        worst = Math.min(worst, area);
    }

    assert.ok(worst > 1e-9, `مثلثی با مساحت ${worst} پیدا شد`);
});

test('هر رأس چندضلعی نشانی خودش را در مش دارد', () => {
    const polygon = concavePiece();
    const mesh = triangulate(polygon, { target: 2.5 });

    assert.equal(mesh.boundary.length, polygon.length);

    for (let i = 0; i < polygon.length; i++) {
        const vertex = mesh.boundary[i];

        assert.ok(vertex >= 0, `رأس ${i} در مش نیست`);
        assert.ok(
            Math.hypot(
                mesh.positions[vertex * 2] - polygon[i][0],
                mesh.positions[vertex * 2 + 1] - polygon[i][1],
            ) < 1e-9,
            `رأس ${i} جابه‌جا شده است`,
        );
        assert.ok(mesh.slot[i] >= 0, `رأس ${i} روی حلقه‌ی مرزی نیست`);
        assert.equal(mesh.loop[mesh.slot[i]], vertex);
    }
});

test('مرز کامل برمی‌گردد و مثلثی بیرون چندضلعی نمی‌ماند', () => {
    const mesh = triangulate(concavePiece(), { target: 2 });

    assert.equal(mesh.unrecovered, 0, 'پاره‌خط مرزی جا افتاده است');
});

test('طول یال به target نزدیک می‌ماند', () => {
    const target = 3;
    const mesh = triangulate(
        [
            [0, 0],
            [40, 0],
            [40, 60],
            [0, 60],
        ],
        { target },
    );

    let sum = 0;
    let count = 0;

    for (let t = 0; t < mesh.indices.length; t += 3) {
        for (let e = 0; e < 3; e++) {
            const a = mesh.indices[t + e] * 2;
            const b = mesh.indices[t + ((e + 1) % 3)] * 2;

            sum += Math.hypot(mesh.positions[b] - mesh.positions[a], mesh.positions[b + 1] - mesh.positions[a + 1]);
            count++;
        }
    }

    const average = sum / count;

    assert.ok(
        average > target * 0.6 && average < target * 1.4,
        `میانگین طول یال ${average.toFixed(2)} از ${target} خیلی دور است`,
    );
});

test('چندضلعیِ خیلی کوچک یا خراب، خطای روشن می‌دهد', () => {
    assert.throws(() => triangulate([[0, 0], [1, 1]], { target: 1 }));
    assert.throws(() => triangulate([[0, 0], [0, 0], [0, 0]], { target: 1 }));
});
