/*
 * از قطعه‌های الگو تا پارچه‌ی دوخته‌شده روی مانکن
 *
 * این ماژول پل میان دو دنیاست: بستهٔ سرور که قطعه‌های واقعی الگو را به‌صورت
 * چندضلعیِ سانتی‌متری می‌دهد، و حل‌کنندهٔ پارچه که فقط ذره و قید می‌شناسد.
 * سه کار می‌کند و هیچ‌کدام به three.js وابسته نیست:
 *
 *   ۱) هر قطعه را مثلث‌بندی می‌کند — با مرزِ دست‌نخورده، چون درز دقیقاً روی
 *      همان مرز دوخته می‌شود و یک میلی‌متر جابه‌جایی یعنی درزِ کج.
 *   ۲) قطعه‌ها را دور بدن می‌چیند — فقط برای شروع؛ این چیدن «فرم لباس» نیست،
 *      نقطه‌ی شروعِ خیاطی است.
 *   ۳) درزها و ساسون‌ها را می‌سازد و به حل‌کننده می‌سپارد. از اینجا به بعد،
 *      فرمِ لباس را دوختن می‌سازد، نه ما.
 *
 * چرا این کار با پوسته‌ی پارامتری فرق دارد؟ چون آنجا فرم را ما می‌ساختیم و
 * پارچه فقط رویش می‌افتاد. اینجا فرم از بسته شدن ساسون و کشیده شدن درز پرنسسی
 * درمی‌آید — همان‌طور که در کارگاه واقعی درمی‌آید.
 *
 * دستگاه مختصات: بسته سانتی‌متر است و y آن رو به پایین (دستگاه الگو). خروجی
 * متر است و y رو به بالا (دستگاه three). تبدیل فقط همین‌جا انجام می‌شود.
 */

import { SeamSet, TriPatch, fabricLaw, hash } from './cloth-solver.js';

/* ---------------------------------------------------------------------------
 * ابزارهای کوچکِ دوبعدی
 * ------------------------------------------------------------------------- */

const clamp = (value, min, max) => (value < min ? min : value > max ? max : value);

const lerp = (a, b, t) => a + (b - a) * t;

/*
 * درون‌یابی خطی روی جدولی از [ارتفاع, مقدار…] که بر اساس ارتفاع صعودی مرتب است.
 * بیرون از بازه نزدیک‌ترین سطر برگردانده می‌شود — همان قاعده‌ای که مانکن هم با
 * آن ساخته شده، پس پارچه و بدن هیچ‌وقت دو جدول متفاوت را نمی‌خوانند.
 */
export const sampleTable = (table, y) => {
    if (! table || ! table.length) {
        return [0];
    }

    if (y <= table[0][0]) {
        return table[0].slice(1);
    }

    const last = table[table.length - 1];

    if (y >= last[0]) {
        return last.slice(1);
    }

    for (let i = 0; i < table.length - 1; i++) {
        if (y >= table[i][0] && y <= table[i + 1][0]) {
            const t = (y - table[i][0]) / Math.max(1e-6, table[i + 1][0] - table[i][0]);

            return table[i].slice(1).map((value, index) => lerp(value, table[i + 1][index + 1], t));
        }
    }

    return last.slice(1);
};

/* مساحت علامت‌دار؛ علامتش جهت پیمایش چندضلعی را می‌گوید */
export const polygonArea = (polygon) => {
    let total = 0;

    for (let i = 0, n = polygon.length; i < n; i++) {
        const [x0, y0] = polygon[i];
        const [x1, y1] = polygon[(i + 1) % n];

        total += x0 * y1 - x1 * y0;
    }

    return total / 2;
};

/* آیا نقطه داخل چندضلعی است؟ شمارش برخورد نیم‌خط افقی با ضلع‌ها */
const insidePolygon = (x, y, xs, ys, loop) => {
    let inside = false;

    for (let i = 0, n = loop.length, j = n - 1; i < n; j = i++) {
        const xi = xs[loop[i]];
        const yi = ys[loop[i]];
        const xj = xs[loop[j]];
        const yj = ys[loop[j]];

        if (yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) {
            inside = ! inside;
        }
    }

    return inside;
};

/* مربعِ فاصله‌ی نقطه تا پاره‌خط */
const segmentDistance2 = (px, py, ax, ay, bx, by) => {
    const dx = bx - ax;
    const dy = by - ay;
    const len2 = dx * dx + dy * dy;
    const t = len2 > 0 ? clamp(((px - ax) * dx + (py - ay) * dy) / len2, 0, 1) : 0;
    const qx = ax + dx * t - px;
    const qy = ay + dy * t - py;

    return qx * qx + qy * qy;
};

const cross = (ax, ay, bx, by, cx, cy) => (bx - ax) * (cy - ay) - (by - ay) * (cx - ax);

/* برخوردِ واقعیِ دو پاره‌خط (تماس در سرها به حساب نمی‌آید) */
const segmentsCross = (ax, ay, bx, by, cx, cy, dx, dy) => {
    const d1 = cross(ax, ay, bx, by, cx, cy);
    const d2 = cross(ax, ay, bx, by, dx, dy);
    const d3 = cross(cx, cy, dx, dy, ax, ay);
    const d4 = cross(cx, cy, dx, dy, bx, by);

    return d1 * d2 < 0 && d3 * d4 < 0;
};

/* ---------------------------------------------------------------------------
 * مثلث‌بندیِ دلونه با مرزِ نگه‌داشته‌شده
 * ---------------------------------------------------------------------------
 * چرا دلونه و نه «بریدن گوش»؟ چون بریدن گوش مثلث‌های تیغه‌ای می‌سازد و پارچه‌ای
 * که یک ضلعش صد برابر ضلع دیگرش باشد در حل‌کننده مثل فنرِ دیوانه رفتار می‌کند:
 * وزن رأسش تقریباً صفر است و با هر اصلاح از صحنه می‌پرد. دلونه بزرگ‌ترین
 * زاویه‌ی کوچک ممکن را می‌دهد، یعنی گردترین مثلث‌های ممکن.
 *
 * روش، سه مرحله‌ی ساده:
 *   ۱) مرز را با گام یکنواخت نمونه می‌گیریم و داخلش یک شبکه‌ی مثلثی (شش‌ضلعی)
 *      از نقطه می‌ریزیم؛ نقطه‌های خیلی نزدیک به مرز حذف می‌شوند تا مثلثِ تیغه
 *      نسازند.
 *   ۲) دلونه‌ی بی‌قید (بویر-واتسون) روی همه‌ی نقطه‌ها.
 *   ۳) مرز را برمی‌گردانیم: هر پاره‌خطِ مرزی که در مثلث‌بندی نیفتاده باشد با
 *      چرخاندن یال‌های روی راهش برگردانده می‌شود، و بعد هر مثلثی که مرکزش بیرون
 *      چندضلعی است دور ریخته می‌شود. حلقه‌ی آستین و گودی یقه مقعرند و بدون این
 *      مرحله، مثلث‌بندی روی هوا پُر می‌شود.
 *
 * قطعی بودن: هیچ عدد تصادفی در کار نیست و ترتیب درج نقطه‌ها ثابت است، پس دو بار
 * اجرا با یک چندضلعی، بیت‌به‌بیت یک مش می‌دهد.
 */

const superTriangle = (xs, ys, count) => {
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (let i = 0; i < count; i++) {
        if (xs[i] < minX) minX = xs[i];
        if (ys[i] < minY) minY = ys[i];
        if (xs[i] > maxX) maxX = xs[i];
        if (ys[i] > maxY) maxY = ys[i];
    }

    const span = Math.max(maxX - minX, maxY - minY) || 1;
    const mx = (minX + maxX) / 2;
    const my = (minY + maxY) / 2;

    xs.push(mx - 20 * span, mx + 20 * span, mx);
    ys.push(my - 20 * span, my - 20 * span, my + 20 * span);

    return count;
};

const delaunay = (xs, ys, count) => {
    const first = superTriangle(xs, ys, count);
    const tri = [first, first + 1, first + 2];
    const cx = [];
    const cy = [];
    const cr = [];

    const circle = (t) => {
        const ia = tri[t * 3];
        const ib = tri[t * 3 + 1];
        const ic = tri[t * 3 + 2];
        const ax = xs[ia];
        const ay = ys[ia];
        const bx = xs[ib];
        const by = ys[ib];
        const px = xs[ic];
        const py = ys[ic];
        const d = 2 * (ax * (by - py) + bx * (py - ay) + px * (ay - by));

        if (Math.abs(d) < 1e-18) {
            // سه نقطه‌ی هم‌خط: دایره‌ی محیطی ندارند. با شعاع بی‌نهایت، این مثلث
            // در اولین درجِ بعدی «بد» شمرده و برداشته می‌شود.
            cx[t] = 0;
            cy[t] = 0;
            cr[t] = Infinity;

            return;
        }

        const a2 = ax * ax + ay * ay;
        const b2 = bx * bx + by * by;
        const c2 = px * px + py * py;
        const ux = (a2 * (by - py) + b2 * (py - ay) + c2 * (ay - by)) / d;
        const uy = (a2 * (px - bx) + b2 * (ax - px) + c2 * (bx - ax)) / d;

        cx[t] = ux;
        cy[t] = uy;
        cr[t] = (ax - ux) * (ax - ux) + (ay - uy) * (ay - uy);
    };

    circle(0);

    const bad = [];
    const edges = new Map();

    for (let p = 0; p < count; p++) {
        const px = xs[p];
        const py = ys[p];

        bad.length = 0;
        edges.clear();

        for (let t = 0; t < tri.length / 3; t++) {
            const dx = px - cx[t];
            const dy = py - cy[t];
            const d2 = dx * dx + dy * dy;

            // حاشیه‌ی کوچک: نقطه‌های دقیقاً روی دایره (شبکه‌ی منظم پر از آن‌هاست)
            // «داخل» شمرده نمی‌شوند تا حفره‌ی درج همیشه ستاره‌ای بماند
            if (d2 < cr[t] - 1e-12 * (cr[t] + 1)) {
                bad.push(t);
            }
        }

        if (! bad.length) {
            continue;
        }

        for (let i = 0; i < bad.length; i++) {
            const t = bad[i];

            for (let e = 0; e < 3; e++) {
                const u = tri[t * 3 + e];
                const v = tri[t * 3 + ((e + 1) % 3)];

                edges.set(u * (first + 3) + v, [u, v]);
            }
        }

        // مرزِ حفره: یالی که یال وارونش هم بین مثلث‌های بد باشد، داخلی است
        const rim = [];

        for (const [u, v] of edges.values()) {
            if (! edges.has(v * (first + 3) + u)) {
                rim.push(u, v);
            }
        }

        bad.sort((a, b) => b - a);

        for (let i = 0; i < bad.length; i++) {
            const t = bad[i];
            const last = tri.length / 3 - 1;

            tri[t * 3] = tri[last * 3];
            tri[t * 3 + 1] = tri[last * 3 + 1];
            tri[t * 3 + 2] = tri[last * 3 + 2];
            cx[t] = cx[last];
            cy[t] = cy[last];
            cr[t] = cr[last];
            tri.length -= 3;
            cx.length = last;
            cy.length = last;
            cr.length = last;
        }

        for (let i = 0; i < rim.length; i += 2) {
            const t = tri.length / 3;

            tri.push(rim[i], rim[i + 1], p);
            circle(t);
        }
    }

    // مثلث‌های چسبیده به ابرمثلث بی‌معنی‌اند
    const out = [];

    for (let t = 0; t < tri.length / 3; t++) {
        const a = tri[t * 3];
        const b = tri[t * 3 + 1];
        const c = tri[t * 3 + 2];

        if (a < first && b < first && c < first) {
            out.push(a, b, c);
        }
    }

    xs.length = first;
    ys.length = first;

    return out;
};

/* نقشه‌ی «یال → مثلث‌های همسایه»؛ برای برگرداندن مرز لازم است */
const edgeMap = (tri, count) => {
    const map = new Map();

    for (let t = 0; t < tri.length / 3; t++) {
        for (let e = 0; e < 3; e++) {
            const u = tri[t * 3 + e];
            const v = tri[t * 3 + ((e + 1) % 3)];
            const key = u < v ? u * count + v : v * count + u;
            const found = map.get(key);

            if (found) {
                found.push(t);
            } else {
                map.set(key, [t]);
            }
        }
    }

    return map;
};

/*
 * برگرداندن یک پاره‌خط مرزی به مثلث‌بندی.
 *
 * روش استاندارد CDT: تا وقتی یال (u,v) در مش نیست، یکی از یال‌هایی که راهش را
 * می‌بندد چرخانده می‌شود. هر چرخش تعداد یال‌های مزاحم را کم می‌کند، پس حلقه
 * تمام می‌شود؛ سقفِ تکرار فقط برای مواظبت از حالت‌های واگرای عددی است.
 */
const recoverEdge = (tri, xs, ys, count, u, v) => {
    for (let guard = 0; guard < 400; guard++) {
        const map = edgeMap(tri, count);
        const key = u < v ? u * count + v : v * count + u;

        if (map.has(key)) {
            return true;
        }

        let flipped = false;

        for (const [, share] of map) {
            if (share.length !== 2) {
                continue;
            }

            const [t0, t1] = share;
            let a = -1;
            let b = -1;
            let c = -1;

            for (let e = 0; e < 3; e++) {
                const p = tri[t0 * 3 + e];
                const q = tri[t0 * 3 + ((e + 1) % 3)];
                const r = tri[t0 * 3 + ((e + 2) % 3)];
                const other = [tri[t1 * 3], tri[t1 * 3 + 1], tri[t1 * 3 + 2]];

                if (other.includes(p) && other.includes(q)) {
                    a = p;
                    b = q;
                    c = r;

                    break;
                }
            }

            if (a < 0 || a === u || a === v || b === u || b === v) {
                continue;
            }

            if (! segmentsCross(xs[u], ys[u], xs[v], ys[v], xs[a], ys[a], xs[b], ys[b])) {
                continue;
            }

            const d = [tri[t1 * 3], tri[t1 * 3 + 1], tri[t1 * 3 + 2]].find(
                (index) => index !== a && index !== b,
            );

            // چرخش فقط وقتی مجاز است که چهارضلعی محدب باشد، یعنی قطر تازه از
            // قطر امروز عبور کند
            if (d === undefined || ! segmentsCross(xs[a], ys[a], xs[b], ys[b], xs[c], ys[c], xs[d], ys[d])) {
                continue;
            }

            tri[t0 * 3] = a;
            tri[t0 * 3 + 1] = d;
            tri[t0 * 3 + 2] = c;
            tri[t1 * 3] = d;
            tri[t1 * 3 + 1] = b;
            tri[t1 * 3 + 2] = c;
            flipped = true;

            break;
        }

        if (! flipped) {
            return false;
        }
    }

    return false;
};

/**
 * مثلث‌بندی یک چندضلعیِ ساده (احتمالاً مقعر).
 *
 * @param {number[][]} polygon فهرست [x, y] — واحدش هرچه باشد، target هم همان است
 * @param {object} [options]
 * @param {number} [options.target] طول یال دلخواه
 * @param {number} [options.smoothing] تعداد پاس‌های یکنواخت‌سازی داخلی
 * @returns {{positions: Float64Array, indices: Uint32Array, boundary: Int32Array,
 *            loop: Uint32Array, slot: Int32Array, area: number, repaired: number,
 *            unrecovered: number}}
 *   `boundary[i]` اندیس رأسِ متناظر با `polygon[i]` است و `loop` همه‌ی رأس‌های
 *   مرزی را به ترتیبِ پیمایش می‌دهد (شامل نقطه‌های نمونه‌ی میانی).
 */
export const triangulate = (polygon, { target = 3, smoothing = 3 } = {}) => {
    const step = Math.max(1e-6, target);
    const weld = step * 1e-4;
    const source = [];
    const fold = new Int32Array(polygon.length);

    // نقطه‌های تکراریِ پشت سر هم، مثلث با مساحت صفر می‌سازند و دایره‌ی محیطی
    // ندارند؛ همان اول کنار گذاشته می‌شوند ولی نشانی‌شان گم نمی‌شود
    for (let i = 0; i < polygon.length; i++) {
        const x = polygon[i][0];
        const y = polygon[i][1];
        const last = source[source.length - 1];

        if (last && Math.abs(x - last[0]) < weld && Math.abs(y - last[1]) < weld) {
            fold[i] = source.length - 1;

            continue;
        }

        fold[i] = source.length;
        source.push([x, y]);
    }

    while (
        source.length > 2 &&
        Math.abs(source[0][0] - source[source.length - 1][0]) < weld &&
        Math.abs(source[0][1] - source[source.length - 1][1]) < weld
    ) {
        const dropped = source.length - 1;

        source.pop();

        for (let i = 0; i < fold.length; i++) {
            if (fold[i] === dropped) {
                fold[i] = 0;
            }
        }
    }

    if (source.length < 3) {
        throw new Error('polygon needs at least three distinct points');
    }

    /* ---- ۱) نمونه‌گیری مرز ---- */
    const xs = [];
    const ys = [];
    const loop = [];
    const anchor = new Int32Array(source.length);

    for (let i = 0; i < source.length; i++) {
        const [x0, y0] = source[i];
        const [x1, y1] = source[(i + 1) % source.length];

        anchor[i] = xs.length;
        loop.push(xs.length);
        xs.push(x0);
        ys.push(y0);

        const length = Math.hypot(x1 - x0, y1 - y0);
        const cuts = Math.max(1, Math.round(length / step));

        for (let k = 1; k < cuts; k++) {
            const t = k / cuts;

            loop.push(xs.length);
            xs.push(lerp(x0, x1, t));
            ys.push(lerp(y0, y1, t));
        }
    }

    const rim = loop.length;

    /* ---- ۲) نقطه‌های داخلی روی شبکه‌ی مثلثی ---- */
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (let i = 0; i < rim; i++) {
        if (xs[i] < minX) minX = xs[i];
        if (ys[i] < minY) minY = ys[i];
        if (xs[i] > maxX) maxX = xs[i];
        if (ys[i] > maxY) maxY = ys[i];
    }

    // فاصله‌ی کف تا مرز: کمتر از این، مثلثِ تیغه ساخته می‌شود
    const keepOut = (step * 0.62) ** 2;
    const rowHeight = step * Math.sqrt(3) / 2;
    const rows = Math.max(0, Math.floor((maxY - minY) / rowHeight));

    for (let r = 1; r < rows; r++) {
        const y = minY + r * rowHeight;
        const offset = r % 2 ? step / 2 : 0;
        const columns = Math.floor((maxX - minX - offset) / step);

        for (let c = 0; c <= columns; c++) {
            const x = minX + offset + c * step;

            if (! insidePolygon(x, y, xs, ys, loop)) {
                continue;
            }

            let near = false;

            for (let i = 0; i < rim && ! near; i++) {
                const a = loop[i];
                const b = loop[(i + 1) % rim];

                near = segmentDistance2(x, y, xs[a], ys[a], xs[b], ys[b]) < keepOut;
            }

            if (! near) {
                xs.push(x);
                ys.push(y);
            }
        }
    }

    /* ---- ۳) دلونه و برگرداندن مرز ---- */
    const count = xs.length;
    const tri = delaunay(xs, ys, count);
    let repaired = 0;
    let unrecovered = 0;

    {
        const map = edgeMap(tri, count);

        for (let i = 0; i < rim; i++) {
            const u = loop[i];
            const v = loop[(i + 1) % rim];
            const key = u < v ? u * count + v : v * count + u;

            if (map.has(key)) {
                continue;
            }

            if (recoverEdge(tri, xs, ys, count, u, v)) {
                repaired++;
            } else {
                unrecovered++;
            }
        }
    }

    /* ---- ۴) دور ریختن مثلث‌های بیرون از چندضلعی ---- */
    const kept = [];

    for (let t = 0; t < tri.length / 3; t++) {
        const ia = tri[t * 3];
        const ib = tri[t * 3 + 1];
        const ic = tri[t * 3 + 2];
        const gx = (xs[ia] + xs[ib] + xs[ic]) / 3;
        const gy = (ys[ia] + ys[ib] + ys[ic]) / 3;

        if (! insidePolygon(gx, gy, xs, ys, loop)) {
            continue;
        }

        // جهت همه‌ی مثلث‌ها یکی می‌شود تا نرمالِ سطح و علامت مساحت معنی داشته باشد
        if (cross(xs[ia], ys[ia], xs[ib], ys[ib], xs[ic], ys[ic]) < 0) {
            kept.push(ia, ic, ib);
        } else {
            kept.push(ia, ib, ic);
        }
    }

    /* ---- ۵) یکنواخت‌سازیِ داخلی، با نگهبانِ وارونگی ---- */
    const onRim = new Uint8Array(count);

    for (let i = 0; i < rim; i++) {
        onRim[loop[i]] = 1;
    }

    if (smoothing > 0) {
        const neighbours = new Map();

        const link = (a, b) => {
            let set = neighbours.get(a);

            if (! set) {
                set = new Set();
                neighbours.set(a, set);
            }

            set.add(b);
        };

        for (let t = 0; t < kept.length; t += 3) {
            for (let e = 0; e < 3; e++) {
                link(kept[t + e], kept[t + ((e + 1) % 3)]);
                link(kept[t + ((e + 1) % 3)], kept[t + e]);
            }
        }

        const around = new Map();

        for (let t = 0; t < kept.length; t += 3) {
            for (let e = 0; e < 3; e++) {
                const v = kept[t + e];
                const list = around.get(v);

                if (list) {
                    list.push(t);
                } else {
                    around.set(v, [t]);
                }
            }
        }

        for (let pass = 0; pass < smoothing; pass++) {
            for (let i = 0; i < count; i++) {
                if (onRim[i]) {
                    continue;
                }

                const set = neighbours.get(i);

                if (! set || set.size < 3) {
                    continue;
                }

                let sx = 0;
                let sy = 0;

                for (const j of set) {
                    sx += xs[j];
                    sy += ys[j];
                }

                const oldX = xs[i];
                const oldY = ys[i];

                xs[i] = lerp(oldX, sx / set.size, 0.5);
                ys[i] = lerp(oldY, sy / set.size, 0.5);

                let flipped = false;

                for (const t of around.get(i) || []) {
                    const a = kept[t];
                    const b = kept[t + 1];
                    const c = kept[t + 2];

                    if (cross(xs[a], ys[a], xs[b], ys[b], xs[c], ys[c]) <= 1e-12) {
                        flipped = true;

                        break;
                    }
                }

                if (flipped) {
                    xs[i] = oldX;
                    ys[i] = oldY;
                }
            }
        }
    }

    /* ---- ۶) فشرده‌سازی: رأسِ بی‌مثلث نباید بماند ---- */
    const remap = new Int32Array(count).fill(-1);
    const positions = [];

    for (let i = 0; i < kept.length; i++) {
        const v = kept[i];

        if (remap[v] < 0) {
            remap[v] = positions.length / 2;
            positions.push(xs[v], ys[v]);
        }
    }

    const indices = new Uint32Array(kept.length);

    for (let i = 0; i < kept.length; i++) {
        indices[i] = remap[kept[i]];
    }

    const liveLoop = [];
    const slotOf = new Map();

    for (let i = 0; i < rim; i++) {
        const v = remap[loop[i]];

        if (v >= 0) {
            slotOf.set(loop[i], liveLoop.length);
            liveLoop.push(v);
        }
    }

    const boundary = new Int32Array(polygon.length);
    const slot = new Int32Array(polygon.length);

    for (let i = 0; i < polygon.length; i++) {
        const vertex = anchor[fold[i]];

        boundary[i] = remap[vertex];
        slot[i] = slotOf.has(vertex) ? slotOf.get(vertex) : -1;
    }

    let area = 0;

    for (let t = 0; t < indices.length; t += 3) {
        const a = indices[t] * 2;
        const b = indices[t + 1] * 2;
        const c = indices[t + 2] * 2;

        area +=
            Math.abs(
                (positions[b] - positions[a]) * (positions[c + 1] - positions[a + 1]) -
                    (positions[b + 1] - positions[a + 1]) * (positions[c] - positions[a]),
            ) / 2;
    }

    return {
        positions: Float64Array.from(positions),
        indices,
        boundary,
        loop: Uint32Array.from(liveLoop),
        slot,
        area,
        repaired,
        unrecovered,
    };
};

/* ---------------------------------------------------------------------------
 * چیدن قطعه دور بدن
 * ---------------------------------------------------------------------------
 * این مرحله «طراحی» نیست، فقط یک شروع محترمانه است: قطعه روی استوانه‌ای به
 * پهنای بدن پیچیده می‌شود تا وقتی درزها کشیده می‌شوند، دو لبه‌ی یک درز از دو
 * طرفِ درست به هم برسند و پارچه از داخل بدن رد نشود.
 *
 * چرا کششِ این چیدن مهم نیست؟ چون طول استراحتِ همه‌ی قیدهای TriPatch از خودِ
 * الگوی تخت گرفته می‌شود؛ اگر چیدن اولیه قطعه را کش بدهد، همان تکرار اولِ
 * حل‌کننده جمعش می‌کند. برای همین می‌شود ساده و بی‌ادعا چید.
 */

const legTable = (body) => {
    const { level, radii } = body;
    const offset = radii.hip * 0.42;

    return [
        [level.ankle, radii.ankle * 1.25],
        [level.knee, radii.knee],
        [level.crotch, radii.thigh],
        [level.hip, radii.thigh],
    ].map(([y, r]) => [y, r, offset]);
};

const placePiece = (piece, flat, body, options) => {
    const placement = piece.placement || {};
    const zone = placement.zone || 'torso_front';
    const scale = options.scale;
    const gap = options.gap;
    const count = flat.positions.length / 2;
    const positions = new Float32Array(count * 3);
    const grain = new Float64Array(count * 2);

    let minX = Infinity;
    let maxX = -Infinity;
    let minY = Infinity;
    let maxY = -Infinity;

    for (let i = 0; i < count; i++) {
        const x = flat.positions[i * 2];
        const y = flat.positions[i * 2 + 1];

        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
    }

    const spanX = Math.max(1e-6, maxX - minX);

    /*
     * محورِ عمودی‌ای که این قطعه دورش پیچیده — نه همیشه محورِ مانکن.
     *
     * تنه دورِ خودِ بدن می‌پیچد، ولی آستین دورِ بازو و پاچه دورِ ران؛ آن‌ها روی
     * محوری به فاصلهٔ چند ده سانتی‌متر از مرکزِ بدن نشسته‌اند. spinFit باید قطعه
     * را دورِ همین محور بچرخاند، وگرنه آستین را دورِ تنه می‌گرداند و از عمق
     * جابه‌جا می‌کند. اندازه گرفتیم: مرکزِ آستینِ چپ z=−۹٫۲ و راست z=+۶٫۰ — یعنی
     * یکی پشتِ بدن و دیگری جلوی آن، و لباس یک‌وری می‌نشیند.
     */
    let axis = 0;
    const height = body.level.top;
    const top = (placement.y_top ?? 0.8) * height;
    /*
     * بازه‌ی زاویه‌ای همان‌طور که آمده به کار می‌رود.
     *
     * سرور برای هر نمونه زاویه‌ی مطلق می‌دهد (۰ = مرکز جلو) و خودش هم قطعه‌ی
     * آینه‌شده را آینه کرده. اگر اینجا دوباره با flip منفی شود، دو نیمه‌ی یک
     * تنه روی هم می‌افتند — سنجیده شد: هر دو نیمه‌ی جلوی پیراهن سر یک سمت بدن
     * می‌نشستند. flip فقط برای انتخاب سمتِ اندام (آستین و پاچه) می‌ماند.
     */
    const u0 = placement.u0 ?? -1.2;
    const u1 = placement.u1 ?? 1.2;
    /*
     * کدام سمت بدن؟ اول حرف خودِ بسته (`side`)، بعد آینه بودن نمونه، و در آخر
     * شماره‌ی نمونه. سرور قطعه‌ی آینه‌شده را خودش آینه کرده، پس اینجا فقط جای
     * نشستنش عوض می‌شود، نه شکلش.
     *
     * علامتش باید با قرارداد خودِ سرور بخواند: زاویهٔ منفی سمتِ چپ است
     * (`u < 0 ? 'left' : 'right'`) و زاویه به x با سینوس می‌رسد، پس چپ یعنی x
     * منفی. این‌جا برعکس نوشته شده بود و هر دو آستین سرِ سمتِ اشتباه چیده
     * می‌شدند؛ بعد قیدِ درز آن‌ها را از روی تنه به سمتِ درست می‌کشید و هر کدام
     * از راهی می‌رفت. همین بود که لباس یک‌وری می‌نشست: مرکزِ آستینِ چپ در عمق
     * z=−۹٫۲ و راست z=+۶٫۰ — یکی پشتِ بدن، یکی جلویش.
     */
    const side =
        piece.side === 'right' ? 1 : piece.side === 'left' ? -1 : placement.flip || piece.instance % 2 ? -1 : 1;
    const legs = zone.startsWith('leg') ? legTable(body) : null;
    /*
     * اگر بسته شعاع خودش را گفته باشد، همان حرف آخر است.
     *
     * قطعه‌ای که از دور بدن بلندتر است (نوار یقه، کمربند بلند، دامن کلوش) روی
     * دایره‌ی خودش می‌نشیند تا فشرده نشود؛ درزها بعد آن را روی بدن می‌کشند.
     */
    const hint = placement.radius ? placement.radius * scale : body.radii?.[placement.radius_hint];

    for (let i = 0; i < count; i++) {
        const x = flat.positions[i * 2];
        const y = flat.positions[i * 2 + 1];

        grain[i * 2] = x * scale;
        grain[i * 2 + 1] = y * scale;

        // y الگو رو به پایین است؛ همین‌جا برمی‌گردد تا بقیه‌ی کد فقط با دستگاه
        // سه‌بعدی سروکار داشته باشد
        const world = top - (y - minY) * scale;
        const u = lerp(u0, u1, (x - minX) / spanX);

        let rx;
        let rz;
        let center = 0;

        if (zone === 'sleeve') {
            // آستین دور بازو می‌پیچد، نه دور تنه؛ محورش سرشانه است
            const shoulder = body.level.shoulder - 0.035;
            const radius = sampleTable(body.armTable, world - shoulder)[0];

            rx = radius + gap;
            rz = radius + gap;
            // محورِ بازو مماس بر تنه است؛ ببینید armOffset در نماگر
            center = side * (body.armOffset ?? (body.radii.shoulder * 0.87));
        } else if (legs) {
            const row = sampleTable(legs, world);

            rx = row[0] + gap;
            rz = row[0] + gap;
            center = side * row[1];
        } else {
            const row = sampleTable(body.profile, world);

            rx = Math.max(row[0], hint || 0) + gap;
            rz = Math.max(row[1], (hint || 0) * 0.8) + gap;
        }

        /*
         * یک تلنگر ریزِ قطعی روی شعاع.
         *
         * قطعه‌ی کاملاً هموار روی استوانه‌ی کاملاً هموار هیچ دلیلی برای چین
         * خوردن ندارد و پارچه صاف می‌ماند. hash همان تلنگر است و چون از شماره‌ی
         * رأس می‌آید، هر بار باز کردن صفحه همان چین‌ها را می‌سازد.
         */
        const nudge = 1 + (hash(i) - 0.5) * options.jitter;

        positions[i * 3] = center + rx * nudge * Math.sin(u);
        positions[i * 3 + 1] = world;
        positions[i * 3 + 2] = rz * nudge * Math.cos(u);
        axis += center / count;
    }

    return { positions, grain, minX, maxX, minY, maxY, top, axis };
};

/* ---------------------------------------------------------------------------
 * کمان‌های مرزی و جفت‌سازی درز
 * ------------------------------------------------------------------------- */

/*
 * کمانِ میان دو رأسِ چندضلعی، رو به جلو و با پیچش دور مسیر.
 *
 * دو نکته که بدون آن‌ها درز کج درمی‌آید:
 *   • طول کمان از خودِ چندضلعی حساب می‌شود، نه از عدد `length` بسته. آن عدد
 *     برای بررسی سلامت خوب است ولی منبع حقیقت نیست؛ اگر یک روز خط‌شکسته‌ی سرور
 *     ریزتر شود، مرورگر نباید درز را روی عددِ کهنه بدوزد.
 *   • پاره‌خط‌هایی که داخل یک ساسون افتاده‌اند طول صفر حساب می‌شوند. ساسون
 *     بسته می‌شود و آن دو ساق در لباس دوخته‌شده اصلاً وجود ندارند؛ اگر طولشان
 *     را در خط کمر بشماریم، دامن نسبت به بالاتنه به اندازه‌ی همه‌ی ساسون‌ها
 *     جابه‌جا دوخته می‌شود.
 */
/*
 * بریدنِ ساسون رأس تازه به چندضلعی اضافه می‌کند و همه‌ی شماره‌های بعدی را جلو
 * می‌اندازد. بسته با شماره‌های اصلی حرف می‌زند، پس هر اندیسی که از بسته می‌آید
 * باید از این نقشه رد شود.
 */
const mapIndex = (state, index) => {
    const mapped = state.notched.map[index];

    return mapped === undefined ? index : mapped;
};

const arcOf = (state, from, to) => {
    const { loop, slot, positions } = state;
    const size = loop.length;
    const start = slot[from];
    const end = slot[to];

    if (start < 0 || end < 0 || size === 0) {
        return null;
    }

    const slots = [start];
    let walk = start;

    while (walk !== end) {
        walk = (walk + 1) % size;
        slots.push(walk);

        if (slots.length > size) {
            return null;
        }
    }

    const arc = new Float64Array(slots.length);
    let total = 0;

    for (let i = 1; i < slots.length; i++) {
        const previous = slots[i - 1];

        if (! state.collapsed[previous]) {
            const a = loop[previous] * 2;
            const b = loop[slots[i]] * 2;

            total += Math.hypot(positions[a] - positions[b], positions[a + 1] - positions[b + 1]);
        }

        arc[i] = total;
    }

    const t = new Float64Array(slots.length);

    for (let i = 0; i < slots.length; i++) {
        t[i] = total > 1e-9 ? arc[i] / total : i / Math.max(1, slots.length - 1);
    }

    return {
        vertices: slots.map((position) => loop[position]),
        slots,
        t,
        length: total,
    };
};

/*
 * جفت کردن دو کمان با کسرِ طول کمانی.
 *
 * دو سمت یک درز هیچ‌وقت تعداد رأس یکسان ندارند (یکی خط راست است و دیگری کمانِ
 * حلقه‌ی آستین). پس هر رأس با کسرِ طولش روی کمان شناخته می‌شود و جفتش رأسی است
 * که نزدیک‌ترین کسر را دارد.
 *
 * جفت‌سازی از هر دو طرف انجام می‌شود، نه فقط از سمت پرتراکم‌تر: اگر رأسی از
 * سمتِ ریزتر بی‌سوزن بماند، همان‌جا پارچه از درز بیرون می‌زند و درز مثل زیپِ باز
 * دیده می‌شود. مجموعه‌ی جفت‌ها یکتا می‌شود تا یک سوزن دو بار زده نشود.
 *
 * `ease` (اختلاف طول دو سمت) خودبه‌خود رعایت می‌شود: چون مبنا «کسر» است و نه
 * «سانتی‌متر»، سمتِ بلندتر به‌طور یکنواخت روی سمتِ کوتاه‌تر جمع می‌شود — همان
 * کاری که خیاط با پس‌دوزیِ یکنواخت می‌کند.
 */
const pairArcs = (arcA, arcB, reverse) => {
    const tb = reverse ? Float64Array.from(arcB.t, (value) => 1 - value) : arcB.t;
    const order = reverse
        ? Array.from(tb, (_, i) => i).sort((one, two) => tb[one] - tb[two])
        : Array.from(tb, (_, i) => i);
    const pairs = [];
    const second = [];
    const weight = [];
    const seen = new Set();

    /*
     * سوزن روی پاره‌خطِ سمتِ روبه‌رو می‌نشیند، نه لزوماً سرِ یک رأس.
     *
     * پیش‌تر هر رأس به نزدیک‌ترین رأسِ سمتِ دیگر بسته می‌شد. جایی که یک سمت ریزتر
     * بود، چند رأسش روی *یک* رأس می‌افتادند و درز نوارِ پارچه را روی یک نقطه جمع
     * می‌کرد. اندازه گرفته شد روی الگوی تخت، پس فیزیک دخالتی نداشت: در درزِ
     * سرشانه دو سوزنِ پی‌درپی یک سمتش ۱٫۴۴ سانتی‌متر جلو می‌رفت و سمتِ دیگرش صفر.
     * آن نوار لِه می‌شد و همسایه‌اش جِر می‌خورد — روی مانکن مثلثِ سی‌برابر کشیده
     * در سرشانه و حلقه، و لبه‌های تیکه‌تیکه.
     *
     * حالا برای هر رأسِ این سمت، جای هم‌کسرش روی کمانِ آن سمت پیدا می‌شود: کدام
     * پاره‌خط، و چه کسری از آن. پس هر سوزن شریکِ خودش را دارد — نه بادبزن، و نه
     * کم شدنِ شمارِ سوزن (که خودش لباس را شُل می‌کرد و لباسِ بی‌بند را می‌انداخت).
     */
    const at = (value) => {
        let low = 0;
        let high = order.length - 1;

        while (low < high) {
            const middle = (low + high) >> 1;

            if (tb[order[middle]] < value) {
                low = middle + 1;
            } else {
                high = middle;
            }
        }

        const after = Math.max(1, low);
        const before = after - 1;
        const span = tb[order[after]] - tb[order[before]];

        return {
            from: order[before],
            to: order[after],
            share: span > 1e-9 ? clamp((value - tb[order[before]]) / span, 0, 1) : 0,
        };
    };

    const add = (ia, ib, ic, w) => {
        // سوزنی که عملاً سرِ یک رأس می‌نشیند، همان جفتِ ساده بماند
        const snapped = w < 0.02 ? 0 : (w > 0.98 ? 1 : w);
        const one = snapped === 1 ? ic : ib;
        const two = snapped === 1 ? ib : ic;
        const value = snapped === 1 ? 0 : snapped;
        const key = `${ia}:${one}:${value.toFixed(2)}`;

        if (ia === one && value === 0) {
            return;
        }

        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        pairs.push(ia, one);
        second.push(two);
        weight.push(value);
    };

    for (let i = 0; i < arcA.t.length; i++) {
        const spot = at(arcA.t[i]);

        add(arcA.vertices[i], arcB.vertices[spot.from], arcB.vertices[spot.to], spot.share);
    }

    return { pairs, second, weight };
};

/* ---------------------------------------------------------------------------
 * ساسون‌های بریده‌نشده
 * ---------------------------------------------------------------------------
 * ساسونی که سرور با legs/apex می‌دهد هنوز روی مسیر بریده نشده: چندضلعی از روی
 * دهانه‌ی ساسون صاف رد می‌شود. چنین ساسونی را نمی‌شود دوخت، چون دو ساقی وجود
 * ندارد که به هم برسند.
 *
 * پس همان کاری را می‌کنیم که خیاط روی کاغذ می‌کند: دهانه را از مرز می‌بریم و
 * نوکِ ساسون را به مسیر اضافه می‌کنیم. از اینجا به بعد ساسون دقیقاً مثل ساسونِ
 * بریده رفتار می‌کند و همان کد دوختش می‌کند.
 */
const notchDarts = (piece) => {
    const polygon = piece.polygon.map((point) => [point[0], point[1]]);
    const darts = [];
    const map = polygon.map((point, index) => index);

    const open = (piece.darts || []).filter(
        (dart) => dart && dart.legs && dart.apex && ! Number.isInteger(dart.start),
    );

    /*
     * نوکِ دو ساسون نباید روی یک نقطه بیفتد.
     *
     * روی الگو، ساسون سینه و ساسون کمر هر دو به «نقطه‌ی سینه» اشاره می‌کنند و
     * سرور هم همان یک نقطه را برای هر دو می‌دهد. اگر هر دو گوه را با همان نوکِ
     * مشترک از مرز ببریم، چندضلعی در آن نقطه به خودش گره می‌خورد (bowtie) و
     * دیگر ساده نیست؛ مثلث‌بندی همان‌جا به‌هم می‌ریزد. اندازه‌گیری‌اش روی لباس
     * غلافی: یک مثلث ۴۹۳ برابر کشیده می‌شد و روی سینه یک حفره‌ی سه‌گوش می‌ماند.
     *
     * کاری که خیاط می‌کند همین است: نوک ساسون چند میلی‌متر پیش از نقطه‌ی سینه
     * تمام می‌شود، وگرنه روی سینه یک قله‌ی تیز می‌افتد. پس نوکِ ساسونِ دوم را
     * به همان اندازه عقب می‌کشیم.
     */
    const tips = [];

    const freeTip = (dart) => {
        const [l0, l1] = dart.legs;
        const mouth = [(l0[0] + l1[0]) / 2, (l0[1] + l1[1]) / 2];
        let apex = [dart.apex[0], dart.apex[1]];

        for (let attempt = 0; attempt < 6; attempt++) {
            const clash = tips.some((tip) => Math.hypot(tip[0] - apex[0], tip[1] - apex[1]) < 0.4);

            if (! clash) {
                break;
            }

            const dx = apex[0] - mouth[0];
            const dy = apex[1] - mouth[1];
            const length = Math.hypot(dx, dy) || 1;

            apex = [apex[0] - (dx / length) * 0.7, apex[1] - (dy / length) * 0.7];
        }

        tips.push(apex);

        return apex;
    };

    /* نزدیک‌ترین پاره‌خط مرزی به یک نقطه */
    const nearestSegment = (point) => {
        let best = -1;
        let bestCost = Infinity;

        for (let i = 0; i < polygon.length; i++) {
            const a = polygon[i];
            const b = polygon[(i + 1) % polygon.length];
            const cost = segmentDistance2(point[0], point[1], a[0], a[1], b[0], b[1]);

            if (cost < bestCost) {
                bestCost = cost;
                best = i;
            }
        }

        return best;
    };

    for (const dart of open) {
        const [l0, l1] = dart.legs;

        // دهانه‌ی صفر یعنی گوه‌ای بی‌عرض؛ بریدنش فقط مثلث تیغه می‌سازد
        if (Math.hypot(l1[0] - l0[0], l1[1] - l0[1]) < 0.05) {
            continue;
        }

        /*
         * دو ساق ساسون لزوماً روی یک پاره‌خط نیستند.
         *
         * دهانه‌ی ساسون سینه روی درز پهلو سه‌ونیم سانتی‌متر باز است و مسیرِ
         * خط‌شکسته آنجا هر یک سانتی‌متر یک رأس دارد؛ یعنی میان دو ساق چند رأس
         * دیگر هست. اگر گوه را همان‌جا «اضافه» کنیم بی‌آنکه آن رأس‌ها را
         * برداریم، مرز از روی خودش برمی‌گردد و چندضلعی دیگر ساده نیست —
         * مثلث‌بندی همان‌جا می‌ترکد. اندازه‌گیری‌اش: یک مثلث ۶۲۵ برابر کشیده
         * می‌شد. پس بازه‌ی میان دو ساق برداشته و به‌جایش ساق→نوک→ساق می‌نشیند.
         */
        const first = nearestSegment(l0);
        const second = nearestSegment(l1);

        if (first < 0 || second < 0) {
            continue;
        }

        const [from, to, ordered] =
            first <= second ? [first, second, [l0, l1]] : [second, first, [l1, l0]];

        // بازه‌ای که دور مسیر می‌پیچد را دست نمی‌زنیم؛ نادر است و بریدنش خطاخیز
        if (to - from > polygon.length / 2) {
            continue;
        }

        const apex = freeTip(dart);
        const removed = to - from;

        polygon.splice(from + 1, removed, [ordered[0][0], ordered[0][1]], [apex[0], apex[1]], [
            ordered[1][0],
            ordered[1][1],
        ]);

        const shift = 3 - removed;

        for (let i = 0; i < map.length; i++) {
            if (map[i] > from) {
                map[i] = Math.max(from + 1, map[i] + shift);
            }
        }

        darts.push({ start: from + 1, apex: from + 2, end: from + 3, intake: dart.intake, cut: false });
    }

    // ساسون‌هایی که سرور خودش بریده؛ فقط باید نوکشان را روی مسیر پیدا کنیم
    for (const dart of piece.darts || []) {
        if (! dart || ! Number.isInteger(dart.start) || ! Number.isInteger(dart.end)) {
            continue;
        }

        darts.push({
            start: map[dart.start],
            end: map[dart.end],
            tip: dart.apex || null,
            intake: dart.intake,
            cut: true,
        });
    }

    return { polygon, darts, map };
};

/* نوکِ ساسون روی کمان: نزدیک‌ترین رأس به نقطه‌ی apex، وگرنه میانه‌ی طول کمان */
const dartApex = (state, from, to, tip) => {
    const arc = arcOf(state, from, to);

    if (! arc) {
        return null;
    }

    let best = Math.floor(arc.slots.length / 2);

    if (tip) {
        let bestGap = Infinity;

        for (let i = 0; i < arc.vertices.length; i++) {
            const at = arc.vertices[i] * 2;
            const gap = Math.hypot(state.positions[at] - tip[0], state.positions[at + 1] - tip[1]);

            if (gap < bestGap) {
                bestGap = gap;
                best = i;
            }
        }
    } else {
        for (let i = 0; i < arc.t.length; i++) {
            if (arc.t[i] >= 0.5) {
                best = i;

                break;
            }
        }
    }

    return { arc, index: clamp(best, 1, arc.slots.length - 2) };
};

/* ---------------------------------------------------------------------------
 * ساخت کاملِ دوخت
 * ------------------------------------------------------------------------- */

const DEFAULTS = {
    layers: ['outer'],
    gap: 0.012,
    jitter: 0.004,
    smoothing: 3,
    seamDuration: 0.6,
    seamStiffness: 0.7,
    // سختیِ لولای درز؛ ۰٫۵ از سنجه آمد: مثلث خراب روی هشت لباس ۴۱ (۰٫۲۵) به ۲۹
    seamHinge: 0.5,
    support: { band: 0.03, strength: 0.3 },
    // بالاتر از این تعداد رأس، حلقه‌ی حل روی دستگاه معمولی از ۳۰ فریم می‌افتد
    comfortableVertices: 4200,
};

const IDENTITY = new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]);

/*
 * بیشترین جابه‌جاییِ پهلوییِ جمع‌شدهٔ یک قطعه در جفت‌وجورِ صُلب، متر.
 *
 * قطعهٔ روی محورِ خودِ بدن (تنه، دامن) جا دارد: بدن ۱۵ سانتی‌متر شعاع دارد و شش
 * سانتی‌متر جابه‌جایی اصلاحِ کوچکی است که درزِ سرشانه را مچ می‌کند — بی آن،
 * سرشانه ۱۸٫۷ سانتی‌متر باز شروع می‌کند.
 *
 * قطعهٔ روی اندام (آستین، پاچه) جا ندارد: بازو ۴٫۵ سانتی‌متر شعاع دارد و حتی سه
 * سانتی‌متر لغزش آستین را از رویش کنار می‌برد؛ اندازه گرفتیم، پوششِ دورِ بازو از
 * ۳۶۰ درجه به ۵۰ افتاد و بازو لخت بیرون ماند. پس برای اندام صفر.
 */
const LATERAL_ROOM = 0.06;
const LIMB_ROOM = 0;

/**
 * ساخت پارچه‌ی دوخته‌شده از بستهٔ سرور.
 *
 * @param {object} payload خروجی DrapePayloadService::payload
 * @param {object} body جدول‌های مانکن: { level, radii, profile, armTable, armLength }
 * @param {object} [options]
 * @returns {{patches: object[], seams: SeamSet[], meshes: object[], stats: object}}
 */

/*
 * خم کردنِ قطعه روی خطِ دوختش، به‌صورت هموار.
 *
 * تا اینجا هر قطعه با یک تبدیلِ صُلب سرِ جایش می‌رفت. برای قطعه‌ای که به **دو**
 * قطعه‌ی دیگر دوخته می‌شود این کافی نیست: سرآستین باید هم به حلقه‌ی جلو برسد
 * هم به حلقه‌ی پشت، یوک هم به دو جلو هم به تنه‌ی پشت. یک چرخش و یک جابه‌جایی
 * نمی‌تواند هر دو را راضی کند — قطعه باید خم شود.
 *
 * نخستین تلاشم برای نوار یقه این بود که هر رأس را نسبت به «نزدیک‌ترین رأسِ
 * درز» بگذارم. نتیجه‌اش را اندازه گرفتم و بد بود: هنگام چیدن، کششِ مثلث‌ها به
 * ۱۲٫۴ برابر می‌رسید و ۲۷۱ مثلث خراب می‌شد، چون نگاشت در مرزِ میان دو لنگر
 * می‌پرید. حل‌کننده بعداً درستش می‌کرد، ولی شروع کردن از یک قطعه‌ی مچاله،
 * کارِ درستی نیست.
 *
 * راهِ درست، همانی است که در هندسه به آن «میدانِ جابه‌جاییِ همساز» می‌گویند:
 * جابه‌جاییِ رأس‌های درز را می‌دانیم (باید روی هدفشان بنشینند)؛ جابه‌جاییِ
 * بقیه‌ی رأس‌ها را با میانگین‌گیری از همسایه‌هایشان پخش می‌کنیم تا هیچ‌جا پرش
 * نداشته باشد. قطعه خم می‌شود، ولی نرم.
 *
 * سقفِ هر لنگر هشت سانتی‌متر است: اگر همسایه‌ی بدجا افتاده باشد، نباید قطعه را
 * پرت کند.
 */
const warpToSeams = (patches, seams, rounds = 400) => {
    const at = new Map();

    patches.forEach((entry, index) => at.set(entry.patch, index));

    /*
     * فقط قطعه‌های کوچک خم می‌شوند.
     *
     * یقه و سرآستین و یوک کوچک‌اند و باید خم شوند تا به دو همسایه‌شان برسند.
     * تنه و دامن بزرگ‌اند و شکلشان همان فرمِ لباس است؛ خم کردنشان برای بستنِ
     * یک درز، کششی می‌سازد که به کل لباس می‌نشیند. اندازه گرفتم: خم کردنِ دامنِ
     * لباس غلافی کششِ چیدن را از ۲٫۳ به ۷٫۳ برابر برد. آن‌جا کار را به
     * حل‌کننده می‌سپاریم، که برای همین ساخته شده.
     */
    let biggest = 0;

    for (const entry of patches) {
        biggest = Math.max(biggest, entry.patch.count);
    }

    let warped = 0;

    for (const entry of patches) {
        /*
         * یقه، آستین، مچ‌بند و نوارها خم می‌شوند؛ تنه و دامن نه.
         *
         * این مرز از اندازه‌گیری آمد، نه از سلیقه: خم کردنِ یقه و سرآستینِ
         * پیراهن، مثلث‌های خرابِ پایانی را از ۲ به ۰ رساند؛ ولی خم کردنِ دامنِ
         * لباس غلافی کششِ چیدن را از ۲٫۳ به ۵٫۶ برابر برد و خطای درز را هم بهتر
         * نکرد. تنه و دامن شکلِ خودِ لباس‌اند و کشیدنشان به کل لباس می‌نشیند.
         */
        if (! ['collar', 'sleeve', 'detail'].includes(entry.piece.role) || entry.patch.count > biggest * 0.6) {
            continue;
        }

        const patch = entry.patch;
        const count = patch.count;
        const move = new Float64Array(count * 3);
        const anchored = new Uint8Array(count);
        let joins = 0;

        for (const seam of seams) {
            if (! seam.b || seam.b === seam.a) {
                continue;
            }

            const mineFirst = seam.a === patch;

            if (! mineFirst && seam.b !== patch) {
                continue;
            }

            const theirs = mineFirst ? seam.b : seam.a;

            if (at.get(theirs) === undefined) {
                continue;
            }

            joins++;

            for (let i = 0; i < seam.count; i++) {
                const v = seam.pairs[i * 2 + (mineFirst ? 0 : 1)];
                const to = seam.pairs[i * 2 + (mineFirst ? 1 : 0)] * 3;
                const dx = theirs.positions[to] - patch.positions[v * 3];
                const dy = theirs.positions[to + 1] - patch.positions[v * 3 + 1];
                const dz = theirs.positions[to + 2] - patch.positions[v * 3 + 2];
                const size = Math.hypot(dx, dy, dz);
                // نیمی از فاصله: سرِ دیگرِ درز هم به همین اندازه به این‌سو می‌آید
                const take = (size > 0.08 ? 0.08 / size : 1) * 0.5;

                if (anchored[v]) {
                    // رأسی که روی دو درز است: میانگینِ دو خواسته
                    move[v * 3] = (move[v * 3] + dx * take) / 2;
                    move[v * 3 + 1] = (move[v * 3 + 1] + dy * take) / 2;
                    move[v * 3 + 2] = (move[v * 3 + 2] + dz * take) / 2;

                    continue;
                }

                anchored[v] = 1;
                move[v * 3] = dx * take;
                move[v * 3 + 1] = dy * take;
                move[v * 3 + 2] = dz * take;
            }
        }

        let anchors = 0;
        let need = 0;

        for (let v = 0; v < count; v++) {
            if (! anchored[v]) {
                continue;
            }

            anchors++;
            need += Math.hypot(move[v * 3], move[v * 3 + 1], move[v * 3 + 2]);
        }

        if (anchors === 0 || anchors === count) {
            continue;
        }

        /*
         * فقط آن‌جا خم می‌کنیم که جابه‌جایی صُلب از عهده‌اش برنمی‌آید.
         *
         * خم کردن — هرچند هموار — پارچه را کمی می‌کشد، پس باید دلیل داشته باشد.
         * دلیلش این است: اگر همه‌ی لنگرهای یک قطعه بخواهند به یک سمت بروند،
         * همان جابه‌جاییِ صُلبِ پیشین کافی بود و خم کردن فقط پارچه را می‌کشد.
         * ولی اگر لنگرها به سمت‌های مخالف بکشند — سرآستین به حلقه‌ی جلو و پشت،
         * یقه به سه قطعه — هیچ حرکتِ صُلبی جوابشان نیست و قطعه باید خم شود.
         *
         * پس «باقی‌ماندهٔ ناهمسو» را می‌سنجیم: میانگینِ فاصله‌ی هر خواسته از
         * خواسته‌ی میانگین. اندازه‌گیری روی لباس غلافی نشان داد خم کردنِ بی‌دلیلِ
         * دامن، کششِ چیدن را از ۲٫۳ به ۷٫۳ برابر می‌برد و خطای نهایی را هم بهتر
         * نمی‌کند.
         */
        let mx = 0;
        let my = 0;
        let mz = 0;

        for (let v = 0; v < count; v++) {
            if (! anchored[v]) {
                continue;
            }

            mx += move[v * 3];
            my += move[v * 3 + 1];
            mz += move[v * 3 + 2];
        }

        mx /= anchors;
        my /= anchors;
        mz /= anchors;

        let spread = 0;

        for (let v = 0; v < count; v++) {
            if (! anchored[v]) {
                continue;
            }

            spread += Math.hypot(move[v * 3] - mx, move[v * 3 + 1] - my, move[v * 3 + 2] - mz);
        }

        /*
         * قطعه‌ای که ده‌ها درزِ جدا دارد، جای خم‌کردن نیست.
         *
         * این را از داده گرفتم: کمربندِ دامن کلوش به دوازده کمانِ پخش‌شده دور
         * تمام کمر دوخته است. خم‌کردنش نوار را می‌کشد و بعد جابه‌جاییِ صُلبِ
         * بعدی، پنل‌های دامن را دنبال نوارِ کشیده می‌برد؛ اندازه‌گیری: درز
         * پهلوی دامن از ۱۳ به ۳۸ سانتی‌متر باز می‌شد. خم‌کردن برای ناجوریِ
         * موضعی است — یقه و سرآستین — نه برای جابه‌جا کردنِ نوارِ بلند.
         */
        if (joins > 4 || need / anchors < 0.02 || spread / anchors < 0.015) {
            continue;
        }

        /* همسایگی از مثلث‌ها */
        const indices = entry.mesh.indices;
        const heads = new Int32Array(count + 1);

        for (let t = 0; t < indices.length; t += 3) {
            for (let k = 0; k < 3; k++) {
                heads[indices[t + k] + 1] += 2;
            }
        }

        for (let v = 0; v < count; v++) {
            heads[v + 1] += heads[v];
        }

        const fill = heads.slice(0, count);
        const near = new Int32Array(heads[count]);

        for (let t = 0; t < indices.length; t += 3) {
            const tri = [indices[t], indices[t + 1], indices[t + 2]];

            for (let k = 0; k < 3; k++) {
                near[fill[tri[k]]++] = tri[(k + 1) % 3];
                near[fill[tri[k]]++] = tri[(k + 2) % 3];
            }
        }

        /* پخش کردن جابه‌جایی: میانگین همسایه‌ها، لنگرها ثابت */
        const next = new Float64Array(move);

        for (let round = 0; round < rounds; round++) {
            let drift = 0;

            for (let v = 0; v < count; v++) {
                if (anchored[v]) {
                    continue;
                }

                let sx = 0;
                let sy = 0;
                let sz = 0;
                const from = heads[v];
                const to = heads[v + 1];

                if (to === from) {
                    continue;
                }

                for (let k = from; k < to; k++) {
                    const n = near[k] * 3;

                    sx += move[n];
                    sy += move[n + 1];
                    sz += move[n + 2];
                }

                const n = to - from;

                next[v * 3] = sx / n;
                next[v * 3 + 1] = sy / n;
                next[v * 3 + 2] = sz / n;
                drift = Math.max(drift, Math.abs(next[v * 3] - move[v * 3]));
            }

            move.set(next);

            if (drift < 1e-6) {
                break;
            }
        }

        const positions = patch.positions;

        for (let i = 0; i < positions.length; i++) {
            positions[i] += move[i];
        }

        patch.remember();
        warped++;
    }

    return warped;
};

/*
 * خم کردنِ نوار روی خطِ دوختش — به‌جای جابه‌جا کردنش.
 *
 * یقه و نوارها با جابه‌جایی و چرخش سر جایشان نمی‌آیند و دلیلش هندسی است: خط
 * یقه‌ی لباس دایره نیست، در جلو گود می‌شود. نوارِ یقه ۵۴ سانتی‌متر است و دورِ
 * گردن ۳۷؛ هیچ چرخش و جابه‌جایی‌ای نوارِ ۵۴ را روی دایره‌ی ۳۷ نمی‌نشاند. ولی
 * روی خودِ خط یقه — که همان ۵۴ سانتی‌متر است — عیناً می‌نشیند.
 *
 * پس نوار را خم می‌کنیم: هر رأس نوار نسبت به نزدیک‌ترین رأسِ درزش (روی الگوی
 * تخت) یک فاصله دارد؛ همان فاصله را در دستگاهِ محلیِ همان نقطه روی خط دوخت
 * می‌گذاریم. راستای «طول» همان مسیرِ خط دوخت است و راستای «پهنا» عمود بر آن.
 *
 * چرا این کار پارچه را خراب نمی‌کند؟ چون طولِ دو لبه با هم برابر است (خودِ
 * الگو این را تضمین کرده) و نگاشت در راستای طول، طول را حفظ می‌کند. نوار
 * فقط خم می‌شود، کشیده نمی‌شود.
 */
const bendStrips = (patches, seams) => {
    const at = new Map();

    patches.forEach((entry, index) => at.set(entry.patch, index));

    let bent = 0;

    for (const entry of patches) {
        if (! isStrip(entry.piece)) {
            continue;
        }

        /* جفت‌رأس‌های همه‌ی درزهای این نوار: رأس خودی ⇒ نقطه‌ی هدف */
        const own = [];
        const goal = [];

        for (const seam of seams) {
            if (! seam.b || seam.b === seam.a) {
                continue;
            }

            const mineFirst = seam.a === entry.patch;

            if (! mineFirst && seam.b !== entry.patch) {
                continue;
            }

            const mine = mineFirst ? seam.a : seam.b;
            const theirs = mineFirst ? seam.b : seam.a;

            if (at.get(theirs) === undefined) {
                continue;
            }

            for (let i = 0; i < seam.count; i++) {
                const v = seam.pairs[i * 2 + (mineFirst ? 0 : 1)];
                const to = seam.pairs[i * 2 + (mineFirst ? 1 : 0)] * 3;

                own.push(v);
                goal.push(theirs.positions[to], theirs.positions[to + 1], theirs.positions[to + 2]);
            }
        }

        if (own.length < 3) {
            continue;
        }

        /* مرتب کردن روی طولِ خودِ نوار تا مسیر هدف پیوسته شود */
        const grain = entry.mesh.grain;
        const order = own.map((v, i) => i).sort((a, b) => grain[own[a] * 2] - grain[own[b] * 2]);
        const path = order.map((i) => [goal[i * 3], goal[i * 3 + 1], goal[i * 3 + 2]]);
        const anchors = order.map((i) => own[i]);
        const positions = entry.patch.positions;

        for (let v = 0; v < entry.patch.count; v++) {
            /* نزدیک‌ترین رأسِ درز روی الگوی تخت */
            let best = 0;
            let bestGap = Infinity;

            for (let k = 0; k < anchors.length; k++) {
                const gap = Math.abs(grain[anchors[k] * 2] - grain[v * 2]);

                if (gap < bestGap) {
                    bestGap = gap;
                    best = k;
                }
            }

            const next = Math.min(anchors.length - 1, best + 1);
            const prev = Math.max(0, best - 1);
            let tx = path[next][0] - path[prev][0];
            let tz = path[next][2] - path[prev][2];
            const ty = path[next][1] - path[prev][1];
            const span = Math.hypot(tx, ty, tz) || 1;

            tx /= span;
            tz /= span;

            const along = grain[v * 2] - grain[anchors[best] * 2];
            const across = grain[v * 2 + 1] - grain[anchors[best] * 2 + 1];

            positions[v * 3] = path[best][0] + tx * along;
            positions[v * 3 + 1] = path[best][1] + (ty / span) * along - across;
            positions[v * 3 + 2] = path[best][2] + tz * along;
        }

        entry.patch.remember();
        bent++;
    }

    return bent;
};

/* آیا این قطعه یک نوار است؟ باریک و بلند، و دور چیزی می‌پیچد */
const isStrip = (piece) => {
    if ((piece.placement?.zone || '') === 'collar') {
        return true;
    }

    return !! piece.placement?.radius && (piece.role === 'detail' || piece.role === 'collar');
};

/*
 * چیدن از روی گرافِ دوخت، نه با حدسِ مستقل هر قطعه.
 *
 * چیدن اولیه تا اینجا برای هر قطعه جدا حساب می‌شد: ارتفاع، شعاع و بازه‌ی
 * زاویه‌ای از روی نقش و اندازه‌ی خودش. نتیجه‌اش این بود که دو سرِ یک درز
 * ده‌ها سانتی‌متر از هم دور شروع می‌کردند و قید درز — که فقط در خط راست
 * می‌کشد — پارچه را از روی بدن می‌کشید.
 *
 * کاری که خیاط می‌کند فرق دارد: یک قطعه را روی مانکن می‌گذارد، بعد قطعه‌ی
 * بعدی را **کنارِ همان درزی که به آن دوخته می‌شود** می‌گذارد، و همین‌طور تا
 * آخر. این تابع همان است: بزرگ‌ترین قطعه لنگر می‌شود و بقیه با یک پیمایشِ
 * سطحی روی گرافِ درزها، هر کدام با یک تبدیلِ صُلبِ بهینه (چرخش دور محور بدن +
 * جابه‌جایی) روی جای خودشان می‌نشینند.
 *
 * تبدیل بهینه از خودِ جفت‌رأس‌های درز درمی‌آید (پروکراستسِ محدود به چرخش
 * حول محور y). صُلب است، پس نه پارچه را می‌کشد نه می‌پیچاند.
 */
const seedPlacement = (patches, seams) => {
    if (patches.length < 2 || ! seams.length) {
        return 0;
    }

    const at = new Map();

    patches.forEach((entry, index) => at.set(entry.patch, index));

    /* گراف: برای هر قطعه، فهرست (همسایه، درز، آیا این قطعه سمت a است) */
    const links = patches.map(() => []);
    const load = new Float64Array(patches.length);

    for (const seam of seams) {
        if (! seam.b || seam.b === seam.a) {
            continue;
        }

        const ia = at.get(seam.a);
        const ib = at.get(seam.b);

        if (ia === undefined || ib === undefined) {
            continue;
        }

        links[ia].push({ other: ib, seam, self: 'a' });
        links[ib].push({ other: ia, seam, self: 'b' });
        load[ia] += seam.count;
        load[ib] += seam.count;
    }

    /* لنگر: قطعه‌ای که بیشترین سوزن روی آن است — تنه، نه نوار */
    let anchor = 0;

    for (let p = 1; p < patches.length; p++) {
        if (load[p] > load[anchor]) {
            anchor = p;
        }
    }

    const placed = new Uint8Array(patches.length);
    const queue = [anchor];

    placed[anchor] = 1;

    let moved = 0;

    while (queue.length) {
        const here = queue.shift();

        for (const link of links[here]) {
            if (placed[link.other]) {
                continue;
            }

            // همه‌ی درزهایی که این قطعه را به قطعه‌های چیده‌شده وصل می‌کنند
            const target = [];
            const source = [];

            for (const edge of links[link.other]) {
                if (! placed[edge.other]) {
                    continue;
                }

                const seam = edge.seam;
                const mine = edge.self === 'a' ? seam.a : seam.b;
                const theirs = edge.self === 'a' ? seam.b : seam.a;
                const mineFirst = edge.self === 'a';

                for (let i = 0; i < seam.count; i++) {
                    const own = seam.pairs[i * 2 + (mineFirst ? 0 : 1)] * 3;
                    const to = seam.pairs[i * 2 + (mineFirst ? 1 : 0)] * 3;

                    source.push(mine.positions[own], mine.positions[own + 1], mine.positions[own + 2]);
                    target.push(theirs.positions[to], theirs.positions[to + 1], theirs.positions[to + 2]);
                }
            }

            if (source.length >= 3) {
                moved += spinFit(patches[link.other].patch, source, target, patches[link.other].axis || 0);
            }

            placed[link.other] = 1;
            queue.push(link.other);
        }
    }

    return moved;
};

/*
 * چرخاندن قطعه دورِ مانکن و سُر دادنش بالا و پایین — و نه بیشتر.
 *
 * نخستین نسخه‌ی این تابع تبدیلِ صُلبِ کامل می‌داد: چرخش حولِ مرکزِ خودِ قطعه
 * به‌اضافه‌ی جابه‌جایی در سه جهت. درزها با آن تا چهار سانتی‌متر بسته شدند ولی
 * لباس از روی مانکن سُر خورد و کنارِ بدن آویزان ماند — قطعه‌ها به هم دوخته
 * بودند، ولی مجموعه دیگر روی تن نبود.
 *
 * دلیلش روشن است: هر قطعه روی استوانه‌ی بدن پیچیده شده. چرخاندنش حولِ مرکزِ
 * خودش، آن را از استوانه بیرون می‌برد. پس فقط دو درجه‌ی آزادی می‌دهیم که با
 * «روی تن ماندن» سازگارند: چرخش حولِ محورِ خودِ مانکن، و بالا/پایین. یعنی
 * قطعه دورِ بدن می‌چرخد و سُر می‌خورد تا نشانه‌هایش روبه‌روی نشانه‌های همسایه
 * بیاید — دقیقاً کاری که خیاط با قطعه روی مانکن می‌کند.
 */
const spinFit = (patch, source, target, axis = 0) => {
    const n = source.length / 3;
    let dot = 0;
    let cross = 0;
    let rise = 0;

    for (let i = 0; i < n; i++) {
        const bx = source[i * 3] - axis;
        const bz = source[i * 3 + 2];
        const ax = target[i * 3] - axis;
        const az = target[i * 3 + 2];

        dot += ax * bx + az * bz;
        cross += ax * bz - az * bx;
        rise += target[i * 3 + 1] - source[i * 3 + 1];
    }

    const theta = Math.abs(dot) + Math.abs(cross) < 1e-9 ? 0 : Math.atan2(cross, dot);
    const lift = rise / Math.max(1, n);
    const cos = Math.cos(theta);
    const sin = Math.sin(theta);
    const positions = patch.positions;

    for (let i = 0; i < positions.length; i += 3) {
        const x = positions[i] - axis;
        const z = positions[i + 2];

        positions[i] = axis + (x * cos + z * sin);
        positions[i + 1] += lift;
        positions[i + 2] = -x * sin + z * cos;
    }

    patch.remember();

    return Math.abs(theta) + Math.abs(lift);
};

/*
 * جابه‌جایی صُلبِ قطعه‌ها تا درزها روی هم بیفتند.
 *
 * هر دور، برای هر درز میانگین بردارِ اختلافِ جفت‌رأس‌ها حساب می‌شود و نیمی از آن
 * به هر سر داده می‌شود؛ بعد هر قطعه یکپارچه با میانگینِ وزنیِ خواسته‌ها جابه‌جا
 * می‌شود. چون هیچ رأسی مستقل حرکت نمی‌کند، شکلِ تخت قطعه دست‌نخورده می‌ماند.
 *
 * ساسون (دو کمان از یک قطعه) کنار گذاشته می‌شود: جابه‌جایی صُلب ساسون را نمی‌بندد
 * و فقط کل قطعه را بی‌دلیل می‌کشد.
 */
const alignPatches = (patches, seams, rounds, radial = false) => {
    if (! patches.length || ! seams.length) {
        return;
    }

    const index = new Map();

    patches.forEach((entry, at) => index.set(entry.patch, at));

    /*
     * چرخش هر قطعه دورِ محورِ خودش، نه دورِ محورِ مانکن — همان درسِ spinFit.
     * آستین روی محورِ بازو نشسته و چرخاندنش دورِ تنه آن را در عمق جابه‌جا می‌کند؛
     * چون دو آستین دو طرفِ بدن‌اند، چرخشِ قرینه‌شان عمقِ مخالف می‌سازد و لباس
     * یک‌وری می‌نشیند. اندازه: ناقرینگیِ آستین با چرخشِ دورِ تنه ۶٫۲ سانتی‌متر.
     */
    const axis = patches.map((entry) => entry.axis || 0);
    /*
     * جابه‌جاییِ پهلوییِ هر قطعه سقف دارد.
     *
     * بی سقف، قطعه از استوانهٔ خودش بیرون می‌رود و برنمی‌گردد — برخوردگر فقط
     * پارچه را از بدن بیرون می‌راند. آستین ۴ سانتی‌متر کنارِ بازو می‌رفت و بازو
     * لخت می‌ماند. با بستنِ کاملِ جابه‌جایی هم درزِ سرشانه ۱۸٫۷ سانتی‌متر باز
     * شروع می‌کرد، چون چرخش و ارتفاع تنها، سرشانه را مچ نمی‌کنند.
     *
     * پس سقفِ کوچک: به اندازهٔ مچ‌کردنِ سرشانه بس است، و کمتر از آن است که قطعه
     * از استوانه‌اش بیرون بزند.
     */
    const slid = new Float64Array(patches.length);
    const want = new Float64Array(patches.length * 3);
    const weight = new Float64Array(patches.length);
    /*
     * چرخش هم لازم است، نه فقط جابه‌جایی.
     *
     * یقه تنها قطعه‌ای است که به سه قطعه‌ی دیگر دوخته می‌شود؛ با جابه‌جایی
     * صُلبِ تنها نمی‌شود هر سه را راضی کرد و بیست سانتی‌متر فاصله می‌ماند. یک
     * چرخش کوچک دور محور بدن همان را حل می‌کند: قطعه می‌چرخد تا نشانه‌هایش
     * روبه‌روی نشانه‌های همسایه بیایند — همان کاری که خیاط پیش از سنجاق‌زدن
     * می‌کند.
     */
    const spin = new Float64Array(patches.length);
    const inertia = new Float64Array(patches.length);

    for (let round = 0; round < rounds; round++) {
        want.fill(0);
        weight.fill(0);
        spin.fill(0);
        inertia.fill(0);

        for (const seam of seams) {
            if (! seam.b || seam.b === seam.a) {
                continue;
            }

            const ia = index.get(seam.a);
            const ib = index.get(seam.b);

            if (ia === undefined || ib === undefined) {
                continue;
            }

            const pa = seam.a.positions;
            const pb = seam.b.positions;
            let dx = 0;
            let dy = 0;
            let dz = 0;

            for (let i = 0; i < seam.count; i++) {
                const at = seam.pairs[i * 2] * 3;
                const to = seam.pairs[i * 2 + 1] * 3;
                const ex = pb[to] - pa[at];
                const ey = pb[to + 1] - pa[at + 1];
                const ez = pb[to + 2] - pa[at + 2];

                dx += ex;
                dy += ey;
                dz += ez;

                // گشتاور چرخش دور محور خودِ قطعه: Δx ≈ θz و Δz ≈ −θx
                const ax = pa[at] - axis[ia];
                const bx = pb[to] - axis[ib];

                spin[ia] += pa[at + 2] * ex - ax * ez;
                inertia[ia] += ax * ax + pa[at + 2] * pa[at + 2];
                spin[ib] += bx * ez - pb[to + 2] * ex;
                inertia[ib] += bx * bx + pb[to + 2] * pb[to + 2];
            }

            const n = Math.max(1, seam.count);

            dx /= n;
            dy /= n;
            dz /= n;

            want[ia * 3] += dx * seam.count;
            want[ia * 3 + 1] += dy * seam.count;
            want[ia * 3 + 2] += dz * seam.count;
            weight[ia] += seam.count;

            want[ib * 3] -= dx * seam.count;
            want[ib * 3 + 1] -= dy * seam.count;
            want[ib * 3 + 2] -= dz * seam.count;
            weight[ib] += seam.count;
        }

        let moved = 0;

        for (let p = 0; p < patches.length; p++) {
            if (weight[p] === 0) {
                continue;
            }

            // نیمی از خواسته در هر دور: هر دو سرِ درز هم‌زمان حرکت می‌کنند
            const dx = (want[p * 3] / weight[p]) * 0.5;
            const dy = (want[p * 3 + 1] / weight[p]) * 0.5;
            const dz = (want[p * 3 + 2] / weight[p]) * 0.5;

            moved = Math.max(moved, Math.abs(dx) + Math.abs(dy) + Math.abs(dz));

            const patch = patches[p].patch;
            const positions = patch.positions;

            // چرخش کوچک و میراشده؛ بیش از ده درجه در یک دور، قطعه را پرت می‌کند
            const theta = inertia[p] > 1e-6
                ? clamp((spin[p] / inertia[p]) * 0.5, -0.17, 0.17)
                : 0;

            if (Math.abs(theta) > 1e-5) {
                const cos = Math.cos(theta);
                const sin = Math.sin(theta);

                for (let i = 0; i < positions.length; i += 3) {
                    const x = positions[i] - axis[p];
                    const z = positions[i + 2];

                    positions[i] = axis[p] + (x * cos + z * sin);
                    positions[i + 2] = -x * sin + z * cos;
                }
            }

            /*
             * جابه‌جایی فقط در ارتفاع — همان درسی که spinFit گرفته بود.
             *
             * هر قطعه روی استوانه‌ای پیچیده است (تنه دورِ بدن، آستین دورِ بازو).
             * کشیدنش به پهلو آن را از استوانه بیرون می‌برد و هیچ چیزی برنمی‌گرداند:
             * برخوردگر فقط پارچه را از بدن بیرون می‌راند، پس آستینی که کنارِ بازو
             * رفت همان‌جا می‌ماند. اندازه گرفتیم: مقطعِ آستین ۶ سانتی‌متر از محورِ
             * بازو جابه‌جا بود و تنها ۱۱۰ درجه از ۳۶۰ درجهٔ دورِ بازو را می‌پوشاند —
             * بازو لخت از آستین بیرون می‌زد و در عکس همین دیده می‌شد.
             *
             * بستنِ درز کارِ چرخش (دورِ محورِ خودِ قطعه) و ارتفاع است؛ باقی‌اش را
             * حل‌کننده می‌بندد.
             */
            const budget = Math.abs(axis[p]) > 1e-6 ? LIMB_ROOM : LATERAL_ROOM;
            const room = Math.max(0, budget - slid[p]);
            const step = Math.hypot(dx, dz);
            const slide = radial ? 1 : (step > 1e-9 ? Math.min(1, room / step) : 0);

            slid[p] += step * slide;

            for (let i = 0; i < positions.length; i += 3) {
                positions[i] += dx * slide;
                positions[i + 1] += dy;
                positions[i + 2] += dz * slide;
            }

            patch.remember();
        }

        if (moved < 0.0005) {
            break; // نشست
        }
    }
};

export const buildDrape = (payload, body, options = {}) => {
    const settings = { ...DEFAULTS, ...options };
    const law = settings.law || fabricLaw(settings.fabric || payload.fabric || {});
    const scale = payload.scale ?? 0.01;
    const budget = payload.budget || {};
    const maxVertices = settings.maxVertices ?? budget.max_vertices ?? 6000;
    const requested = settings.targetEdge ?? budget.target_edge ?? 3;
    const layers = settings.layers;

    const stats = {
        vertices: 0,
        triangles: 0,
        pieces: 0,
        seams: 0,
        darts: 0,
        stitches: 0,
        dropped: [],
        skipped: [],
        unmatched: [],
        checks: [],
        warnings: [],
        requestedEdge: requested,
        targetEdge: requested,
        maxVertices,
        retries: 0,
        repaired: 0,
        notched: 0,
        solver: null,
    };

    const wanted = (payload.pieces || []).filter((piece) => {
        const layer = piece.layer || 'outer';

        if (layers === 'all' || layers.includes(layer)) {
            return true;
        }

        stats.skipped.push({ id: piece.id, reason: `لایه‌ی «${layer}» خاموش است` });

        return false;
    });

    /*
     * بودجه‌ی رأس.
     *
     * ریزیِ مش را نمی‌شود از قبل حساب کرد؛ به شکل قطعه بستگی دارد. پس مثلث‌بندی
     * می‌کنیم، می‌شماریم و اگر جا نشد یال درشت‌تر می‌گیریم و از نو. هیچ قطعه‌ای
     * حذف نمی‌شود — کاربر باید همه‌ی لباسش را ببیند، حتی اگر مش درشت‌تر باشد.
     */
    let target = Math.max(0.4, requested);
    let flats = [];

    for (let attempt = 0; attempt < 6; attempt++) {
        // هر دور از نو شمرده می‌شود، وگرنه گزارشِ دورهای شکست‌خورده روی هم
        // می‌ماند و کاربر خطای تکراری می‌بیند
        flats = [];
        stats.dropped.length = 0;
        stats.warnings.length = 0;
        stats.repaired = 0;
        stats.notched = 0;

        let total = 0;

        for (const piece of wanted) {
            if (! Array.isArray(piece.polygon) || piece.polygon.length < 3) {
                stats.dropped.push({ id: piece.id, reason: 'چندضلعی کمتر از سه رأس دارد' });

                continue;
            }

            const notched = notchDarts(piece);

            try {
                // چندضلعی و طول یال هر دو سانتی‌متری‌اند؛ تبدیل به متر بعد از
                // مثلث‌بندی و در مرحله‌ی چیدن انجام می‌شود
                const flat = triangulate(notched.polygon, {
                    target,
                    smoothing: settings.smoothing,
                });

                stats.repaired += flat.repaired;
                stats.notched += notched.darts.filter((dart) => ! dart.cut).length;

                if (flat.unrecovered) {
                    stats.warnings.push(
                        `قطعه‌ی ${piece.id}: ${flat.unrecovered} پاره‌خط مرزی برنگشت`,
                    );
                }

                total += flat.positions.length / 2;
                flats.push({ piece, flat, notched });
            } catch (error) {
                stats.dropped.push({ id: piece.id, reason: `مثلث‌بندی نشد: ${error.message}` });
            }
        }

        stats.targetEdge = target;
        stats.vertices = total;

        if (total <= maxVertices || attempt === 5) {
            if (total > maxVertices) {
                stats.warnings.push(
                    `بودجه‌ی ${maxVertices} رأس با یال ${target.toFixed(2)} هم پر شد (${total} رأس)`,
                );
            }

            break;
        }

        // مساحت ثابت است و تعداد رأس با مربع طول یال کم می‌شود؛ ۸٪ حاشیه گرفته
        // می‌شود تا دور دوم لازم نشود
        target *= Math.max(1.06, Math.sqrt((total / maxVertices) * 1.08));
        stats.retries++;
    }

    if (stats.retries) {
        stats.warnings.push(
            `طول یال از ${requested} به ${stats.targetEdge.toFixed(2)} سانتی‌متر بزرگ شد تا در بودجه‌ی ${maxVertices} رأسی جا شود`,
        );
    }

    /* ---- ساخت تکه‌ها ---- */
    const states = new Map();
    const patches = [];
    const meshes = [];

    for (const { piece, flat, notched } of flats) {
        const placed = placePiece(piece, flat, body, { ...settings, scale });
        const count = flat.positions.length / 2;
        const patch = new TriPatch({
            positions: placed.positions,
            indices: flat.indices,
            grain: placed.grain,
            // آرایه‌ی چسبندگی خالی ساخته می‌شود ولی همه‌ی وزن‌ها صفرند، پس تا
            // وقتی کسی supportGarment را صدا نزده هیچ اثری ندارد. دلیلش این است
            // که «نگه داشتن لباس» فقط بعد از دوخته شدن معنی دارد؛ ساختِ آرایه
            // بعد از ساختِ تکه ممکن نیست.
            follow: new Float32Array(count),
            fabric: law,
        });

        patch.matrix.set(IDENTITY);

        const uv = new Float32Array(count * 2);
        const spanX = Math.max(1e-6, placed.maxX - placed.minX);
        const spanY = Math.max(1e-6, placed.maxY - placed.minY);

        for (let i = 0; i < count; i++) {
            uv[i * 2] = (flat.positions[i * 2] - placed.minX) / spanX;
            uv[i * 2 + 1] = 1 - (flat.positions[i * 2 + 1] - placed.minY) / spanY;
        }

        orient(placed.positions, flat.indices);

        const mesh = {
            id: piece.id,
            positions: placed.positions,
            indices: flat.indices,
            uv,
            grain: placed.grain,
        };

        const state = {
            id: piece.id,
            piece,
            patch,
            mesh,
            flat,
            notched,
            loop: flat.loop,
            slot: flat.slot,
            positions: flat.positions,
            collapsed: new Uint8Array(flat.loop.length),
        };

        states.set(piece.id, state);
        patches.push({ id: piece.id, piece, patch, mesh, axis: placed.axis });
        meshes.push(mesh);
        stats.triangles += flat.indices.length / 3;
    }

    stats.pieces = patches.length;
    stats.vertices = patches.reduce((sum, entry) => sum + entry.patch.count, 0);

    /* ---- ساسون‌ها را پیش از هر درزی علامت می‌زنیم ---- */
    const dartPlans = [];

    for (const state of states.values()) {
        for (const dart of state.notched.darts) {
            const found = dartApex(state, dart.start, dart.end, dart.tip);

            if (! found) {
                stats.unmatched.push({ id: state.id, reason: 'ساسون روی مرز پیدا نشد' });

                continue;
            }

            markCollapsed(state, found.arc);
            dartPlans.push({ state, dart, found });
        }
    }

    for (const seam of payload.seams || []) {
        if (seam && seam.kind === 'dart' && seam.a && seam.b && seam.a.piece === seam.b.piece) {
            const state = states.get(seam.a.piece);

            if (! state) {
                continue;
            }

            const arcA = arcOf(state, mapIndex(state, seam.a.from), mapIndex(state, seam.a.to));
            const arcB = arcOf(state, mapIndex(state, seam.b.from), mapIndex(state, seam.b.to));

            if (arcA) markCollapsed(state, arcA);
            if (arcB) markCollapsed(state, arcB);
        }
    }

    /* ---- درزها ---- */
    const seams = [];

    const sew = ({ a, b, stitch, label, kind }) => {
        if (! stitch || ! stitch.pairs.length) {
            return null;
        }

        const set = new SeamSet({
            a: a.patch,
            b: b === a ? null : b.patch,
            pairs: stitch.pairs,
            second: stitch.second,
            weight: stitch.weight,
            label,
            kind,
            duration: settings.seamDuration,
            stiffness: settings.seamStiffness,
            // ساسون لولا ندارد: دو لبه‌اش عمداً روی هم تا می‌خورند
            hinge: kind === 'dart' ? null : hingeOf(a, b, stitch.pairs),
            hingeStiffness: settings.seamHinge,
        });

        seams.push(set);
        stats.stitches += set.count;

        return set;
    };

    for (const plan of dartPlans) {
        const { state, found } = plan;
        const left = sliceArc(found.arc, 0, found.index);
        const right = sliceArc(found.arc, found.index, found.arc.slots.length - 1);

        sew({
            a: state,
            b: state,
            stitch: pairArcs(left, right, true),
            label: 'ساسون',
            kind: 'dart',
        });
        stats.darts++;
    }

    for (const seam of payload.seams || []) {
        if (! seam || ! seam.a || ! seam.b) {
            continue;
        }

        if (seam.kind === 'fold') {
            stats.checks.push({ label: seam.label || 'تای پارچه', kind: 'fold', skipped: true });

            continue;
        }

        const stateA = states.get(seam.a.piece);
        const stateB = states.get(seam.b.piece);

        if (! stateA || ! stateB) {
            stats.unmatched.push({
                label: seam.label || '',
                reason: `قطعه‌ی ${! stateA ? seam.a.piece : seam.b.piece} در این دوخت نیست`,
            });

            continue;
        }

        const arcA = arcOf(stateA, mapIndex(stateA, seam.a.from), mapIndex(stateA, seam.a.to));
        const arcB = arcOf(stateB, mapIndex(stateB, seam.b.from), mapIndex(stateB, seam.b.to));

        if (! arcA || ! arcB) {
            stats.unmatched.push({ label: seam.label || '', reason: 'کمانِ درز روی مرز پیدا نشد' });

            continue;
        }

        // بررسی سلامت: طولی که خودمان از چندضلعی گرفتیم باید به طولِ بسته نزدیک
        // باشد. اختلاف بزرگ یعنی from/to اشتباه است و درز جای دیگری دوخته می‌شود
        for (const [name, arc, side] of [
            ['a', arcA, seam.a],
            ['b', arcB, seam.b],
        ]) {
            if (typeof side.length === 'number' && side.length > 0) {
                const drift = Math.abs(arc.length - side.length) / side.length;

                if (drift > 0.15) {
                    stats.checks.push({
                        label: seam.label || '',
                        side: name,
                        expected: side.length,
                        measured: Number(arc.length.toFixed(2)),
                    });
                }
            }
        }

        /*
         * سرِ سوزن روی سمتِ ریزتر می‌نشیند و شریکش روی پاره‌خطِ سمتِ درشت‌تر.
         *
         * چون هر رأسِ سمتِ سوزن یک سوزن می‌گیرد، اگر سمتِ کم‌رأس را انتخاب کنیم
         * رأس‌های سمتِ ریز بی‌سوزن می‌مانند و پارچه از درز بیرون می‌زند. دو سرِ
         * درز در حل‌کننده قرینه‌اند، پس جابه‌جا کردنشان بی‌خطر است و «وارونه» هم
         * قرینه است: اگر A روبه‌جلو با B وارونه بخواند، B روبه‌جلو هم با A وارونه
         * می‌خواند.
         */
        const flip = arcB.vertices.length > arcA.vertices.length;
        const stitch = flip
            ? pairArcs(arcB, arcA, Boolean(seam.reverse))
            : pairArcs(arcA, arcB, Boolean(seam.reverse));
        const set = sew({
            a: flip ? stateB : stateA,
            b: flip ? stateA : stateB,
            stitch,
            label: seam.label || 'درز',
            kind: seam.kind === 'dart' ? 'dart' : 'seam',
        });

        if (set) {
            set.ease = seam.ease ?? 0;

            if (seam.kind === 'dart') {
                stats.darts++;
            } else {
                stats.seams++;
            }
        }
    }

    /*
     * پیشنهاد تنظیم حل‌کننده.
     *
     * هزینه‌ی هر گام تقریباً خطیِ تعداد قید است. تا حدود چهار هزار رأس، دو زیرگام
     * و سه تکرار روی یک دستگاه معمولی زیر ۱۶ میلی‌ثانیه می‌ماند. بالاتر از آن،
     * به‌جای اینکه صبر کنیم صفحه کند شود و ClothWorld خودش افت کند، از همین اول
     * ارزان‌تر شروع می‌کنیم. عدد نهایی را نمای سه‌بعدی روی world می‌نشاند.
     */
    /*
     * چند گامِ خشک پیش از اولین فریم لازم است؟
     *
     * دست‌کم به اندازه‌ی رمپِ دوختن، وگرنه کاربر لحظه‌ی اول قطعه‌های پخش‌شده‌ی
     * نیم‌دوخته را می‌بیند. بعد از بسته شدن درزها هم پارچه چند ده گام وقت
     * می‌خواهد تا زیر وزن خودش بنشیند. مقدار پیش‌فرضِ presettle برای پوسته‌ی
     * پارامتری بود، نه برای لباسی که باید دوخته شود.
     */
    /*
     * قطعه‌ای که هیچ سوزنی نخورده، لباس نیست.
     *
     * جیب، مغزی و نوارهای ریز رابطه‌ی دوختی ندارند؛ اگر همان‌طور رها شوند زیر
     * وزن خودشان از لباس جدا می‌افتند و کاربر یک تکه‌ی سرگردان می‌بیند. تنه و
     * دامن و آستین حتی اگر بی‌درز بمانند سر جایشان می‌مانند (ساروَنگ و تور
     * همین‌اند)، پس فقط «جزئیات» کنار گذاشته می‌شوند.
     */
    if (seams.length) {
        const stitched = new Set();

        for (const seam of seams) {
            stitched.add(seam.a);

            if (seam.b) {
                stitched.add(seam.b);
            }
        }

        for (let i = patches.length - 1; i >= 0; i--) {
            const entry = patches[i];

            if (entry.piece.role !== 'detail' || stitched.has(entry.patch)) {
                continue;
            }

            /*
             * جیب در درز دوخته نمی‌شود، روی سطح می‌نشیند.
             *
             * جیب رودوزی، پاتلت و نوارِ رویی لبه‌شان به لبه‌ی چیزی نمی‌رسد؛ روی
             * تنه کوک می‌خورند. جفت‌کردن کمان برایشان جواب نمی‌دهد و تا امروز
             * کنار گذاشته می‌شدند. حالا هر رأسِ مرزیِ قطعه به نزدیک‌ترین رأسِ
             * قطعه‌ی زیرش دوخته می‌شود — همان کوکِ دور جیب.
             */
            const host = nearestHost(entry, patches, stitched);
            const pairs = host ? surfaceStitches(states.get(entry.id), host, settings.gap * 2.5) : [];

            if (pairs.length >= 8) {
                const seam = new SeamSet({
                    a: entry.patch,
                    b: host.patch,
                    pairs: Uint32Array.from(pairs),
                    label: 'دوخت روی سطح',
                    kind: 'seam',
                    duration: settings.seamDuration,
                    stiffness: settings.seamStiffness,
                });

                seams.push(seam);
                stitched.add(entry.patch);
                stats.seams++;
                stats.stitches += seam.count;

                continue;
            }

            patches.splice(i, 1);
            meshes.splice(meshes.indexOf(entry.mesh), 1);
            stats.skipped.push({ id: entry.id, reason: 'هیچ درزی به آن نمی‌رسد؛ رها می‌شد و می‌افتاد' });
        }

        stats.pieces = patches.length;
        stats.vertices = patches.reduce((sum, entry) => sum + entry.patch.count, 0);
    }

    /*
     * پارک کردنِ قطعه‌ها کنار هم، پیش از هر شبیه‌سازی.
     *
     * این جواب همان پرسش است: الگو درست است، برش درست است، جفتِ درزها هم درست
     * است — پس چرا لباس روی مانکن به‌هم می‌ریزد؟ چون «جای شروع» هر قطعه حدسی
     * است. اندازه گرفتم: روی پیراهن کلاسیک، دو سرِ یک درز تا ۳۸ سانتی‌متر از
     * هم دور شروع می‌کردند. قید درز در خط راست می‌کشد؛ با چنین فاصله‌ای، پارچه
     * از روی بدن کشیده می‌شود و گره می‌خورد — نه به‌خاطر غلط بودن دوخت، به‌خاطر
     * غلط بودن نقطه‌ی شروع.
     *
     * درمانش جابه‌جایی صُلبِ کل قطعه است: هر قطعه یکپارچه آن‌قدر جابه‌جا می‌شود
     * که دو سرِ درزهایش روی هم بیفتند. جابه‌جایی صُلب نه پارچه را می‌کشد نه
     * می‌پیچاند، فقط قطعه را می‌برد کنار همسایه‌اش. بعد از این، حل‌کننده فقط
     * باید پارچه را بنشاند، نه اینکه قطعه‌ها را از این سر بدن به آن سر بکشد.
     */
    stats.seeded = seedPlacement(patches, seams);
    stats.bent = settings.warp === false ? 0 : warpToSeams(patches, seams);
    alignPatches(patches, seams, settings.alignRounds ?? 60, settings.alignSlide ?? false);

    stats.presettle = Math.ceil(settings.seamDuration * 60) + 140;

    /*
     * زیرگام کفِ ۲ دارد؛ صرفه‌جویی از تکرار گرفته می‌شود، نه از زیرگام.
     *
     * پیش‌تر مشِ سنگین زیرگام را به ۱ می‌رساند و همان یک زیرگام لولهٔ آستین را
     * لِه می‌کرد: با گامِ بزرگ، برخوردِ بازو و قیدهای کشش در یک گام جبران نمی‌شوند
     * و مقطعِ آستین روی بازو می‌خوابد. اندازه گرفتیم — پوششِ آستین دورِ بازو:
     *
     *   زیرگام ۱، تکرار ۳ → ۲۱۰°–۲۵۰° از ۳۶۰  (بازو لخت بیرون می‌ماند)
     *   زیرگام ۲، تکرار ۲ → ۳۴۰°
     *   زیرگام ۲، تکرار ۳ → ۳۴۰°–۳۶۰°
     *   زیرگام ۳، تکرار ۲ → ۳۴۰°–۳۶۰°
     *
     * یعنی تکرار در این ماجرا نقشی ندارد و زیرگام همه‌کاره است. پیراهنِ سنجه با
     * ۴۵۳۳ رأس فقط ۸٪ از حدِ راحت رد می‌شد و همین یک پله، آستین را از روی بازو
     * برمی‌داشت. بهایش: ۲×۲ پرتاب در هر فریم به‌جای ۱×۳.
     */
    stats.solver = stats.vertices > settings.comfortableVertices
        ? { substeps: 2, iterations: 2, reason: 'مش سنگین: تکرار کم شد، زیرگام نه' }
        : { substeps: 2, iterations: 3, reason: 'مش سبک' };

    if (stats.vertices > settings.comfortableVertices) {
        stats.warnings.push(
            `${stats.vertices} رأس بیشتر از حدِ راحتِ ${settings.comfortableVertices} است؛ زیرگام و تکرار کم شد`,
        );
    }

    return { patches, seams, meshes, stats, body };
};

/*
 * رو کردنِ مش به بیرون.
 *
 * جهت پیمایش مثلث‌ها روی الگو به جهت نوشتن چندضلعی در بسته بستگی دارد و y الگو
 * هم رو به پایین است؛ ترکیب این دو یعنی نمی‌شود از پیش گفت روی لباس، مثلث‌ها به
 * کدام سو نگاه می‌کنند. اگر رو به داخل باشند، رندرِ یک‌رویه لباس را نامرئی نشان
 * می‌دهد. پس به‌جای حدس زدن، اندازه می‌گیریم: نرمالِ مثلث‌ها با راستای شعاعیِ
 * بیرون مقایسه می‌شود و اگر مخالف بود، پیمایش برمی‌گردد.
 */
const orient = (positions, indices) => {
    let cx = 0;
    let cz = 0;
    const count = positions.length / 3;

    for (let i = 0; i < count; i++) {
        cx += positions[i * 3];
        cz += positions[i * 3 + 2];
    }

    cx /= count;
    cz /= count;

    let vote = 0;

    for (let t = 0; t < indices.length; t += 3) {
        const a = indices[t] * 3;
        const b = indices[t + 1] * 3;
        const c = indices[t + 2] * 3;
        const ux = positions[b] - positions[a];
        const uy = positions[b + 1] - positions[a + 1];
        const uz = positions[b + 2] - positions[a + 2];
        const vx = positions[c] - positions[a];
        const vy = positions[c + 1] - positions[a + 1];
        const vz = positions[c + 2] - positions[a + 2];
        const nx = uy * vz - uz * vy;
        const nz = ux * vy - uy * vx;

        vote += nx * (positions[a] - cx) + nz * (positions[a + 2] - cz);
    }

    if (vote >= 0) {
        return;
    }

    for (let t = 0; t < indices.length; t += 3) {
        const swap = indices[t + 1];

        indices[t + 1] = indices[t + 2];
        indices[t + 2] = swap;
    }
};

/* بریدن یک بازه از کمان و نرمال کردن دوباره‌ی کسرها */
const sliceArc = (arc, from, to) => {
    const slots = arc.slots.slice(from, to + 1);
    const vertices = arc.vertices.slice(from, to + 1);
    const base = arc.t[from];
    const span = arc.t[to] - base || 1;
    const t = new Float64Array(slots.length);

    for (let i = 0; i < slots.length; i++) {
        t[i] = (arc.t[from + i] - base) / span;
    }

    return { slots, vertices, t, length: (arc.t[to] - base) * arc.length };
};

/* پاره‌خط‌های داخل یک ساسون در شمارش طول کمان به حساب نمی‌آیند */
const markCollapsed = (state, arc) => {
    for (let i = 0; i + 1 < arc.slots.length; i++) {
        state.collapsed[arc.slots[i]] = 1;
    }
};

/* نزدیک‌ترین قطعه‌ی دوخته‌شده به یک قطعه‌ی جامانده، بر پایه‌ی مرکز ثقلشان */
const nearestHost = (entry, patches, stitched) => {
    const centre = (patch) => {
        let x = 0;
        let y = 0;
        let z = 0;

        for (let i = 0; i < patch.count; i++) {
            x += patch.positions[i * 3];
            y += patch.positions[i * 3 + 1];
            z += patch.positions[i * 3 + 2];
        }

        return [x / patch.count, y / patch.count, z / patch.count];
    };

    const [ax, ay, az] = centre(entry.patch);
    let best = null;
    let bestGap = Infinity;

    for (const other of patches) {
        if (other === entry || ! stitched.has(other.patch)) {
            continue;
        }

        const [bx, by, bz] = centre(other.patch);
        const gap = Math.hypot(ax - bx, ay - by, az - bz);

        if (gap < bestGap) {
            bestGap = gap;
            best = other;
        }
    }

    return best;
};

/*
 * کوکِ دور یک قطعه‌ی رویی: هر رأس مرزی به نزدیک‌ترین رأس قطعه‌ی زیرش.
 *
 * فقط رأس‌هایی که واقعاً روی قطعه‌ی زیر می‌افتند کوک می‌خورند؛ اگر جیب نصفه
 * بیرون از تنه باشد، همان نصفه‌اش دوخته می‌شود و بقیه آزاد می‌ماند.
 */
const surfaceStitches = (state, host, reach) => {
    if (! state) {
        return [];
    }

    const pairs = [];
    const from = state.patch.positions;
    const to = host.patch.positions;

    for (const vertex of state.loop) {
        const ax = from[vertex * 3];
        const ay = from[vertex * 3 + 1];
        const az = from[vertex * 3 + 2];
        let best = -1;
        let bestGap = reach;

        for (let j = 0; j < host.patch.count; j++) {
            const gap = Math.hypot(ax - to[j * 3], ay - to[j * 3 + 1], az - to[j * 3 + 2]);

            if (gap < bestGap) {
                bestGap = gap;
                best = j;
            }
        }

        if (best >= 0) {
            pairs.push(vertex, best);
        }
    }

    return pairs;
};

/*
 * بستنِ کاملِ درز پیش از نمایش.
 *
 * حل‌کننده درز را «تا حد ممکن» می‌بندد، نه کامل: بیشترین بازشدگی روی پیراهن
 * ۱٫۷ سانتی‌متر می‌ماند. برای فیزیک اهمیتی ندارد، ولی روی مانکن همان یک‌ونیم
 * سانتی‌متر یعنی نواری از پوستِ بدن که از لای درز دیده می‌شود — و چشم آن را
 * «پارچهٔ پاره» می‌خواند، نه «درزِ کمی باز». کاربر هم دقیقاً همین را دید.
 *
 * درزِ دوخته‌شده در واقعیت صفر فاصله دارد: دو لبه روی هم می‌افتند. پس در پایانِ
 * نشستن، هر جفت رأس را به میانهٔ خودشان می‌بریم. این کار فرمِ لباس را عوض
 * نمی‌کند (جابه‌جایی در حد میلی‌متر است) و فقط شکافِ دیدنی را می‌بندد.
 *
 * چند تکرار لازم است چون رأسی که روی دو درز است، هر بار میانهٔ یکی را می‌گیرد.
 */
export const weldSeams = (drape, rounds = 8) => {
    /*
     * جابه‌جاییِ لبه باید به درونِ قطعه پخش شود.
     *
     * نسخهٔ اول این تابع فقط رأس‌های خودِ درز را به میانه می‌بُرد و همسایه‌شان
     * را سر جایش می‌گذاشت؛ مثلثِ میان این دو تیغه می‌شد. با سنجهٔ چهارمرحله‌ای
     * دیده شد: پس از چیدن و دوختن و وزن، صفر مثلث تیغه‌ای — و بعد از همین
     * تابع، ۴۵ تا. شکافِ پوست بسته می‌شد و به‌جایش زبانه ساخته می‌شد.
     *
     * حالا هر بار که لبه تکان می‌خورد، همان جابه‌جایی با یک پخشِ کوتاه به
     * همسایه‌های درونی هم می‌رسد — همان کاری که برای خم‌کردنِ قطعه کردیم و
     * جواب داد. پارچه نزدیک درز به‌جای تیغه شدن، نرم می‌آید.
     *
     * و یک قانون که جایش خالی بود: پارچه لِه نمی‌شود. جفت‌کردنِ رأس‌های درز
     * چند-به-یک است — دو رأسِ کنارِ هم از یک لبه می‌توانند شریکِ مشترک داشته
     * باشند — و بستنِ کاملِ هر دو، هر دو را روی همان یک نقطه می‌نشاند. اندازه
     * گرفتیم: در آن ۴۵ مثلث، کوتاه‌ترین ضلع صفرِ صفر بود، نه کشیده. پس کشش
     * مسئله نبود؛ فروریختن بود. بعد از هر دور، هر ضلعی که از ۴۰٪ اندازهٔ خودش
     * روی الگوی تخت کوتاه‌تر شده باشد پس زده می‌شود. درز کمی باز می‌ماند —
     * همان‌قدر که ضخامتِ پارچه اجازه می‌دهد — و این درست است.
     */
    const spread = drape.patches.map((entry) => neighboursOf(entry));
    const index = new Map();

    drape.patches.forEach((entry, at) => index.set(entry.patch, at));

    let worst = 0;

    for (let round = 0; round < rounds; round++) {
        worst = 0;

        const before = drape.patches.map((entry) => Float64Array.from(entry.patch.positions));

        for (const seam of drape.seams) {
            const pa = seam.a.positions;
            const pb = (seam.b || seam.a).positions;
            const wa = seam.a.invMass;
            const wb = (seam.b || seam.a).invMass;
            const point = [0, 0, 0];

            for (let i = 0; i < seam.count; i++) {
                const na = seam.pairs[i * 2];
                const nb = seam.pairs[i * 2 + 1];
                const w = seam.second && seam.weight ? seam.weight[i] : 0;
                const nc = w > 0 ? seam.second[i] : nb;
                const at = na * 3;
                const ma = wa[na];
                // جرمِ مؤثرِ نقطهٔ کسری، همان‌که قیدِ درز به کار می‌برد
                const mb = w > 0
                    ? ((1 - w) * (1 - w) * wb[nb]) + (w * w * wb[nc])
                    : wb[nb];
                const sum = ma + mb;

                if (sum <= 0) {
                    continue; // هر دو سر میخکوب‌اند؛ دست‌نزدنی
                }

                seam.target(i, point);

                const dx = point[0] - pa[at];
                const dy = point[1] - pa[at + 1];
                const dz = point[2] - pa[at + 2];

                worst = Math.max(worst, Math.hypot(dx, dy, dz));

                // سهم هر سر به نسبت آزادیِ خودش؛ رأس میخکوب تکان نمی‌خورد
                const ka = ma / sum;
                const kb = 1 / sum;

                pa[at] += dx * ka;
                pa[at + 1] += dy * ka;
                pa[at + 2] += dz * ka;

                const sideA = index.get(seam.a);
                const sideB = index.get(seam.b || seam.a);

                if (sideA !== undefined) {
                    spread[sideA].moved[na] = 1;
                }

                for (const [vertex, share] of w > 0
                    ? [[nb, (1 - w) * wb[nb] * kb], [nc, w * wb[nc] * kb]]
                    : [[nb, wb[nb] * kb]]) {
                    const to = vertex * 3;

                    pb[to] -= dx * share;
                    pb[to + 1] -= dy * share;
                    pb[to + 2] -= dz * share;

                    if (sideB !== undefined) {
                        spread[sideB].moved[vertex] = 1;
                    }
                }
            }
        }

        /*
         * پخشِ همان جابه‌جایی به درون: هر رأسِ آزادِ غیرِ درزی، نیمی از میانگینِ
         * جابه‌جاییِ همسایه‌هایش را می‌گیرد. دو پاس کافی است — بیشتر از آن،
         * فرمِ لباس را هم جابه‌جا می‌کند.
         */
        for (let p = 0; p < drape.patches.length; p++) {
            const patch = drape.patches[p].patch;
            const positions = patch.positions;
            const was = before[p];
            const { heads, near, moved } = spread[p];

            for (let pass = 0; pass < 2; pass++) {
                for (let v = 0; v < patch.count; v++) {
                    if (moved[v] || patch.invMass[v] === 0) {
                        continue;
                    }

                    const from = heads[v];
                    const to = heads[v + 1];

                    if (to === from) {
                        continue;
                    }

                    let dx = 0;
                    let dy = 0;
                    let dz = 0;

                    for (let k = from; k < to; k++) {
                        const n = near[k] * 3;

                        dx += positions[n] - was[n];
                        dy += positions[n + 1] - was[n + 1];
                        dz += positions[n + 2] - was[n + 2];
                    }

                    const n = to - from;

                    positions[v * 3] += (dx / n) * 0.5;
                    positions[v * 3 + 1] += (dy / n) * 0.5;
                    positions[v * 3 + 2] += (dz / n) * 0.5;
                }
            }

            unsquash(patch, spread[p]);
        }
    }

    for (const { patch } of drape.patches) {
        patch.remember();
    }

    return worst;
};

/*
 * پس زدنِ ضلع‌های لِه‌شده.
 *
 * تنها کارِ این تابع نگه داشتنِ ضخامت است: ضلعی که روی الگوی تخت ۸ میلی‌متر
 * بوده نباید روی مانکن ۰ شود. زیرِ ۷۰٪ را به همان ۷۰٪ برمی‌گردانیم، دو پاس،
 * و رأسِ میخکوب دست‌نخورده می‌ماند.
 *
 * ۷۰٪ از اندازه‌گیری آمد، نه از حدس: با ۴۰٪ روی هشت لباسِ سنجه ۳۱ مثلث تیغه‌ای
 * می‌ماند، با ۵۵٪ سیزده، و با ۷۰٪ صفر — به‌جز دامنِ کلوش که پیش از جوش هم
 * تیغه دارد و مشکلش جای دیگری است. بهایش چند دهم میلی‌متر بازتر ماندنِ درز
 * است. دو پاس هم از چهار پاس تفاوتی نداشت.
 */
const unsquash = (patch, { heads, near, rest }, floor = 0.7, passes = 2) => {
    const { positions, invMass, count } = patch;

    for (let pass = 0; pass < passes; pass++) {
        for (let v = 0; v < count; v++) {
            for (let k = heads[v]; k < heads[v + 1]; k++) {
                const n = near[k];

                if (n < v) {
                    continue; // هر ضلع یک بار
                }

                const goal = rest[k] * floor;

                if (goal <= 0) {
                    continue;
                }

                const ia = v * 3;
                const ib = n * 3;
                let dx = positions[ia] - positions[ib];
                let dy = positions[ia + 1] - positions[ib + 1];
                let dz = positions[ia + 2] - positions[ib + 2];
                let length = Math.hypot(dx, dy, dz);
                const wa = invMass[v];
                const wb = invMass[n];
                const sum = wa + wb;

                if (length >= goal || sum <= 0) {
                    continue;
                }

                if (length < 1e-6) {
                    // کاملاً روی هم؛ جهتِ باز کردن دلبخواه است، فقط باید بازش کند
                    dx = 1;
                    dy = 0;
                    dz = 0;
                    length = 1;
                }

                /*
                 * جابه‌جایی روی بردارِ یکه، نه با تقسیم بر طول.
                 *
                 * ضریبِ پیشین (goal-length)/length بود؛ هرجا دو رأس تقریباً روی هم
                 * می‌آمدند — تای یقه، یا نشاندنِ نوارِ نگه‌داشته روی بدن — همان تقسیم
                 * رأس را پرت می‌کرد. اندازه گرفتیم روی یقه: دورترین شعاعش پیش از جوش
                 * ۱۰٫۴ سانتی‌متر بود و پس از جوش ۶۱٫۶. حالا بیشترین جابه‌جایی خودِ
                 * goal است و از آن بیشتر نمی‌شود.
                 */
                const move = (goal - length) / sum;
                const ux = dx / length;
                const uy = dy / length;
                const uz = dz / length;

                positions[ia] += ux * move * wa;
                positions[ia + 1] += uy * move * wa;
                positions[ia + 2] += uz * move * wa;
                positions[ib] -= ux * move * wb;
                positions[ib + 1] -= uy * move * wb;
                positions[ib + 2] -= uz * move * wb;
            }
        }
    }
};

/*
 * لولای درز: برای هر جفتِ دوخته‌شده، دو رأسِ پشتِ درز و فاصلهٔ صافشان.
 *
 * «پشتِ درز» یعنی همسایه‌ای که خودش روی مرز نیست — یک ردیف تو‌رفته. اگر رأسِ
 * لبه هیچ همسایهٔ درونی نداشته باشد (نوارِ یک‌ردیفه) از آن جفت می‌گذریم؛ لولا
 * اختیاری است و نبودش چیزی را خراب نمی‌کند.
 *
 * فاصلهٔ هدف روی الگوی تخت اندازه گرفته می‌شود: اگر دو قطعه صاف کنارِ هم باشند،
 * آن دو رأس به اندازهٔ مجموعِ فاصله‌شان از لبه از هم دورند.
 *
 * ولی هدف، *صافی* نیست. درزِ سرشانه واقعاً روی شانه گوشه می‌خورد و درز پهلو دور
 * تن می‌پیچد؛ قیدی که همه را صاف بخواهد با خودِ الگو می‌جنگد — و جنگید: درز
 * سرشانه ۵٫۸ میلی‌متر باز ماند و آزمون گرفتش. با دو بازوی برابرِ d، فاصلهٔ آن دو
 * رأس در زاویهٔ θ می‌شود 2d·sin(θ/2)؛ پس ۰٫۷۲ از حالتِ صاف تقریباً همان زاویهٔ
 * ۹۰ درجه است. هر خمی بازتر از ۹۰ درجه آزاد است و تنها تا شدنِ روی خود بسته
 * می‌شود — که همان چیزی است که «آستین وصل نیست» را می‌ساخت.
 */
const HINGE_FLOOR = 0.72;

const hingeOf = (a, b, pairs) => {
    const nearA = neighboursOf(a);
    const nearB = a === b ? nearA : neighboursOf(b);
    const edgeA = boundaryOf(a);
    const edgeB = a === b ? edgeA : boundaryOf(b);
    const out = [];

    for (let i = 0; i < pairs.length; i += 2) {
        const inA = inward(nearA, edgeA, pairs[i]);
        const inB = inward(nearB, edgeB, pairs[i + 1]);

        if (inA === null || inB === null) {
            continue;
        }

        out.push(inA.vertex, inB.vertex, (inA.rest + inB.rest) * HINGE_FLOOR);
    }

    return out.length ? out : null;
};

/* رأس‌های روی مرز: ضلعی که تنها یک مثلث دارد، مرز است */
const boundaryOf = (entry) => {
    const { indices } = entry.mesh;
    const seen = new Map();
    const edge = new Uint8Array(entry.patch.count);

    for (let t = 0; t < indices.length; t += 3) {
        for (const [x, y] of [[0, 1], [1, 2], [2, 0]]) {
            const one = indices[t + x];
            const two = indices[t + y];

            seen.set(one < two ? `${one},${two}` : `${two},${one}`, (seen.get(one < two ? `${one},${two}` : `${two},${one}`) || 0) + 1);
        }
    }

    for (const [key, count] of seen) {
        if (count === 1) {
            for (const at of key.split(',')) {
                edge[Number(at)] = 1;
            }
        }
    }

    return edge;
};

/* نزدیک‌ترین همسایهٔ غیرِ مرزیِ یک رأس، با فاصله‌اش روی الگوی تخت */
const inward = ({ heads, near, rest }, edge, vertex) => {
    let best = null;

    for (let k = heads[vertex]; k < heads[vertex + 1]; k++) {
        if (edge[near[k]] || rest[k] <= 0) {
            continue;
        }

        if (best === null || rest[k] < best.rest) {
            best = { vertex: near[k], rest: rest[k] };
        }
    }

    return best;
};

/*
 * همسایگیِ رأس‌ها از مثلث‌ها: heads/near فهرستِ فشرده، rest اندازهٔ همان ضلع
 * روی الگوی تخت (سانتی‌متر تبدیل‌شده به متر، همان یکایِ grain)، و moved نشانهٔ
 * «این رأس روی درز است».
 */
const neighboursOf = (entry) => {
    const count = entry.patch.count;
    const indices = entry.mesh.indices;
    const grain = entry.mesh.grain;
    const heads = new Int32Array(count + 1);

    for (let t = 0; t < indices.length; t += 3) {
        for (let k = 0; k < 3; k++) {
            heads[indices[t + k] + 1] += 2;
        }
    }

    for (let v = 0; v < count; v++) {
        heads[v + 1] += heads[v];
    }

    const fill = heads.slice(0, count);
    const near = new Int32Array(heads[count]);
    const rest = new Float32Array(heads[count]);

    for (let t = 0; t < indices.length; t += 3) {
        const tri = [indices[t], indices[t + 1], indices[t + 2]];

        for (let k = 0; k < 3; k++) {
            for (const other of [tri[(k + 1) % 3], tri[(k + 2) % 3]]) {
                rest[fill[tri[k]]] = Math.hypot(
                    grain[other * 2] - grain[tri[k] * 2],
                    grain[other * 2 + 1] - grain[tri[k] * 2 + 1],
                );
                near[fill[tri[k]]++] = other;
            }
        }
    }

    return { heads, near, rest, moved: new Uint8Array(count) };
};

/**
 * «نگه‌دارنده»ی لبه‌ی بالا — بعد از دوخته شدن لباس صدا زده می‌شود.
 *
 * لباس در واقعیت روی سرشانه می‌ایستد: شیبِ شانه، اصطکاک و فرمِ خودِ درز نگهش
 * می‌دارند. برخورد و اصطکاک حل‌کننده بیشترش را می‌سازد، ولی لباسِ لغزنده روی
 * مانکنِ صاف دیر یا زود پایین می‌رود و در چشم کاربر یعنی خرابی، نه فیزیک.
 *
 * چرا این کار را همان buildDrape نمی‌کند؟ چون آن لحظه قطعه‌ها هنوز دور بدن پخش
 * شده‌اند و به هم دوخته نشده‌اند. اگر همان‌جا لبه‌ی بالا را میخکوب کنیم، درز
 * سرشانه هیچ‌وقت بسته نمی‌شود — نگه‌دارنده دقیقاً همان جایی را می‌چسباند که
 * درز می‌خواهد از آنجا تکان بخورد. پس اول می‌دوزیم، بعد نگه می‌داریم.
 *
 * نوار باریک است تا چین و افتادگی از بین نرود.
 *
 * @param {{patches: object[]}} drape خروجی buildDrape پس از نشستن
 * @param {object} [options] { band: متر، strength: ۰ تا ۱، inverse: ماتریس وارونِ گروه بدن }
 */
/*
 * نشاندنِ نوارِ نگه‌داشته روی خودِ بدن، پیش از میخ زدن.
 *
 * میخ، جای امروزِ پارچه را ثبت می‌کند. اگر لباس در پایانِ دوختِ بی‌وزنی چند
 * سانتی‌متر بالای شانه مانده باشد، میخ همان بلندی را برای همیشه قفل می‌کند و وزن
 * هم پایینش نمی‌آورد. اندازه گرفتیم: در نوکِ شانه ۳۶ رأس بیش از دو سانتی‌متر از
 * بدن دور بودند و *همه‌شان* میخکوب — هیچ رأسِ آزادی آن‌جا نبود، بدترینشان ۵٫۴
 * سانتی‌متر. از لای همان بلندی، پوستِ شانه دیده می‌شد.
 *
 * لباس روی خودِ شانه می‌ایستد، نه چند سانتی‌متر بالای آن. پس پیش از میخ زدن،
 * رأسِ نوار به سطحِ بدن (به‌اضافهٔ فاصلهٔ پوست) کشیده می‌شود — و فقط به سمتِ
 * داخل: لباسِ گشاد از تن جدا می‌ماند و این کار تنگش نمی‌کند. شدتِ کشیدن همان
 * وزنِ نگه‌دارنده است، پس مرزِ نوار چینِ تیز نمی‌گیرد.
 */
const seatOnBody = (patch, body, zone) => {
    if (! body || ! patch.follow) {
        return;
    }

    const gap = DEFAULTS.gap;
    const arm = zone === 'sleeve' || zone.startsWith('sleeve_');

    for (let i = 0; i < patch.count; i++) {
        const hold = patch.follow[i];

        if (hold <= 0) {
            continue;
        }

        const at = i * 3;
        const x = patch.positions[at];
        const y = patch.positions[at + 1];
        const z = patch.positions[at + 2];

        let cx = 0;
        let rx;
        let rz;

        if (arm && body.armTable) {
            cx = x < 0 ? -(body.armOffset ?? 0) : (body.armOffset ?? 0);
            rx = sampleTable(body.armTable, y - (body.level.shoulder - 0.035))[0] + gap;
            rz = rx;
        } else {
            const row = sampleTable(body.profile, y);

            rx = row[0] + gap;
            rz = row[1] + gap;
        }

        const dx = x - cx;
        const reach = Math.hypot(dx / Math.max(1e-6, rx), z / Math.max(1e-6, rz));

        if (reach <= 1) {
            continue; // همین حالا روی بدن یا داخلش است؛ کاری نداریم
        }

        const pull = hold * (1 - 1 / reach);

        patch.positions[at] = x - dx * pull;
        patch.positions[at + 2] = z - z * pull;
    }

    patch.remember();
};

export const supportGarment = (drape, options = {}) => {
    const band = options.band ?? DEFAULTS.support.band;
    const strength = options.strength ?? DEFAULTS.support.strength;
    const inverse = options.inverse || IDENTITY;

    /*
     * دامن هم باید نگه داشته شود، نه فقط تنه.
     *
     * دامن تنها از خط کمر به بالاتنه دوخته است و وزن یک دامن بلند روی همان یک
     * درز می‌افتد؛ قید درز نرم است و زیر بار می‌کشد. اندازه‌گیری روی لباس
     * غلافی: لبه‌ی بالای دامن پانزده سانتی‌متر پایین می‌رفت و بالاتنه بالای آن
     * بی‌جا می‌ماند — همان نوار مچاله‌ای که کاربر دور کمر می‌دید. روی تن هم
     * همین است: کمرِ دامن روی خودِ کمر می‌ایستد، نه روی درز.
     */
    const zones = options.zones || ['torso', 'collar', 'skirt', 'sleeve'];

    for (const { piece, patch } of drape.patches) {
        const zone = piece.placement?.zone || '';
        const held = zones.some((name) => zone === name || zone.startsWith(`${name}_`));

        if (! held || ! patch.follow) {
            continue;
        }

        let top = -Infinity;

        for (let i = 0; i < patch.count; i++) {
            top = Math.max(top, patch.positions[i * 3 + 1]);
        }

        for (let i = 0; i < patch.count; i++) {
            const depth = top - patch.positions[i * 3 + 1];

            patch.follow[i] = depth > band ? 0 : strength * (1 - depth / band);
        }

        seatOnBody(patch, drape.body, zone);
        patch.capturePins(inverse);
    }
};
