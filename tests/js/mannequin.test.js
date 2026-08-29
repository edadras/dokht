/*
 * مانکن باید بدنِ همان مشتری باشد، نه یک بدنِ متوسط با اندازه‌های او.
 *
 * سنجهٔ درستش هم «شبیه آدم است» نیست — این را چشم می‌گوید نه تست. چیزی که تست
 * می‌تواند بگوید این است: هر عددی که مشتری داده باید جایی در بدن اثر بگذارد، و
 * اثرش باید همان‌جایی باشد که در آدم واقعی هست. دور سینه که بزرگ‌تر شد، باید
 * *جلوی* سینه جلو بیاید نه پشتش؛ باسن که بزرگ‌تر شد، پشت. اگر این دو با هم عوض
 * شوند، بدن باز هم قرینه است و لباس روی یک لوله دیده می‌شود.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { buildBody, girthOf, minorAxis, perimeter, sampleRing } from '../../resources/js/lib/mannequin.js';

/* یک زنِ بزرگسال با اندام معمول */
const woman = {
    height: 168, bust: 92, under_bust: 78, waist: 74, hip: 100,
    shoulder_width: 39, back_length: 41, waist_to_hip: 21, inseam: 76, arm_length: 58,
};

/* همان قد و همان دورها، ولی قفسهٔ صاف — یعنی مردانه */
const man = {
    height: 178, bust: 100, under_bust: 98, waist: 88, hip: 98,
    shoulder_width: 45, back_length: 45, waist_to_hip: 22, inseam: 82, arm_length: 62,
};

/* مردی با شکم: کمرش از زیرسینه‌اش بزرگ‌تر است */
const bellied = {
    height: 174, bust: 108, under_bust: 104, waist: 114, hip: 106,
    shoulder_width: 45, back_length: 44, waist_to_hip: 21, inseam: 78, arm_length: 60,
};

/* کودک: قد کوتاه و دورهای نزدیک به هم */
const child = {
    height: 116, bust: 60, under_bust: 58, waist: 56, hip: 62,
    shoulder_width: 28, back_length: 28, waist_to_hip: 13, inseam: 52, arm_length: 40,
};

const ringAt = (body, y) => sampleRing(body.torso, y);

test('محیط بیضی با شعاع برابر همان دایره درمی‌آید', () => {
    assert.ok(Math.abs(perimeter(5, 5) - 2 * Math.PI * 5) < 1e-9);
});

test('نیم‌قطر از دور برمی‌گردد؛ دور دوباره همان می‌شود', () => {
    const b = minorAxis(92, 1.45);

    assert.ok(Math.abs(perimeter(b * 1.45, b) - 92) < 1e-9);
});

test('سینه به جلو می‌آید، نه به پشت', () => {
    const body = buildBody(woman);
    const bust = ringAt(body, body.level.bust);

    assert.ok(bust.front > bust.back + 1, `سینه باید جلو بزند: ${bust.front} در برابر ${bust.back}`);
});

test('قفسهٔ صاف سینه‌ای جلو نمی‌آورد', () => {
    const bust = ringAt(buildBody(man), buildBody(man).level.bust);

    assert.ok(bust.front <= bust.back + 0.05, 'با اختلاف سینه و زیرسینهٔ دو سانت نباید برجستگی بسازد');
});

test('باسن به پشت می‌رود', () => {
    const body = buildBody(woman);
    const hip = ringAt(body, body.level.hip);

    assert.ok(hip.back > hip.front + 1, `باسن باید پشت بزند: ${hip.back} در برابر ${hip.front}`);
});

test('شکمِ بزرگ‌تر از زیرسینه، کمر را جلو می‌برد', () => {
    const heavy = buildBody(bellied);
    const slim = buildBody(woman);
    const belly = ringAt(heavy, heavy.level.waist);
    const flat = ringAt(slim, slim.level.waist);

    assert.ok(belly.front > belly.back + 0.5, 'کمرِ کسی که کمرش از زیرسینه‌اش بزرگ‌تر است باید جلو بیاید');
    assert.ok(Math.abs(flat.front - flat.back) < 0.6, 'کمرِ باریک تقریباً قرینه می‌ماند');
});

test('برجستگی دور را باد نمی‌کند؛ فقط جابه‌جایش می‌کند', () => {
    const body = buildBody(woman);
    const bust = ringAt(body, body.level.bust);
    const symmetric = perimeter(bust.rx, (bust.front + bust.back) / 2);

    // دورِ اندازه‌گرفته‌شده ۹۲ است؛ برجستگی نباید بیش از یکی‌دو درصد جابه‌جایش کند
    assert.ok(Math.abs(girthOf(bust) - 92) < 92 * 0.03, `دور سینه: ${girthOf(bust)}`);
    assert.ok(Math.abs(symmetric - 92) < 1e-6);
});

test('سرِ کودک نسبت به قدش بزرگ‌تر از سرِ بزرگسال است', () => {
    const kid = buildBody(child);
    const adult = buildBody(woman);

    const kidRatio = (kid.head.radius * 2) / kid.height;
    const adultRatio = (adult.head.radius * 2) / adult.height;

    assert.ok(kid.childish > 0.5, `کودکانگی باید بالا باشد: ${kid.childish}`);
    assert.ok(adult.childish < 0.1, `بزرگسال نباید کودکانه شمرده شود: ${adult.childish}`);
    assert.ok(kidRatio > adultRatio + 0.02, `${kidRatio} باید از ${adultRatio} بزرگ‌تر باشد`);
});

test('ترازها از بالا به پایین مرتب‌اند و هیچ‌کدام روی هم نمی‌افتند', () => {
    [woman, man, bellied, child].forEach((m) => {
        const body = buildBody(m);

        body.torso.forEach((ring, i) => {
            if (i === 0) {
                return;
            }

            assert.ok(ring.y > body.torso[i - 1].y, 'ترازهای تنه باید صعودی باشند');
            assert.ok(ring.rx > 0 && ring.front > 0 && ring.back > 0, 'هیچ تراز صفر یا منفی نیست');
        });

        [body.arm, body.leg].forEach((chain) => {
            chain.forEach((row, i) => {
                assert.ok(row.r > 0, 'شعاع دست و پا مثبت است');

                if (i > 0) {
                    assert.ok(row.y > chain[i - 1].y, 'زنجیرهٔ دست و پا صعودی است');
                }
            });
        });

        assert.ok(body.level.neck < body.level.shoulder);
        assert.ok(body.level.shoulder < body.level.bust);
        assert.ok(body.level.bust < body.level.waist);
        assert.ok(body.level.waist < body.level.hip);
        assert.ok(body.level.hip < body.level.crotch);
        assert.ok(body.level.crotch < body.level.ankle);
        assert.ok(body.level.ankle < body.height * 1.02, 'قدِ کلِ مانکن نباید از قدِ مشتری بیشتر شود');
    });
});

test('گردن از داخل چانه شروع می‌شود تا میان سر و شانه شکاف نماند', () => {
    const body = buildBody(woman);
    const headBottom = body.head.centre + body.head.radius * 1.12;

    assert.ok(body.torso[0].y < headBottom, 'اولین تراز تنه باید بالاتر از پایینِ سر باشد');
    assert.ok(body.torso[0].y < body.level.neck);
});

test('میان‌یابی، عمقِ جلو و پشت را جدا از هم می‌خواند', () => {
    const body = buildBody(woman);
    const a = ringAt(body, body.level.waist);
    const b = ringAt(body, body.level.hip);
    const mid = ringAt(body, (body.level.waist + body.level.hip) / 2);

    assert.ok(mid.front > Math.min(a.front, b.front) - 1e-9);
    assert.ok(mid.front < Math.max(a.front, b.front) + 1e-9);
    assert.ok(mid.back > Math.min(a.back, b.back) - 1e-9);
    assert.ok(mid.back < Math.max(a.back, b.back) + 1e-9);
});

test('دورِ هر تراز همان اندازهٔ داده‌شده می‌ماند', () => {
    const body = buildBody(woman);

    [['bust', 92], ['waist', 74], ['hip', 100]].forEach(([key, expected]) => {
        const girth = girthOf(ringAt(body, body.level[key]));

        assert.ok(Math.abs(girth - expected) < expected * 0.04, `${key}: ${girth} در برابر ${expected}`);
    });
});

test('سه بدن، سه شکلِ متفاوت — بی‌آنکه جایی نامشان برود', () => {
    const shape = (m) => {
        const body = buildBody(m);
        const bust = ringAt(body, body.level.bust);
        const hip = ringAt(body, body.level.hip);

        return {
            chest: bust.front - bust.back,
            seat: hip.back - hip.front,
            head: (body.head.radius * 2) / body.height,
        };
    };

    const w = shape(woman);
    const g = shape(man);
    const k = shape(child);

    assert.ok(w.chest > g.chest + 1, 'قفسهٔ زن از مرد جلوتر است');
    assert.ok(w.seat > g.seat, 'باسنِ زن بیشتر عقب می‌زند');
    assert.ok(k.head > w.head && k.head > g.head, 'سرِ کودک نسبتاً بزرگ‌ترین است');
});
