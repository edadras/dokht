/*
 * لباسِ دوخته‌شده روی مانکن.
 *
 * این نما با «دوخت مجازی» فرق دارد و عمداً هم فرق دارد. آن‌جا پارچه شبیه‌سازی
 * می‌شود: قطعه‌ها دوخته می‌شوند، زیر وزن خودشان می‌افتند و به بدن برخورد
 * می‌کنند. این‌جا حل‌کننده‌ای در کار نیست؛ همان لباسی که در نمای دوبعدی دیده
 * شد، دور بدن نشان داده می‌شود.
 *
 * شکلِ لباس از سرور می‌آید: در هر ارتفاع، نیم‌پهنا و نیم‌ضخامتِ لباسِ دوخته‌شده،
 * اندازه‌گرفته‌شده از خودِ قطعه‌های الگو پس از بستن ساسون‌ها. همان اعدادی که
 * نمای جلو و پشت و پهلو را ساختند. پس آنچه روی مانکن دیده می‌شود نمی‌تواند با
 * نمای دوبعدی نخواند.
 *
 * چند چیز باعث می‌شود این «لولهٔ رنگی» نباشد بلکه لباس دیده شود:
 *
 *   ۱) مانکن یک آدمِ کامل است، نه چند بیضیِ روی هم: سر و گردن و سرشانهٔ شیب‌دار،
 *      دو دستِ باریک‌شونده و دو پای جدا — همه از اندازه‌های همان مشتری. و قرینه
 *      هم نیست: سینه جلو می‌زند و باسن پشت (mannequin.js).
 *
 *   ۲) لباس شکلِ همان بدن را می‌گیرد. دورِ هر تراز از الگو می‌آید و دست‌نخورده
 *      می‌ماند، ولی جای پارچه جابه‌جا می‌شود تا برجستگی‌های بدن از زیرش پیدا
 *      باشند — و هرچه لباس تنگ‌تر، بیشتر.
 *
 *   ۳) لباس از جای درستش آویزان است: پیراهن از سرشانه، شلوار از کمر. و شلوار
 *      دو پاچه دارد، نه یک لوله.
 *
 *   ۴) پارچه چین می‌خورد، و چینش ساختگی نیست: هر جا لباس از بدن گشادتر است،
 *      آن پارچهٔ اضافه باید جایی برود. دامنهٔ چین از همان اختلاف درمی‌آید.
 *      روی سرشانه صفر است (آن‌جا لباس آویزان و کشیده است) و رو به پایین باز
 *      می‌شود، همان‌طور که پارچه می‌افتد.
 *
 *   ۵) نورپردازی و سایهٔ زمین، تا حجم دیده شود. بدون این هر چیزی تخت است.
 */

import { armCentre, armJoint, buildBody, drapeBody, girthOf, sampleRing } from '../lib/mannequin.js';

let THREE = null;

/* اشیای three از دسترسِ واکنشیِ آلپاین دور نگه داشته می‌شوند */
const contexts = new WeakMap();

const contextFor = (element) => {
    if (! contexts.has(element)) {
        contexts.set(element, { disposables: [] });
    }

    return contexts.get(element);
};

/* سانتی‌متر به متر */
const CM = 0.01;

/*
 * چند ضلعی در هر حلقه.
 *
 * این عدد باید چند برابرِ شمارِ چین‌ها باشد. با شصت‌وچهار ضلع و پانزده چین، هر
 * چین چهار ضلع می‌گرفت و به‌جای پارچه، راه‌راهِ مخملِ کبریتی درمی‌آمد.
 */
const SIDES = 96;

const clamp = (value, low, high) => Math.max(low, Math.min(high, value));

const lerp = (a, b, t) => a + (b - a) * t;

/* کمترین فاصلهٔ پارچه از پوست؛ کمتر از این، بدن از لباس بیرون می‌زند */
const SKIN_GAP = 0.35;

/* ماتریسِ همانی — بدنِ این نما تکان نمی‌خورد، پس پین‌ها همان‌جا می‌مانند */
const IDENTITY = new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]);

const track = (ctx, item) => {
    ctx.disposables.push(item);

    return item;
};

/*
 * رنگِ نخ: همان رنگِ پارچه، تیره‌تر.
 *
 * خیاط نخ را هم‌رنگِ پارچه می‌خرد و همان کمی تیره‌تر دیده می‌شود، چون نخ تابیده
 * است و نور را مثل پارچه پس نمی‌دهد. نخِ سیاه روی پارچهٔ روشن، «کوکِ تزیینی»
 * است نه درزدوزی.
 */
const threadColour = (colour) => {
    const shade = colour && colour.clone ? colour.clone() : null;

    return shade ? shade.multiplyScalar(0.42) : 0x3a322b;
};

/** حلقه‌ای دایره‌ای — دست و پا و آستین که جلو و پشتشان فرقی ندارد. */
const round = (y, r, x = 0) => ({ y, rx: r, front: r, back: r, x });

/** یک مقدار روی زنجیرهٔ دست یا پا (شعاع یا مرکز)، در ارتفاع دلخواه. */
export const chainAt = (rows, y, key = 'r') => {
    if (rows.length === 0) {
        return 0;
    }

    if (y <= rows[0].y) {
        return rows[0][key] || 0;
    }

    for (let i = 1; i < rows.length; i++) {
        if (y <= rows[i].y) {
            const span = rows[i].y - rows[i - 1].y || 1;

            return lerp(rows[i - 1][key] || 0, rows[i][key] || 0, (y - rows[i - 1].y) / span);
        }
    }

    return rows[rows.length - 1][key] || 0;
};

/**
 * پوستِ بدن در هر ارتفاع — بالای فاق تنه، پایین‌ترش دو پا.
 *
 * پایینِ فاق تنه‌ای در کار نیست، و اگر همان حلقهٔ فاق را ادامه بدهیم دامن و
 * شلوار روی یک استوانهٔ پهنِ نبوده می‌نشینند. پس آن‌جا پوشش از دو پا حساب
 * می‌شود: نیم‌پهنا تا لبهٔ بیرونیِ پا و عمق به اندازهٔ خودِ پا.
 */
export const bodyEnvelope = (body, y) => {
    if (y <= body.level.crotch) {
        return sampleRing(body.torso, y);
    }

    const local = y - body.level.crotch;
    const r = chainAt(body.leg, local);

    return { y, rx: chainAt(body.leg, local, 'x') + r, front: r, back: r };
};



/**
 * برخوردگرهای بدن، برای حل‌کنندهٔ پارچه.
 *
 * پارچه باید به چیزی بخورد، وگرنه از روی بدن رد می‌شود. همان مانکن، این بار
 * به زبانِ حل‌کننده: تنه (با جلو و پشتِ جدا، تا سینه و شکم از زیرِ پارچه پیدا
 * باشند)، گردن، سر، دو بازو و دو پا.
 *
 * دستگاهِ حل‌کننده متر است و y رو به بالا با صفرِ روی زمین، پس همه‌جا از
 * drapeBody خوانده می‌شود نه از سانتی‌مترِ مانکن.
 */
export const bodyColliders = (Collider, body, table) => {
    const out = [];
    const level = table.level;

    const at = (sections, name, offset = [0, 0, 0], spin = 0) => {
        const cos = Math.cos(spin);
        const sin = Math.sin(spin);
        const collider = new Collider({ sections, name });

        collider.setTransform(
            new Float32Array([
                cos, sin, 0, 0, -sin, cos, 0, 0, 0, 0, 1, 0,
                offset[0], offset[1], offset[2], 1,
            ]),
            new Float32Array([
                cos, -sin, 0, 0, sin, cos, 0, 0, 0, 0, 1, 0,
                -(cos * offset[0] + sin * offset[1]),
                -(-sin * offset[0] + cos * offset[1]),
                -offset[2], 1,
            ]),
            0.03,
        );

        out.push(collider);
    };

    at(table.profile.filter(([y]) => y >= level.crotch - 1e-6), 'torso');

    const neck = table.radii.neck;

    at([
        [level.neck - 0.02, neck * 1.05, neck * 1.05],
        [level.chin, neck * 0.92, neck * 0.92],
    ], 'neck');

    const headR = body.head.radius / 100;
    const headY = (body.height - body.head.centre) / 100;

    at([
        [headY - headR * 0.95, headR * 0.42, headR * 0.44],
        [headY, headR * 0.9, headR * 0.95],
        [headY + headR * 0.95, headR * 0.42, headR * 0.44],
    ], 'head');

    /* دست و پا: زنجیرهٔ خودشان، از پایین به بالا */
    const chain = (rows) => rows
        .map((row) => [-row.y / 100, row.r / 100, row.r / 100])
        .sort((a, b) => a[0] - b[0]);

    [[-1, 'armL'], [1, 'armR']].forEach(([side, name]) => {
        at(chain(body.arm), name, [side * table.armOffset, table.armTop, 0], side * table.armTilt);
    });

    [[-1, 'legL'], [1, 'legR']].forEach(([side, name]) => {
        at(chain(body.leg), name, [side * body.leg[0].x / 100, level.crotch, 0]);
    });

    return out;
};

/**
 * نوارِ خودِ درز.
 *
 * حل‌کننده دو لبهٔ یک درز را به هم نزدیک می‌کند ولی هیچ‌وقت کاملاً روی هم
 * نمی‌نشاند: قیدِ درز نرم است و پارچه هم نمی‌تواند بی‌حد کشیده شود. باقی‌مانده‌اش
 * چند سانتی‌متر است و روی مانکن دقیقاً مثل یک درزِ *باز* دیده می‌شود — زیر بغل
 * و روی پهلو، پوست از میان لباس پیدا بود. اندازه گرفته شد: بدترین شکافِ
 * پیراهن شش سانتی‌متر.
 *
 * ولی آن شکاف، شکاف نیست. دو لبه به هم دوخته‌اند؛ چیزی که کم است پارچهٔ خودِ
 * درز است — همان نواری که در لباسِ واقعی جای بخیه و اضافهٔ درز است. پس همان‌جا
 * ساخته می‌شود: میان هر دو سوزنِ پشتِ سرِ هم، دو مثلث. جایی که درز بسته است،
 * نوار پهنای صفر دارد و دیده نمی‌شود؛ جایی که باز مانده، پارچه است نه سوراخ.
 */
const seamBand = (drape) => {
    const positions = [];
    const indices = [];
    const ends = [];
    let base = 0;

    for (const seam of drape.seams) {
        /* تای یقه درز نیست؛ دو سوی آن *باید* روی هم بیفتند، نه اینکه پر شوند */
        if (seam.kind === 'crease' || seam.count < 2) {
            continue;
        }

        const pa = seam.a.positions;
        const pb = (seam.b || seam.a).positions;
        const start = base;

        for (let i = 0; i < seam.count; i++) {
            const a = seam.pairs[i * 2] * 3;
            const b = seam.pairs[i * 2 + 1] * 3;

            positions.push(pa[a], pa[a + 1], pa[a + 2], pb[b], pb[b + 1], pb[b + 2]);
        }

        base += seam.count * 2;

        for (let i = 0; i + 1 < seam.count; i++) {
            const one = start + i * 2;

            indices.push(one, one + 1, one + 2, one + 1, one + 3, one + 2);
        }

        /* دو سرِ همین درز، برای بستنِ گوشه‌ها */
        ends.push({ at: start, seam }, { at: start + (seam.count - 1) * 2, seam });
    }

    /*
     * و گوشه‌ها.
     *
     * حلقهٔ آستین یک درز نیست، سه تاست: جلو، پشت، و یوک. هر کدام نوارِ خودش را
     * دارد ولی *میانشان* چیزی نیست، و همان‌جا یک گوهٔ باز می‌ماند. روی مانکن
     * دیده می‌شد: بالای حلقهٔ آستین یک مثلثِ باز که از آن بدن پیدا بود.
     *
     * پس هر دو سرِ درزی که کنارِ هم افتاده‌اند به هم وصل می‌شوند. «کنارِ هم»
     * یعنی هر دو سرِ یکی به هر دو سرِ دیگری نزدیک باشد؛ دو درزِ بی‌ربط از دو سرِ
     * لباس این شرط را ندارند.
     */
    const near = (one, two) => {
        const dx = positions[one] - positions[two];
        const dy = positions[one + 1] - positions[two + 1];
        const dz = positions[one + 2] - positions[two + 2];

        return dx * dx + dy * dy + dz * dz;
    };

    const REACH = 0.07 * 0.07;

    for (let i = 0; i < ends.length; i++) {
        for (let j = i + 1; j < ends.length; j++) {
            if (ends[i].seam === ends[j].seam) {
                continue;
            }

            const a = ends[i].at * 3;
            const b = ends[j].at * 3;
            const straight = Math.max(near(a, b), near(a + 3, b + 3));
            const crossed = Math.max(near(a, b + 3), near(a + 3, b));

            if (Math.min(straight, crossed) > REACH) {
                continue;
            }

            const one = ends[i].at;
            const two = ends[j].at;

            if (straight <= crossed) {
                indices.push(one, one + 1, two, one + 1, two + 1, two);
            } else {
                indices.push(one, one + 1, two + 1, one + 1, two, two + 1);
            }
        }
    }

    return positions.length ? { positions, indices } : null;
};

/**
 * صاف کردنِ پارچه، پیش از نمایش.
 *
 * حل‌کننده هر رأس را جدا حرکت می‌دهد و در پایان یک موجِ ریزِ رأس‌به‌رأس روی سطح
 * می‌ماند — نه چینِ پارچه، که نویزِ عددی. روی مانکن همان دیده می‌شد که کاربر
 * گفت: «لباس جمع شده». چینِ واقعی پهن است و از افتادنِ پارچه می‌آید؛ این یکی
 * ریز است و به اندازهٔ یک مثلث.
 *
 * پس چند دورِ کوتاهِ میانگین‌گیری با همسایه‌ها: موجِ ریز پاک می‌شود و چینِ پهن
 * می‌ماند. رأس‌های درز دست نمی‌خورند، وگرنه درزی که تازه بسته شده باز می‌شود.
 */
const relax = (drape, rounds = 3, weight = 0.4) => {
    const locked = new Map();

    for (const seam of drape.seams) {
        for (const patch of [seam.a, seam.b || seam.a]) {
            if (! locked.has(patch)) {
                locked.set(patch, new Uint8Array(patch.count));
            }
        }

        const one = locked.get(seam.a);
        const two = locked.get(seam.b || seam.a);

        for (let i = 0; i < seam.count; i++) {
            one[seam.pairs[i * 2]] = 1;
            two[seam.pairs[i * 2 + 1]] = 1;
        }
    }

    for (const entry of drape.patches) {
        const patch = entry.patch;
        const indices = entry.mesh.indices;
        const count = patch.count;
        const hold = locked.get(patch) || new Uint8Array(count);

        /* همسایه‌ها، یک بار */
        const tally = new Uint32Array(count);

        for (let t = 0; t < indices.length; t += 3) {
            tally[indices[t]] += 2;
            tally[indices[t + 1]] += 2;
            tally[indices[t + 2]] += 2;
        }

        const heads = new Uint32Array(count + 1);

        for (let v = 0; v < count; v++) {
            heads[v + 1] = heads[v] + tally[v];
        }

        const near = new Uint32Array(heads[count]);
        const fill = heads.slice(0, count);

        for (let t = 0; t < indices.length; t += 3) {
            for (let k = 0; k < 3; k++) {
                const a = indices[t + k];

                near[fill[a]++] = indices[t + (k + 1) % 3];
                near[fill[a]++] = indices[t + (k + 2) % 3];
            }
        }

        const positions = patch.positions;
        const next = new Float32Array(positions.length);

        for (let round = 0; round < rounds; round++) {
            next.set(positions);

            for (let v = 0; v < count; v++) {
                if (hold[v] || patch.invMass[v] === 0) {
                    continue;
                }

                const from = heads[v];
                const to = heads[v + 1];

                if (to === from) {
                    continue;
                }

                let x = 0;
                let y = 0;
                let z = 0;

                for (let k = from; k < to; k++) {
                    const n = near[k] * 3;

                    x += positions[n];
                    y += positions[n + 1];
                    z += positions[n + 2];
                }

                const n = to - from;
                const at = v * 3;

                next[at] += (x / n - positions[at]) * weight;
                next[at + 1] += (y / n - positions[at + 1]) * weight;
                next[at + 2] += (z / n - positions[at + 2]) * weight;
            }

            positions.set(next);
        }
    }
};

/**
 * کوک‌ها، روی خطِ درز.
 *
 * دوختی که دیده نشود دوخت نیست: لباسی که درزهایش نوارِ صاف باشد، پارچه‌ای است
 * که کسی به هم وصلش نکرده. خیاط روی هر درز کوک می‌زند و فاصله‌شان هم دلبخواه
 * نیست — از وزنِ پارچه می‌آید (ببینید Stitches::WEIGHTS در سرور).
 *
 * پس روی خطِ میانیِ هر درز، هر «فاصلهٔ کوک» یک بخیه گذاشته می‌شود: پاره‌خطی
 * به‌درازای نیمی از فاصله، کمی بیرون‌تر از سطح تا زیرِ پارچه پنهان نشود.
 */
const stitchLines = (drape, millimetres) => {
    const step = Math.max(0.0012, millimetres / 1000);
    const dash = step * 0.55;
    const lift = 0.0012;
    const points = [];

    for (const seam of drape.seams) {
        if (seam.kind === 'crease' || seam.count < 2) {
            continue;
        }

        const pa = seam.a.positions;
        const pb = (seam.b || seam.a).positions;

        /* خطِ میانیِ درز: همان‌جا که سوزن می‌رود */
        const line = [];

        for (let i = 0; i < seam.count; i++) {
            const a = seam.pairs[i * 2] * 3;
            const b = seam.pairs[i * 2 + 1] * 3;

            line.push([
                (pa[a] + pb[b]) / 2,
                (pa[a + 1] + pb[b + 1]) / 2,
                (pa[a + 2] + pb[b + 2]) / 2,
            ]);
        }

        /*
         * راه رفتن روی خط و کوک زدن با فاصلهٔ ثابت — نه یک کوک به ازای هر
         * رأسِ مش. مشِ درشت کوکِ درشت می‌داد و مشِ ریز کوکِ ریز، در حالی که
         * فاصلهٔ کوک به پارچه ربط دارد نه به مثلث‌بندی.
         */
        let carry = 0;

        for (let i = 1; i < line.length; i++) {
            const one = line[i - 1];
            const two = line[i];
            const dx = two[0] - one[0];
            const dy = two[1] - one[1];
            const dz = two[2] - one[2];
            const span = Math.hypot(dx, dy, dz);

            if (span < 1e-6) {
                continue;
            }

            for (let at = step - carry; at < span; at += step) {
                const t = at / span;
                const end = Math.min(1, (at + dash) / span);
                const nudge = (x, z) => {
                    const out = Math.hypot(x, z) || 1;

                    return [(x / out) * lift, (z / out) * lift];
                };

                const px = one[0] + dx * t;
                const pz = one[2] + dz * t;
                const qx = one[0] + dx * end;
                const qz = one[2] + dz * end;
                const [ox, oz] = nudge(px, pz);
                const [rx, rz] = nudge(qx, qz);

                points.push(
                    px + ox, one[1] + dy * t, pz + oz,
                    qx + rx, one[1] + dy * end, qz + rz,
                );
            }

            carry = (carry + span) % step;
        }
    }

    return points.length ? points : null;
};

/**
 * آیا لباسِ دوخته‌شده واقعاً روی تن نشسته؟
 *
 * حل‌کننده همیشه جواب می‌دهد، ولی جوابش همیشه لباس نیست: قطعه‌ای که تکیه‌گاهش را
 * از دست بدهد می‌افتد، و قطعه‌ای که جای شروعش غلط باشد پایین‌تر از جای خودش
 * می‌نشیند. هر دو را دیده‌ایم — شلواری که تا زیرِ کف می‌رفت، پیراهنی که تا
 * باسن سُر می‌خورد.
 *
 * پس پیش از آنکه جای نمای مطمئن را بگیرد، سه چیز سنجیده می‌شود. اگر رد شود،
 * همان نمای چرخشی می‌ماند: ناقص است ولی روی تن است.
 */
const landedWell = (drape, table, anchor, seamError) => {
    /*
     * اول از همه، حرفِ خودِ چیدن.
     *
     * buildDrape هر کمانِ درز را با طولی که سرور گفته می‌سنجد و اختلافِ بزرگ را
     * در stats.checks می‌نویسد. تا امروز کسی نگاهش نمی‌کرد: پیراهنی که ده تا از
     * این هشدارها داشت باز هم دوخته می‌شد و لبهٔ دامنش به سرشانه می‌رسید. اگر
     * قطعه‌ها سرِ جای هم ننشسته باشند، بقیهٔ سنجه‌ها هم بی‌معنی‌اند.
     */
    if (drape.stats.checks.some((row) => row.measured !== undefined)) {
        return 'کمانِ درز با الگو نمی‌خواند';
    }

    /*
     * درزی که بسته نشده یعنی قطعه‌ها سرِ جای هم ننشسته‌اند. کتی با درزِ نُه
     * سانتی، آستین‌هایش جلوی بدن گره می‌خورد — هندسه‌اش سالم بود و چشم می‌گفت
     * خراب است.
     */
    if (! (seamError < 0.06)) {
        return 'درزها بسته نشدند';
    }

    let lowest = Infinity;
    let highest = -Infinity;

    for (const mesh of drape.meshes) {
        const p = mesh.positions;

        for (let i = 1; i < p.length; i += 3) {
            if (p[i] < lowest) {
                lowest = p[i];
            }

            if (p[i] > highest) {
                highest = p[i];
            }
        }
    }

    if (! Number.isFinite(lowest) || ! Number.isFinite(highest)) {
        return 'لباسی ساخته نشد';
    }

    // زیرِ کف رفتن یعنی چیزی افتاده
    if (lowest < -0.03) {
        return 'قطعه‌ای از لباس زیر کف افتاد';
    }

    // بالاتر از چانه هم یعنی چیزی پرت شده
    if (highest > table.level.chin + 0.08) {
        return 'قطعه‌ای از لباس بالای سر رفت';
    }

    /*
     * و بالای لباس باید نزدیکِ همان‌جایی باشد که الگو می‌گوید. کمی نشستن
     * طبیعی است؛ ده سانتی‌متر یعنی لباس از تکیه‌گاهش سُر خورده.
     */
    const seat = (table.level[anchor.level] ?? table.level.waist) + (anchor.offset || 0) / 100;

    if (highest < seat - 0.10) {
        return 'لباس از جای خودش پایین‌تر نشست';
    }

    return null;
};

/**
 * حلقه‌های لباس را رقیق و صاف می‌کند.
 *
 * سرور هر هشت میلی‌متر یک حلقه می‌دهد و اعدادش هم گرد شده‌اند. آن ریزلرزشِ صدم
 * سانتی روی سطح دیده نمی‌شد اگر چین نبود؛ با چین، دو الگوی نزدیک‌به‌هم روی هم
 * می‌افتند و موج‌های تداخلی می‌سازند. پس فاصله‌ها بازتر می‌شود و هر حلقه با
 * همسایه‌هایش میانگین می‌گیرد.
 */
export const smooth = (rings, step = 1.6) => {
    let last = -Infinity;

    const kept = rings.filter((ring, i) => {
        if (i === rings.length - 1 || ring.y - last >= step) {
            last = ring.y;

            return true;
        }

        return false;
    });

    if (kept.length < 3) {
        return kept;
    }

    return kept.map((ring, i) => {
        if (i === 0 || i === kept.length - 1) {
            return ring;
        }

        const a = kept[i - 1];
        const b = kept[i + 1];
        const mix = (key) => (a[key] + ring[key] * 2 + b[key]) / 4;

        return { y: ring.y, rx: mix('rx'), front: mix('front'), back: mix('back') };
    });
};

/**
 * عمقِ یک حلقه در زاویهٔ دلخواه.
 *
 * نیمهٔ جلو و نیمهٔ پشت دو بیضیِ جداگانه‌اند که در پهلو به هم می‌رسند. همان‌جا
 * هر دو نیمه z=0 دارند و مماسشان هم‌راستاست، پس درزی دیده نمی‌شود — ولی برخلافِ
 * یک بیضیِ ساده، سینه می‌تواند جلو بیاید بی‌آنکه پشت هم باد کند.
 */
export const depthAt = (ring, angle) => (Math.sin(angle) >= 0 ? ring.front : ring.back);

/**
 * یک سطحِ لوله‌ای از روی حلقه‌ها.
 *
 * هر حلقه بیضی است و می‌تواند شعاعش را با یک تابع تغییر دهد — همان‌جاست که چینِ
 * پارچه وارد می‌شود. نرمال‌ها از خودِ مثلث‌ها حساب می‌شوند تا چین‌ها سایه بگیرند؛
 * نرمالِ بیضیِ ساده روی سطحِ چین‌خورده غلط است و همه‌چیز تخت دیده می‌شود.
 */
const tube = (rings, options = {}) => {
    const rows = rings.filter((ring) => ring.rx > 0.02 && ring.front > 0.02 && ring.back > 0.02);

    if (rows.length < 2) {
        return null;
    }

    const yOffset = options.yOffset || 0;
    const wave = options.wave || null;
    const capTop = options.capTop !== false;
    const capBottom = options.capBottom !== false;

    const positions = [];
    const indexes = [];

    rows.forEach((ring, row) => {
        const t = rows.length === 1 ? 0 : row / (rows.length - 1);

        for (let i = 0; i <= SIDES; i++) {
            const angle = (i / SIDES) * Math.PI * 2;
            const push = wave ? wave(angle, t, ring) : 0;

            positions.push(
                ((ring.x || 0) + (ring.rx + push) * Math.cos(angle)) * CM,
                -(ring.y + yOffset) * CM,
                (depthAt(ring, angle) + push) * CM * Math.sin(angle),
            );
        }
    });

    const perRow = SIDES + 1;

    for (let r = 0; r < rows.length - 1; r++) {
        for (let i = 0; i < SIDES; i++) {
            const a = r * perRow + i;
            const b = a + 1;
            const c = a + perRow;
            const d = c + 1;

            indexes.push(a, c, b, b, c, d);
        }
    }

    [[0, capTop], [rows.length - 1, capBottom]].forEach(([row, wanted]) => {
        if (! wanted) {
            return;
        }

        const centre = positions.length / 3;

        positions.push((rows[row].x || 0) * CM, -(rows[row].y + yOffset) * CM, 0);

        for (let i = 0; i < SIDES; i++) {
            const a = row * perRow + i;
            const b = a + 1;

            row === 0 ? indexes.push(centre, b, a) : indexes.push(centre, a, b);
        }
    });

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geometry.setIndex(indexes);
    geometry.computeVertexNormals();

    return geometry;
};

export default (initial = {}) => ({
    payload: initial.payload || null,
    fabrics: (initial.payload && initial.payload.fabrics) || [],
    chosen: null,
    ready: false,
    failed: false,
    sewing: false,
    sewn: false,
    sewnNote: '',
    message: '',
    spin: true,

    async boot() {
        if (! this.payload || ! this.payload.ok) {
            this.failed = true;
            this.message = (this.payload && this.payload.notes && this.payload.notes[0])
                || 'این مدل روی مانکن نشانده نشد.';

            return;
        }

        try {
            THREE ??= await import('three');
            this.build();
            this.ready = true;
        } catch (error) {
            this.failed = true;
            this.message = 'نمای سه‌بعدی در این مرورگر بالا نیامد.';

            return;
        }

        /*
         * و حالا، اگر بسته‌ی قطعه‌ها آمده باشد، لباس *واقعاً* دوخته می‌شود.
         *
         * نمای بالا از چرخاندنِ نیم‌رخِ الگو ساخته می‌شود و یک جای مشخص را
         * هیچ‌وقت درست نشان نمی‌دهد: سرشانه. پهنای الگو روی خط سرشانه، پهنای
         * حلقهٔ آستین است نه پهنای تنه، و چرخاندنش یک تختهٔ پهن می‌سازد که دست
         * را هم می‌بلعد.
         *
         * دوختِ واقعی این را ندارد، چون درزِ سرشانه و حلقهٔ آستین را از خودِ
         * قطعه‌ها می‌گیرد. گران‌تر است — چند ثانیه — پس اول همان نمای سریع
         * نشان داده می‌شود و بعد جایش را می‌دهد.
         */
        if (this.payload.drape) {
            await this.stitch();
        }
    },

    /** لباس را واقعاً می‌دوزد و روی همین مانکن می‌نشاند. */
    async stitch() {
        this.sewing = true;

        // یک فریم فرصت، تا پیام «در حال دوخت» دیده شود
        await new Promise((resolve) => requestAnimationFrame(() => resolve()));

        try {
            const [{ ClothWorld, Collider }, { buildDrape, supportGarment, weldSeams }] = await Promise.all([
                import('../lib/cloth-solver.js'),
                import('../lib/pattern-drape.js'),
            ]);

            const ctx = contextFor(this.$root);
            const body = ctx.body;
            const table = drapeBody(body);
            const fabric = this.payload.fabric || {};
            const drape = buildDrape(this.payload.drape, table, { fabric });

            if (! drape.patches.length) {
                throw new Error('قطعه‌ای برای دوخت نماند');
            }

            const world = new ClothWorld({ fabric });

            drape.patches.forEach((entry) => world.addPatch(entry.patch));
            drape.seams.forEach((seam) => world.addSeam(seam));
            world.setColliders(bodyColliders(Collider, body, table));

            world.substeps = drape.stats.solver.substeps;
            world.iterations = Math.max(6, drape.stats.solver.iterations);

            /*
             * ترتیبش مهم است: اول در بی‌وزنی دوخته می‌شود، بعد سرشانه گرفته
             * می‌شود و وزن برمی‌گردد. برعکسش، قطعه‌ها پیش از بسته شدنِ درزها از
             * روی بدن سُر می‌خورند.
             */
            const gravity = world.law.gravity;

            world.law.gravity = 0;
            world.presettle(drape.stats.presettle);

            supportGarment(drape, { band: 0.08, strength: 1 });
            drape.patches.forEach((entry) => entry.patch.applyPins(IDENTITY));

            world.law.gravity = gravity;
            world.presettle(40);
            world.iterations = drape.stats.solver.iterations;
            world.seamPasses = drape.stats.solver.seamPasses ?? world.seamPasses;
            world.presettle(300);

            /*
             * جوش، آخر از همه.
             *
             * سنجهٔ کیفیتِ حل‌کننده عمداً پس از جوش باز هم شبیه‌سازی می‌کند، چون
             * می‌خواهد بداند درز *ماندگار* چقدر باز است. ولی این‌جا تصویری
             * ثابت است و پس از نمایش هیچ گامی برداشته نمی‌شود؛ آن صد‌و‌پنجاه
             * گامِ آخر فقط چیزی را که جوش بسته بود دوباره باز می‌کرد. اندازه
             * گرفته شد: بدترین شکافِ پیراهن ۱۰٫۳ سانتی‌متر بود و شد ۶٫۰.
             */
            world.presettle(150);
            weldSeams(drape);
            relax(drape);

            const wrong = landedWell(
                drape,
                table,
                this.payload.anchor || { level: 'shoulder', offset: 0 },
                world.seamError(),
            );

            if (wrong) {
                this.sewnNote = wrong;

                throw new Error(wrong);
            }

            this.showStitched(drape);
            this.sewn = true;
        } catch (error) {
            // نمای چرخشی سرِ جایش می‌ماند؛ چیزی از دست نمی‌رود
            this.sewn = false;
        }

        this.sewing = false;
    },

    build() {
        const ctx = contextFor(this.$root);
        const canvas = this.$refs.canvas;
        const shell = this.payload;
        const body = buildBody(shell.measurements || {});
        ctx.body = body;

        const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        ctx.renderer = renderer;

        const scene = new THREE.Scene();
        ctx.scene = scene;

        /*
         * قاب از قدِ خودِ مانکن می‌آید، پس یک تاپ و یک لباس شب هر دو کامل در
         * تصویر می‌افتند و نسبتشان به بدن حفظ می‌شود.
         */
        const tall = body.level.ankle * CM;
        const camera = new THREE.PerspectiveCamera(30, canvas.clientWidth / canvas.clientHeight, 0.05, 60);
        camera.position.set(tall * 0.40, -tall * 0.45, tall * 1.9);
        camera.lookAt(0, -tall * 0.47, 0);
        ctx.camera = camera;

        scene.add(new THREE.HemisphereLight(0xf6f4f1, 0x59524c, 1.4));

        const key = new THREE.DirectionalLight(0xfff6ec, 2.0);
        key.position.set(tall * 0.9, tall * 0.5, tall * 1.4);
        key.castShadow = true;
        key.shadow.mapSize.set(1024, 1024);
        key.shadow.camera.near = 0.1;
        key.shadow.camera.far = tall * 6;
        key.shadow.camera.left = -tall;
        key.shadow.camera.right = tall;
        key.shadow.camera.top = tall;
        key.shadow.camera.bottom = -tall;
        scene.add(key);

        const fill = new THREE.DirectionalLight(0xdfe6ef, 0.65);
        fill.position.set(-tall * 1.2, -tall * 0.2, tall * 0.7);
        scene.add(fill);

        const rim = new THREE.DirectionalLight(0xffffff, 0.85);
        rim.position.set(-tall * 0.4, tall * 0.6, -tall * 1.4);
        scene.add(rim);

        // زمین فقط سایه می‌گیرد؛ خودش دیده نمی‌شود
        const floor = new THREE.Mesh(
            track(ctx, new THREE.PlaneGeometry(tall * 4, tall * 4)),
            track(ctx, new THREE.ShadowMaterial({ opacity: 0.2 })),
        );
        floor.rotation.x = -Math.PI / 2;
        floor.position.y = -tall * 1.002;
        floor.receiveShadow = true;
        scene.add(floor);

        const group = new THREE.Group();
        group.rotation.y = -0.42;
        scene.add(group);
        ctx.group = group;

        this.addBody(group, body);

        /*
         * نمای چرخشی در گروهِ خودش می‌ماند تا اگر دوختِ واقعی رسید، یک‌جا
         * کنار برود — نه اینکه دو لباس روی هم بیفتند.
         */
        const spun = new THREE.Group();

        group.add(spun);
        ctx.spun = spun;

        this.addGarment(spun, shell, body);

        let last = performance.now();

        const tick = (now) => {
            const delta = Math.min((now - last) / 1000, 0.1);
            last = now;

            if (this.spin) {
                group.rotation.y += delta * 0.42;
            }

            renderer.render(scene, camera);
            ctx.frame = requestAnimationFrame(tick);
        };

        ctx.frame = requestAnimationFrame(tick);
    },

    /**
     * لباسِ دوخته‌شده جای نمای چرخشی را می‌گیرد.
     *
     * بافرِ هر قطعه همان بافرِ حل‌کننده است، پس چیزی رونویسی نمی‌شود. جنسِ پارچه
     * هم همان است که بود، تا عوض کردنِ پارچه سرِ جایش کار کند.
     */
    showStitched(drape) {
        const ctx = contextFor(this.$root);
        const cloth = new THREE.Group();

        /*
         * حل‌کننده در دستگاهِ خودش کار می‌کند — متر، y رو به بالا، صفر روی زمین —
         * و این صحنه از بالای سر رو به پایین می‌شمارد. یک جابه‌جاییِ ساده هر دو
         * را یکی می‌کند؛ آینه لازم نیست، پس جهتِ مثلث‌ها هم به‌هم نمی‌ریزد.
         */
        cloth.position.y = -ctx.body.height * CM;

        for (const mesh of drape.meshes) {
            const geometry = track(ctx, new THREE.BufferGeometry());

            geometry.setAttribute('position', new THREE.BufferAttribute(mesh.positions, 3));
            geometry.setIndex(new THREE.BufferAttribute(mesh.indices, 1));
            geometry.computeVertexNormals();

            const piece = new THREE.Mesh(geometry, ctx.fabric);

            piece.castShadow = true;
            piece.receiveShadow = true;
            cloth.add(piece);
        }

        /* و خودِ درزها، تا میانِ دو قطعه سوراخ نماند */
        const band = seamBand(drape);

        if (band) {
            const geometry = track(ctx, new THREE.BufferGeometry());

            geometry.setAttribute('position', new THREE.Float32BufferAttribute(band.positions, 3));
            geometry.setIndex(band.indices);
            geometry.computeVertexNormals();

            const seam = new THREE.Mesh(geometry, ctx.fabric);

            seam.castShadow = true;
            cloth.add(seam);
        }

        /* و کوک‌ها، با همان فاصله‌ای که در نقشهٔ دوخت نوشته شده */
        const spacing = (this.payload.stitch && this.payload.stitch.length_mm) || 2.5;
        const seams = stitchLines(drape, spacing);

        if (seams) {
            const geometry = track(ctx, new THREE.BufferGeometry());

            geometry.setAttribute('position', new THREE.Float32BufferAttribute(seams, 3));

            const thread = track(ctx, new THREE.LineBasicMaterial({
                color: threadColour(ctx.fabric.color),
            }));

            ctx.thread = thread;
            cloth.add(new THREE.LineSegments(geometry, thread));
        }

        ctx.group.add(cloth);
        ctx.stitched = cloth;

        if (ctx.spun) {
            ctx.spun.visible = false;
        }
    },

    /** مانکن: سر، گردن، تنه، دو دست و دو پا — همه از اندازه‌های مشتری. */
    addBody(group, body) {
        const ctx = contextFor(this.$root);

        const skin = track(ctx, new THREE.MeshStandardMaterial({
            color: 0xcdc2b8,
            roughness: 0.96,
            metalness: 0,
        }));
        ctx.skin = skin;

        const add = (geometry) => {
            if (! geometry) {
                return;
            }

            track(ctx, geometry);
            const mesh = new THREE.Mesh(geometry, skin);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            group.add(mesh);
        };

        add(tube(body.torso));

        // سر: بیضی‌گون، نه کره — کرهٔ کامل شبیه توپ می‌شود
        const head = track(ctx, new THREE.SphereGeometry(body.head.radius * CM, 32, 24));
        const headMesh = new THREE.Mesh(head, skin);
        headMesh.scale.set(0.82, 1.12, 0.9);
        headMesh.position.y = -body.head.centre * CM;
        headMesh.castShadow = true;
        group.add(headMesh);

        const joint = armJoint(body);

        [-1, 1].forEach((side) => {
            /*
             * سرِ شانه گِرد است. بی این حلقه، بالای بازو یک صفحهٔ تخت می‌ماند که
             * از کنارِ تنه بیرون می‌زند و مثل تختهٔ چوب دیده می‌شود.
             */
            const ball = body.arm[0].r;
            const rings = [
                round(-ball * 0.85, ball * 0.55, side * (joint.x - ball * 0.35)),
                round(-ball * 0.45, ball * 0.86, side * (joint.x - ball * 0.12)),
                ...body.arm.map((row) => round(row.y, row.r, side * armCentre(body, row.y))),
            ];

            add(tube(rings, { yOffset: joint.y }));
        });

        [-1, 1].forEach((side) => {
            const rings = body.leg.map((row) => round(row.y, row.r, side * row.x));
            add(tube(rings, { yOffset: body.level.crotch }));
        });
    },

    /** لباس: همان حلقه‌های الگو، با چینِ برخاسته از آزادیِ خودش. */
    addGarment(group, shell, body) {
        const ctx = contextFor(this.$root);
        const fabric = shell.fabric || {};

        const material = track(ctx, new THREE.MeshPhysicalMaterial({
            color: new THREE.Color(fabric.color || '#b9a48c'),
            roughness: clamp(1 - (fabric.sheen ?? 0.15) * 0.8, 0.4, 1),
            metalness: 0,
            sheen: 0.6,
            sheenRoughness: 0.75,
            sheenColor: new THREE.Color(0xffffff),
            side: THREE.DoubleSide,
            transparent: (fabric.transparency ?? 0) > 0.05,
            opacity: 1 - Math.min(0.55, fabric.transparency ?? 0),
        }));
        ctx.fabric = material;

        /*
         * لباس کجای بدن می‌نشیند؟ سرور می‌گوید — نامِ ترازِ بدن و فاصله‌اش از
         * بالای لباس. پیراهن روی سرشانه، شلوار روی کمر، تاپِ بی‌بند روی سینه.
         */
        const anchor = shell.anchor || { level: shell.shoulder > 1 ? 'shoulder' : 'waist', offset: 0 };
        const top = (body.level[anchor.level] ?? body.level.waist) - (anchor.offset || 0);
        const hangs = anchor.level === 'shoulder';

        /*
         * چینِ پارچه.
         *
         * در هر ارتفاع، لباس یک دورِ مشخص دارد و بدن هم. اختلافشان پارچهٔ اضافه
         * است، و پارچهٔ اضافه صاف نمی‌ماند — جمع می‌شود. پس دامنهٔ چین از همین
         * اختلاف می‌آید و شمارِ چین‌ها هم از همان.
         *
         * دو چیز چین را شبیه پارچه می‌کند نه شبیه موج: روی سرشانه صفر است
         * (آن‌جا لباس آویزان و کشیده است) و رو به پایین باز می‌شود؛ و فازش با
         * ارتفاع کمی می‌چرخد تا چین‌ها ستون‌های صافِ عمودی نباشند.
         */
        /*
         * بالای خط سینه، لباس روی *بدن* می‌نشیند نه روی عددِ کاغذ: شانه آن را
         * نگه داشته و پارچه همان‌جا کشیده است. اگر حلقه‌های کاغذی را همان‌طور
         * بچرخانیم، روی شانه یک تختهٔ پهن و کم‌عمق درمی‌آید که به هیچ لباسی
         * شبیه نیست.
         *
         * پس از خط سینه به بالا، حلقه‌ها به شکلِ خودِ بدن (به‌علاوهٔ یک آزادیِ
         * کوچک) میل می‌کنند، و یک حلقهٔ گردن هم بالای همه گذاشته می‌شود تا
         * دهانهٔ یقه دور گردن بسته شود نه دور شانه‌ها.
         */
        const bustRel = body.level.bust - top;
        const skinGap = 1.1;

        /*
         * این پیوند باید کوتاه باشد، به‌بلندیِ حلقهٔ آستین — نه تا خط سینه.
         *
         * پهنای سرشانه تا نوکِ شانه است و دست از همان‌جا آویزان می‌شود. اگر لباس
         * تا خط سینه همان پهنا را نگه دارد، دست کامل تویش می‌ماند و لباس مثل
         * شنل روی شانه‌ها می‌افتد. در واقعیت، پارچه چند سانت پایین‌تر از سرشانه
         * به اندازهٔ خودش برمی‌گردد و دست از حلقهٔ آستین بیرون می‌آید.
         */
        const armholeRel = Math.max(2, bustRel * 0.32);

        /*
         * حلقهٔ کاغذ فقط یک دور است: پهنا و ضخامت، هر دو قرینه. ولی بدنی که
         * زیرش است قرینه نیست، و لباس شکلِ همان بدن را می‌گیرد — سینه پارچه را
         * جلو می‌برد و باسن پشت را. پس همان دور را نگه می‌داریم و فقط جابه‌جایش
         * می‌کنیم: هرچه به جلو رفت، از پشت کم می‌شود.
         *
         * چقدر پیروی کند؟ به‌اندازهٔ تنگی‌اش. لباسِ چسبان تمامِ برجستگی را نشان
         * می‌دهد؛ مانتوی گشاد آویزان است و کمتر.
         */
        let rings = smooth(shell.rings.map((ring) => {
            const skin = bodyEnvelope(body, ring.y + top);
            const skinGirth = girthOf(skin);
            const cloth = girthOf({ rx: ring.rx, front: ring.rz, back: ring.rz });
            const excess = skinGirth <= 1 ? 0 : clamp(cloth / skinGirth - 1, 0, 1.6);
            const follow = clamp(1.1 - excess * 1.5, 0.2, 1);
            const lean = clamp((skin.front - skin.back) / 2 * follow, -ring.rz * 0.55, ring.rz * 0.55);

            let out = { y: ring.y, rx: ring.rx, front: ring.rz + lean, back: ring.rz - lean };

            if (bustRel > 1 && ring.y < armholeRel) {
                const t = clamp(ring.y / armholeRel, 0, 1);

                out = {
                    y: ring.y,
                    rx: lerp(skin.rx + skinGap, out.rx, t),
                    front: lerp(skin.front + skinGap, out.front, t),
                    back: lerp(skin.back + skinGap, out.back, t),
                };
            }

            // پارچه بیرونِ پوست است، همیشه
            return {
                y: out.y,
                rx: Math.max(out.rx, skin.rx + SKIN_GAP),
                front: Math.max(out.front, skin.front + SKIN_GAP),
                back: Math.max(out.back, skin.back + SKIN_GAP),
            };
        }));

        if (hangs && bustRel > 1) {
            /*
             * سرشانه یک پله نیست.
             *
             * لباسی که از سرشانه آویزان است، از دهانهٔ یقه تا نوکِ شانه شیب
             * دارد — همان شیبی که خودِ شانه دارد. اگر فقط یک حلقهٔ گردن بالای
             * حلقهٔ شانه بگذاریم، میانشان یک مخروطِ تیز درمی‌آید که مثل چوب‌لباسی
             * دیده می‌شود. پس چند تراز از خودِ بدن برداشته می‌شود و پارچه روی
             * همان‌ها می‌خوابد.
             */
            const neckY = body.level.neck;
            const drop = body.level.shoulder - neckY;

            /*
             * دهانهٔ یقه باید دورِ گردن را بگیرد. اگر کمی گشادتر از گردن باشد،
             * از همان درز، داخلِ لباس دیده می‌شود — یک مثلثِ سیاه کنارِ گردن.
             */
            const collar = sampleRing(body.torso, neckY);
            const hug = 0.5;

            const slope = [0, 0.34, 0.62, 0.84].map((t) => {
                const y = neckY + drop * t;
                const skin = sampleRing(body.torso, y);
                const ease = lerp(hug, skinGap, t);

                return {
                    y: y - top,
                    rx: lerp(collar.rx + hug, skin.rx + ease, t),
                    front: lerp(collar.front + hug, skin.front + ease, t),
                    back: lerp(collar.back + hug, skin.back + ease, t),
                };
            });

            rings = [...slope, ...rings];
        }

        const excessAt = (ring) => {
            const skinGirth = girthOf(bodyEnvelope(body, ring.y + top));

            return skinGirth <= 1 ? 0 : clamp(girthOf(ring) / skinGirth - 1, 0, 1.6);
        };

        const tail = rings.length ? rings[rings.length - 1] : null;
        const folds = Math.round(clamp(5 + (tail ? excessAt(tail) : 0) * 5, 5, 11));

        const wave = (angle, t, ring) => {
            const excess = excessAt(ring);

            if (excess < 0.08) {
                return 0;
            }

            const fall = Math.pow(clamp(t, 0, 1), 1.3);
            const depth = Math.min(ring.rx * 0.16, (excess - 0.08) * ring.rx * 0.5) * fall;

            return depth * Math.cos(folds * angle + t * 0.8);
        };

        /*
         * شلوار دو پاچه دارد، و این را نمی‌شود از نمای تخت فهمید: نیم‌رخِ
         * شلوار از بیرونِ یک پا تا بیرونِ پای دیگر است، درست مثل دامن. اگر
         * همان را بچرخانیم، شلوار دامن می‌شود — و شد.
         *
         * پس از خط فاق به پایین، تنه تمام می‌شود و دو پاچه جدا ساخته می‌شود.
         * دورِ هر پاچه نصفِ دورِ همان تراز است و مرکزش روی مرکزِ پای مانکن.
         */
        const crotchRel = body.level.crotch - top;
        const split = shell.legs && rings.some((ring) => ring.y > crotchRel + 3);
        const trunk = split ? rings.filter((ring) => ring.y <= crotchRel) : rings;

        const geometry = tube(trunk, {
            yOffset: top,
            wave,
            capTop: false,
            capBottom: ! split,
        });

        if (geometry) {
            track(ctx, geometry);
            const mesh = new THREE.Mesh(geometry, material);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            group.add(mesh);
        }

        if (split) {
            this.addLegs(group, rings, crotchRel, top, body, material);
        }

        this.addSleeves(group, shell, body, material);
    },

    /** پاچه‌ها: هرکدام نصفِ دورِ لباس، روی مسیرِ پای خودش. */
    addLegs(group, rings, crotchRel, top, body, material) {
        const ctx = contextFor(this.$root);

        /*
         * پاچه چند سانت بالاتر از فاق شروع می‌شود تا زیر تنه برود، وگرنه
         * میانشان یک شکاف می‌ماند. پایینِ تنه هم بسته نیست، پس درزی دیده
         * نمی‌شود.
         */
        const rows = rings.filter((ring) => ring.y > crotchRel - 4);

        if (rows.length < 2) {
            return;
        }

        [-1, 1].forEach((side) => {
            const path = rows.map((ring) => {
                const local = Math.max(0, ring.y + top - body.level.crotch);
                const skin = chainAt(body.leg, local);
                const r = Math.max(girthOf(ring) / 2 / (Math.PI * 2), skin + SKIN_GAP);

                return round(ring.y, r, side * chainAt(body.leg, local, 'x'));
            });

            const geometry = tube(path, { yOffset: top, capTop: false, capBottom: true });

            if (! geometry) {
                return;
            }

            track(ctx, geometry);
            const mesh = new THREE.Mesh(geometry, material);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            group.add(mesh);
        });
    },

    /** آستین: لوله‌ای روی مسیرِ دست، با دور بازو و دم‌آستینِ خودِ الگو. */
    addSleeves(group, shell, body, material) {
        const ctx = contextFor(this.$root);
        const sleeve = shell.sleeve;

        if (! sleeve || sleeve.length < 4) {
            return;
        }

        /*
         * عددی که سرور می‌دهد نیم‌پهنای *تختِ* آستین است، پس دورِ آستینِ
         * دوخته‌شده دو برابر آن و شعاعش همان تقسیم بر پی. یک بار همان نیم‌پهنا
         * را شعاع گرفتم و آستین دو برابر پهن درآمد.
         */
        const radius = (half) => half / Math.PI;
        const shoulder = body.shoulderRing;
        const bicep = Math.max(radius(sleeve.bicep), body.arm[0].r * 1.05);

        /*
         * دم‌آستین جایی از دست را می‌گیرد که آستین آن‌جا تمام می‌شود — آستین
         * کوتاه روی بازو و آستین بلند روی مچ — پس شعاعِ کفِ آن از همان نقطهٔ
         * دست خوانده می‌شود، نه از یک حلقهٔ ثابت.
         */
        const cuff = Math.max(radius(sleeve.cuff), chainAt(body.arm, sleeve.length) * 1.06);
        const joint = armJoint(body);

        [-1, 1].forEach((side) => {
            const mid = bicep + (cuff - bicep) * 0.45;

            /*
             * سرِ آستین.
             *
             * آستین از حلقهٔ آستین بیرون می‌آید، نه از هوا. پس بالاترین حلقه‌اش
             * بالاتر از مفصل و کشیده به سمتِ مرکزِ بدن گذاشته می‌شود تا کاملاً
             * زیر پوستهٔ تنه پنهان شود. پیش از این، لولهٔ آستین از کنارِ تنه
             * شروع می‌شد و دهانهٔ بازش دیده می‌شد — مثل لوله‌ای که کنارِ آدم
             * آویزان است.
             *
             * ارتفاعش هم باید داخلِ خودِ آستین بماند: آستینِ حلقه‌ای چند سانت
             * بیشتر نیست و با عددِ ثابت، حلقهٔ سرشانه از دم‌آستین پایین‌تر
             * می‌افتاد و لوله روی خودش تا می‌خورد.
             */
            const head = Math.min(body.arm[0].r * 0.9, sleeve.length * 0.3);
            const at = (along, r, pull = 0) => round(
                along,
                r,
                side * lerp(armCentre(body, along), shoulder.rx * 0.45, pull),
            );

            const geometry = tube([
                at(-body.arm[0].r * 1.1, bicep * 0.86, 0.85),
                at(head * 0.17, bicep * 1.08, 0.22),
                at(head, bicep * 1.02),
                at(sleeve.length * 0.45, mid),
                at(sleeve.length * 0.8, cuff * 1.05),
                at(sleeve.length, cuff),
            ], { yOffset: joint.y, capTop: false, capBottom: true });

            if (! geometry) {
                return;
            }

            track(ctx, geometry);
            const mesh = new THREE.Mesh(geometry, material);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            group.add(mesh);
        });
    },

    /*
     * عوض کردنِ پارچه فقط رنگ و براقی و شفافیت را عوض می‌کند؛ شکل لباس از
     * الگو می‌آید و به پارچه ربطی ندارد، پس صحنه دوباره ساخته نمی‌شود.
     */
    wear(swatch) {
        const ctx = contextFor(this.$root);

        if (! ctx.fabric || ! THREE) {
            return;
        }

        this.chosen = swatch.id;
        ctx.fabric.color = new THREE.Color(swatch.color);
        ctx.fabric.roughness = clamp(1 - (swatch.sheen ?? 0.15) * 0.8, 0.4, 1);
        ctx.fabric.transparent = (swatch.transparency ?? 0) > 0.05;
        ctx.fabric.opacity = 1 - Math.min(0.55, swatch.transparency ?? 0);
        ctx.fabric.needsUpdate = true;

        if (ctx.thread) {
            ctx.thread.color = threadColour(ctx.fabric.color);
            ctx.thread.needsUpdate = true;
        }
    },

    toggleSpin() {
        this.spin = ! this.spin;
    },

    destroy() {
        const ctx = contextFor(this.$root);

        if (ctx.frame) {
            cancelAnimationFrame(ctx.frame);
        }

        (ctx.disposables || []).forEach((item) => item.dispose && item.dispose());

        if (ctx.renderer) {
            ctx.renderer.dispose();
        }

        contexts.delete(this.$root);
    },
});
