/*
 * حل‌کننده‌ی پارچه — دینامیک مبتنی بر موقعیت (Position Based Dynamics)
 *
 * این ماژول هیچ وابستگی بیرونی ندارد؛ نه three.js و نه هیچ بسته‌ی npm دیگری.
 * فقط با آرایه‌های تخت (Float32Array) کار می‌کند تا بشود همان بافرِ هندسه‌ی
 * three را مستقیم به آن داد و بدون کپی، هر فریم جای رأس‌ها را به‌روز کرد.
 *
 * ایده‌ی کلی PBD ساده است:
 *   ۱) پیش‌بینی: هر ذره با سرعت و شتاب جاذبه کمی جابه‌جا می‌شود.
 *   ۲) اصلاح:   قیدها (فاصله‌ی درزها، برش، خمش) چند بار پشت سر هم روی همان
 *              موقعیت‌ها اعمال می‌شوند تا پارچه پاره یا کِش نیاید.
 *   ۳) برخورد:  هر ذره‌ای که داخل بدن رفته، روی پوسته‌ی بدن پس زده می‌شود.
 *   ۴) سرعت:    سرعت از اختلاف موقعیت قبل و بعد دوباره ساخته می‌شود.
 *
 * چرا همین کافی است؟ چون طول استراحتِ قیدها از همان هندسه‌ی دوخته‌شده گرفته
 * می‌شود؛ یعنی نقطه‌ی تعادل حل‌کننده دقیقاً همان فرم الگوست. حل‌کننده فقط
 * افتادگی، چین و برخورد را به آن اضافه می‌کند و هیچ‌وقت لباس را از ریخت
 * نمی‌اندازد.
 *
 * سه ویژگی که به‌عمد رعایت شده:
 *   • قطعی بودن: هیچ Math.random در کار نیست؛ برای شکستن تقارن از hash استفاده
 *     می‌شود، پس هر بار باز کردن صفحه دقیقاً همان چین‌ها را می‌سازد.
 *   • ارزانی: وقتی انرژی جنبشی پارچه از یک آستانه پایین‌تر برود، حل‌کننده
 *     می‌خوابد و صفحه‌ی بی‌کار هیچ هزینه‌ای ندارد.
 *   • افت‌پذیری: اگر دستگاه کند باشد، اول تعداد تکرارها و بعد زیرگام‌ها کم
 *     می‌شود و در نهایت پارچه زودتر «می‌نشیند» و ثابت می‌ماند.
 */

/* ---------------------------------------------------------------------------
 * ابزارهای کوچک
 * ------------------------------------------------------------------------- */

const clamp = (value, min, max) => (value < min ? min : value > max ? max : value);

/*
 * همان عدد شبه‌تصادفیِ قطعیِ نمای سه‌بعدی. اینجا فقط برای شکستن تقارن به کار
 * می‌رود: استوانه‌ی کاملاً متقارنِ دامن بدون یک تلنگر ریز هیچ‌وقت چین نمی‌خورد.
 */
export const hash = (i) => {
    const x = Math.sin(i * 12.9898 + 4.1414) * 43758.5453;

    return x - Math.floor(x);
};

const now = () =>
    typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();

/* ضرب نقطه در ماتریس ۴×۴ ستون‌محور (همان چیدمانی که three.js دارد) */
const applyMatrix = (m, x, y, z, out) => {
    out[0] = m[0] * x + m[4] * y + m[8] * z + m[12];
    out[1] = m[1] * x + m[5] * y + m[9] * z + m[13];
    out[2] = m[2] * x + m[6] * y + m[10] * z + m[14];

    return out;
};

/* ---------------------------------------------------------------------------
 * برخوردگر: «استوانه‌ی بیضوی کشیده» روی محور Y محلی
 * ---------------------------------------------------------------------------
 * بدنِ مانکن از جدول مقطع ساخته می‌شود: در هر ارتفاع یک بیضی با نیم‌پهنا و
 * نیم‌عمق. همین ساختار عیناً به برخوردگر داده می‌شود، پس شکل برخورد دقیقاً
 * همان شکلی است که کاربر می‌بیند و پارچه نه داخل بدن می‌رود نه بی‌دلیل از آن
 * فاصله می‌گیرد.
 *
 * بازوها و پاها هم همین‌اند، فقط نیم‌پهنا و نیم‌عمقشان برابر است (مقطع دایره).
 * هر برخوردگر به یک گروه three وصل است و ماتریس آن گروه هر فریم به اینجا داده
 * می‌شود؛ پس با هر حالت بدن، ران و ساق و بازو خودشان جابه‌جا می‌شوند.
 */
export class Collider {
    /**
     * @param {object} options
     * @param {number[][]} options.sections سطرهای [y, rx, rz] به ترتیب صعودی
     * @param {boolean} [options.capMin] سرِ پایین گرد باشد
     * @param {boolean} [options.capMax] سرِ بالا گرد باشد
     * @param {string} [options.name] فقط برای اشکال‌زدایی
     */
    constructor({ sections, capMin = true, capMax = true, name = '' }) {
        const rows = sections.length;

        this.name = name;
        this.ys = new Float32Array(rows);
        this.rxs = new Float32Array(rows);
        this.rzs = new Float32Array(rows);
        this.capMin = capMin;
        this.capMax = capMax;

        let maxRx = 0;
        let maxRz = 0;

        for (let i = 0; i < rows; i++) {
            this.ys[i] = sections[i][0];
            this.rxs[i] = Math.max(1e-4, sections[i][1]);
            this.rzs[i] = Math.max(1e-4, sections[i][2]);
            maxRx = Math.max(maxRx, this.rxs[i]);
            maxRz = Math.max(maxRz, this.rzs[i]);
        }

        this.capLow = capMin ? Math.min(this.rxs[0], this.rzs[0]) : 0;
        this.capHigh = capMax ? Math.min(this.rxs[rows - 1], this.rzs[rows - 1]) : 0;

        // جعبه‌ی مرزی محلی؛ هر فریم به فضای جهانی برده می‌شود تا رد کردن ذره‌های
        // دور از بدن با سه مقایسه تمام شود و اصلاً نیازی به ماتریس نباشد.
        this.localBox = [
            -maxRx,
            this.ys[0] - this.capLow,
            -maxRz,
            maxRx,
            this.ys[rows - 1] + this.capHigh,
            maxRz,
        ];

        this.matrix = new Float32Array(16);
        this.inverse = new Float32Array(16);
        this.box = new Float32Array(6);
        this.active = false;
    }

    /**
     * ماتریس جهانیِ گروه و وارونش را می‌گیرد (هر دو ستون‌محور، مثل three.js).
     * جعبه‌ی مرزی جهانی هم همین‌جا یک بار در فریم حساب می‌شود.
     */
    setTransform(matrix, inverse, margin = 0) {
        this.matrix.set(matrix);
        this.inverse.set(inverse);

        const [x0, y0, z0, x1, y1, z1] = this.localBox;
        const point = [0, 0, 0];
        let minX = Infinity;
        let minY = Infinity;
        let minZ = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;
        let maxZ = -Infinity;

        for (let c = 0; c < 8; c++) {
            applyMatrix(
                matrix,
                c & 1 ? x1 : x0,
                c & 2 ? y1 : y0,
                c & 4 ? z1 : z0,
                point,
            );

            if (point[0] < minX) minX = point[0];
            if (point[1] < minY) minY = point[1];
            if (point[2] < minZ) minZ = point[2];
            if (point[0] > maxX) maxX = point[0];
            if (point[1] > maxY) maxY = point[1];
            if (point[2] > maxZ) maxZ = point[2];
        }

        this.box[0] = minX - margin;
        this.box[1] = minY - margin;
        this.box[2] = minZ - margin;
        this.box[3] = maxX + margin;
        this.box[4] = maxY + margin;
        this.box[5] = maxZ + margin;
        this.active = true;
    }

    /* نیم‌پهنا و نیم‌عمق در ارتفاع محلی y (درون‌یابی خطی روی جدول مقطع) */
    sectionAt(y, out) {
        const ys = this.ys;
        const last = ys.length - 1;

        if (y <= ys[0]) {
            out[0] = this.rxs[0];
            out[1] = this.rzs[0];

            return -1;
        }

        if (y >= ys[last]) {
            out[0] = this.rxs[last];
            out[1] = this.rzs[last];

            return 1;
        }

        let i = 0;

        while (i < last && ys[i + 1] < y) {
            i++;
        }

        const t = (y - ys[i]) / Math.max(1e-6, ys[i + 1] - ys[i]);

        out[0] = this.rxs[i] + (this.rxs[i + 1] - this.rxs[i]) * t;
        out[1] = this.rzs[i] + (this.rzs[i + 1] - this.rzs[i]) * t;

        return 0;
    }
}

/* بافرهای موقتِ سطح ماژول؛ داخل حلقه‌ی داغ هیچ شیئی ساخته نمی‌شود */
const local = [0, 0, 0];
const world = [0, 0, 0];
const section = [0, 0];

/* ---------------------------------------------------------------------------
 * یک تکه پارچه
 * ---------------------------------------------------------------------------
 * هر قطعه‌ی لباس (بالاتنه، دامن، هر آستین) یک ClothPatch است. موقعیت‌ها همان
 * آرایه‌ی position هندسه‌ی three هستند؛ پس نوشتن نتیجه هزینه‌ی کپی ندارد.
 */
export class ClothPatch {
    /**
     * @param {object} options
     * @param {Float32Array} options.positions آرایه‌ی ۳n مختصات جهانی
     * @param {number} options.rows تعداد حلقه‌ها (از پایین به بالا)
     * @param {number} options.segments تعداد نقطه در هر حلقه (حلقه بسته است)
     * @param {Uint8Array} options.pinned ۱ یعنی این رأس به بدن دوخته است
     * @param {object} options.fabric خروجی fabricLaw()
     */
    constructor({ positions, rows, segments, pinned, fabric }) {
        const count = rows * segments;

        this.positions = positions;
        this.rows = rows;
        this.segments = segments;
        this.count = count;
        this.previous = new Float32Array(positions);
        this.velocity = new Float32Array(count * 3);
        this.invMass = new Float32Array(count);
        this.fabric = fabric;

        const pins = [];

        for (let i = 0; i < count; i++) {
            if (pinned && pinned[i]) {
                pins.push(i);
            } else {
                this.invMass[i] = 1;
            }
        }

        this.pins = new Uint32Array(pins);
        this.pinRest = new Float32Array(pins.length * 3);

        this.groups = this.buildConstraints();
        this.constraintCount = this.groups.reduce((sum, group) => sum + group.rest.length, 0);
        this.motion = 0;
    }

    /*
     * ساخت قیدها از توپولوژی حلقه‌ای.
     *
     *   تار (warp)  : عمودی، بین دو حلقه‌ی پشت سر هم — امتداد قد لباس
     *   پود (weft)  : افقی، دور تا دور حلقه — امتداد دور لباس
     *   برش (shear) : قطرهای هر خانه — مقاومت پارچه در برابر لوزی شدن
     *   خمش (bend)  : یکی در میان، هم عمودی هم افقی — مقاومت در برابر تا خوردن
     *
     * کشسانی هر خانواده جدا از دیگری است؛ برای همین ژرسه که در پود کش می‌آید
     * با جین که در هیچ جهتی کش نمی‌آید یکسان رفتار نمی‌کند.
     */
    buildConstraints() {
        const { rows, segments, positions, fabric } = this;
        const groups = [];

        const make = (pairs, law) => {
            const size = pairs.length / 2;
            const a = new Uint32Array(size);
            const b = new Uint32Array(size);
            const rest = new Float32Array(size);

            for (let i = 0; i < size; i++) {
                const ia = pairs[i * 2];
                const ib = pairs[i * 2 + 1];

                a[i] = ia;
                b[i] = ib;

                const dx = positions[ia * 3] - positions[ib * 3];
                const dy = positions[ia * 3 + 1] - positions[ib * 3 + 1];
                const dz = positions[ia * 3 + 2] - positions[ib * 3 + 2];

                rest[i] = Math.sqrt(dx * dx + dy * dy + dz * dz) || 1e-5;
            }

            groups.push({ a, b, rest, ...law });
        };

        const at = (row, col) => row * segments + (col % segments);

        const warp = [];
        const weft = [];
        const shear = [];
        const bendWarp = [];
        const bendWeft = [];

        for (let row = 0; row < rows; row++) {
            for (let col = 0; col < segments; col++) {
                const here = at(row, col);

                weft.push(here, at(row, col + 1));

                if (segments > 4) {
                    bendWeft.push(here, at(row, col + 2));
                }

                if (row + 1 < rows) {
                    warp.push(here, at(row + 1, col));
                    shear.push(here, at(row + 1, col + 1));
                    shear.push(at(row, col + 1), at(row + 1, col));
                }

                if (row + 2 < rows) {
                    bendWarp.push(here, at(row + 2, col));
                }
            }
        }

        make(weft, fabric.weft);
        make(warp, fabric.warp);
        make(shear, fabric.shear);
        make(bendWarp, fabric.bend);
        make(bendWeft, fabric.bend);

        return groups;
    }

    /*
     * جای رأس‌های دوخته‌شده را از روی ماتریس گروهی که به آن آویزان‌اند به‌روز
     * می‌کند. سرشانه و کمربند با بدن حرکت می‌کنند و بقیه‌ی پارچه دنبالشان
     * می‌افتد؛ همین است که تغییر حالت را «آرام» نشان می‌دهد.
     */
    setPinRest(indexInPins, x, y, z) {
        const at = indexInPins * 3;

        this.pinRest[at] = x;
        this.pinRest[at + 1] = y;
        this.pinRest[at + 2] = z;
    }

    /* گرفتن موقعیت محلی رأس‌های دوخته‌شده از روی وضعیت فعلی و ماتریس وارون */
    capturePins(inverse) {
        const { pins, positions } = this;

        for (let p = 0; p < pins.length; p++) {
            const at = pins[p] * 3;

            applyMatrix(inverse, positions[at], positions[at + 1], positions[at + 2], local);
            this.setPinRest(p, local[0], local[1], local[2]);
        }
    }

    /* بردن رأس‌های دوخته‌شده به جای تازه‌شان در فضای جهانی */
    applyPins(matrix) {
        const { pins, pinRest, positions, previous } = this;

        for (let p = 0; p < pins.length; p++) {
            const at = pins[p] * 3;
            const from = p * 3;

            applyMatrix(matrix, pinRest[from], pinRest[from + 1], pinRest[from + 2], world);

            positions[at] = world[0];
            positions[at + 1] = world[1];
            positions[at + 2] = world[2];
            previous[at] = world[0];
            previous[at + 1] = world[1];
            previous[at + 2] = world[2];
        }
    }

    /* گام ۱ — پیش‌بینی موقعیت با جاذبه و میرایی */
    predict(dt, gravity, damping) {
        const { positions, previous, velocity, invMass, count } = this;
        const keep = 1 - damping;

        for (let i = 0; i < count; i++) {
            const at = i * 3;

            previous[at] = positions[at];
            previous[at + 1] = positions[at + 1];
            previous[at + 2] = positions[at + 2];

            if (invMass[i] === 0) {
                velocity[at] = 0;
                velocity[at + 1] = 0;
                velocity[at + 2] = 0;

                continue;
            }

            velocity[at] *= keep;
            velocity[at + 1] = velocity[at + 1] * keep + gravity * dt;
            velocity[at + 2] *= keep;

            positions[at] += velocity[at] * dt;
            positions[at + 1] += velocity[at + 1] * dt;
            positions[at + 2] += velocity[at + 2] * dt;
        }
    }

    /*
     * گام ۲ — اصلاح قیدها.
     *
     * هر قید یک «بازه» است، نه یک عدد ثابت:
     *   • بلندتر از حد کشسانی → با تمام قدرت جمع می‌شود (درز پاره نمی‌شود)
     *   • کوتاه‌تر از حد آزاد شدن → باز می‌شود
     *   • بین این دو → فقط یک کشش نرم به سمت طول اصلی (حافظه‌ی فرم پارچه)
     * همین «بین دو حد» است که به پارچه اجازه می‌دهد جمع شود و چین بخورد؛
     * پارچه‌ی سفت بازه‌ی باریکی دارد و صاف می‌ماند، پارچه‌ی لخت بازه‌ی گشاد
     * دارد و موج می‌خورد.
     */
    project() {
        const { positions, invMass, groups } = this;

        for (let g = 0; g < groups.length; g++) {
            const group = groups[g];
            const { a, b, rest, softness, maxScale, minScale } = group;
            const size = rest.length;

            for (let i = 0; i < size; i++) {
                const ia = a[i];
                const ib = b[i];
                const wa = invMass[ia];
                const wb = invMass[ib];
                const sum = wa + wb;

                if (sum === 0) {
                    continue;
                }

                const pa = ia * 3;
                const pb = ib * 3;
                const dx = positions[pa] - positions[pb];
                const dy = positions[pa + 1] - positions[pb + 1];
                const dz = positions[pa + 2] - positions[pb + 2];
                const length = Math.sqrt(dx * dx + dy * dy + dz * dz);

                if (length < 1e-9) {
                    continue;
                }

                const target = rest[i];
                const high = target * maxScale;
                const low = target * minScale;
                let goal;
                let stiffness;

                if (length > high) {
                    goal = high;
                    stiffness = 1;
                } else if (length < low) {
                    goal = low;
                    stiffness = 1;
                } else {
                    goal = target;
                    stiffness = softness;
                }

                const scale = ((length - goal) / length) * stiffness / sum;
                const cx = dx * scale;
                const cy = dy * scale;
                const cz = dz * scale;

                if (wa !== 0) {
                    positions[pa] -= cx * wa;
                    positions[pa + 1] -= cy * wa;
                    positions[pa + 2] -= cz * wa;
                }

                if (wb !== 0) {
                    positions[pb] += cx * wb;
                    positions[pb + 1] += cy * wb;
                    positions[pb + 2] += cz * wb;
                }
            }
        }
    }

    /*
     * گام ۳ — برخورد با بدن.
     *
     * هر ذره‌ای که داخل یکی از برخوردگرها افتاده باشد، دقیقاً روی پوسته‌ی آن
     * به‌اضافه‌ی یک فاصله‌ی پوستی گذاشته می‌شود. اصلاح «سخت» است، نه فنری؛
     * چون قرار است هیچ‌وقت ران از دامن بیرون نزند، نه اینکه کمتر بزند.
     *
     * اصطکاک: حرکت مماس بر سطحِ همان گام کم می‌شود، پس پارچه روی پوست ترمز
     * می‌گیرد و سُر نمی‌خورد. پارچه‌ی زبر بیشتر ترمز می‌گیرد.
     */
    collide(colliders, skin, friction) {
        const { positions, previous, invMass, count } = this;

        for (let i = 0; i < count; i++) {
            if (invMass[i] === 0) {
                continue;
            }

            const at = i * 3;
            const px = positions[at];
            const py = positions[at + 1];
            const pz = positions[at + 2];

            for (let c = 0; c < colliders.length; c++) {
                const collider = colliders[c];

                if (! collider.active) {
                    continue;
                }

                const box = collider.box;

                if (px < box[0] || px > box[3] || py < box[1] || py > box[4] || pz < box[2] || pz > box[5]) {
                    continue;
                }

                applyMatrix(collider.inverse, positions[at], positions[at + 1], positions[at + 2], local);

                const side = collider.sectionAt(local[1], section);
                const rx = section[0];
                const rz = section[1];

                let dy = 0;
                let ry = 0;

                if (side < 0) {
                    if (! collider.capMin) {
                        continue;
                    }

                    ry = collider.capLow;
                    dy = local[1] - collider.ys[0];
                } else if (side > 0) {
                    if (! collider.capMax) {
                        continue;
                    }

                    ry = collider.capHigh;
                    dy = local[1] - collider.ys[collider.ys.length - 1];
                }

                const ux = local[0] / rx;
                const uz = local[2] / rz;
                const uy = ry > 0 ? dy / ry : 0;
                const distance = Math.sqrt(ux * ux + uy * uy + uz * uz);

                if (distance >= 1) {
                    continue;
                }

                // مرکز مقطع؛ برای سرهای گرد، مرکزِ همان نیم‌بیضی‌گون
                const baseY = local[1] - dy;
                let sx;
                let sy;
                let sz;

                if (distance < 1e-6) {
                    // درست روی محور بدن؛ جهت پس‌زدن از hash همان رأس می‌آید تا
                    // نتیجه قطعی باشد و همه‌ی ذره‌ها هم به یک سمت هل داده نشوند
                    const angle = hash(i) * Math.PI * 2;

                    sx = rx * Math.cos(angle);
                    sy = 0;
                    sz = rz * Math.sin(angle);
                } else {
                    sx = (rx * ux) / distance;
                    sy = ry > 0 ? (ry * uy) / distance : 0;
                    sz = (rz * uz) / distance;
                }

                // نرمال بیرونیِ بیضی در نقطه‌ی سطح
                let nx = sx / (rx * rx);
                let ny = ry > 0 ? sy / (ry * ry) : 0;
                let nz = sz / (rz * rz);
                const nl = Math.sqrt(nx * nx + ny * ny + nz * nz) || 1;

                nx /= nl;
                ny /= nl;
                nz /= nl;

                local[0] = sx + nx * skin;
                local[1] = baseY + sy + ny * skin;
                local[2] = sz + nz * skin;

                applyMatrix(collider.matrix, local[0], local[1], local[2], world);

                positions[at] = world[0];
                positions[at + 1] = world[1];
                positions[at + 2] = world[2];

                // اصطکاک روی مؤلفه‌ی مماسیِ جابه‌جایی همین گام
                if (friction > 0) {
                    applyMatrix(collider.matrix, nx, ny, nz, world);

                    const wx = world[0] - collider.matrix[12];
                    const wy = world[1] - collider.matrix[13];
                    const wz = world[2] - collider.matrix[14];

                    let mx = positions[at] - previous[at];
                    let my = positions[at + 1] - previous[at + 1];
                    let mz = positions[at + 2] - previous[at + 2];
                    const along = mx * wx + my * wy + mz * wz;

                    mx -= wx * along;
                    my -= wy * along;
                    mz -= wz * along;

                    previous[at] += mx * friction;
                    previous[at + 1] += my * friction;
                    previous[at + 2] += mz * friction;
                }

                break;
            }
        }
    }

    /* گام ۴ — بازسازی سرعت و اندازه‌گیری بی‌قراری پارچه */
    finish(dt) {
        const { positions, previous, velocity, invMass, count } = this;
        const inverse = 1 / dt;
        let motion = 0;

        for (let i = 0; i < count; i++) {
            if (invMass[i] === 0) {
                continue;
            }

            const at = i * 3;
            const dx = positions[at] - previous[at];
            const dy = positions[at + 1] - previous[at + 1];
            const dz = positions[at + 2] - previous[at + 2];
            const square = dx * dx + dy * dy + dz * dz;

            if (square > motion) {
                motion = square;
            }

            velocity[at] = dx * inverse;
            velocity[at + 1] = dy * inverse;
            velocity[at + 2] = dz * inverse;
        }

        this.motion = Math.sqrt(motion);
    }

    /* خواباندن کامل: سرعت‌ها صفر می‌شوند تا پارچه دیگر نلرزد */
    rest() {
        this.velocity.fill(0);
        this.previous.set(this.positions);
        this.motion = 0;
    }
}

/* ---------------------------------------------------------------------------
 * ترجمه‌ی شناسنامه‌ی پارچه به قانون رفتاری قیدها
 * ---------------------------------------------------------------------------
 * ورودی همان payload.fabric است (physics + drape). خروجی برای هر خانواده‌ی
 * قید سه عدد می‌دهد:
 *   softness  چقدر پارچه به فرم اصلی برمی‌گردد
 *   maxScale  تا چند برابر می‌تواند کش بیاید (تار/پود/اریب جدا)
 *   minScale  تا چقدر می‌تواند جمع شود و چین بخورد
 */
export const fabricLaw = (fabric = {}) => {
    const physics = fabric.physics || {};
    const drape = clamp(fabric.drape ?? 0.5, 0, 1);
    const bending = clamp(physics.bending ?? 0.12, 0, 1);
    const shear = clamp(physics.shear ?? 0.2, 0, 1);
    const recovery = clamp(physics.recovery ?? 0.8, 0, 1);
    const warpStretch = clamp(physics.stretch_warp ?? 0, 0, 1.5);
    const weftStretch = clamp(physics.stretch_weft ?? 0, 0, 2);
    const biasStretch = clamp(physics.stretch_bias ?? 0.15, 0, 2);

    // سفتیِ خمش پارچه‌های بافته (گاباردین) نزدیک ۰.۳ و حریر نزدیک ۰.۰۳ است
    const stiff = clamp(bending * 3.2, 0.02, 0.92);
    // هرچه پارچه لخت‌تر، اجازه‌ی جمع شدن بیشتر ⇒ چین‌های ریزتر و بیشتر
    const slack = 0.02 + (1 - stiff) * 0.1 + drape * 0.03;
    const memory = 0.12 + recovery * 0.4;

    /*
     * یک خانواده‌ی قید ساختاری. کف کشسانی ۱٪ گذاشته شده تا پارچه‌ی کاملاً
     * بی‌کشش، حل‌کننده را قفل و صحنه را لرزان نکند.
     */
    const line = (stretch) => ({
        softness: clamp(memory, 0.05, 0.95),
        maxScale: 1 + clamp(stretch, 0.01, 1.2),
        minScale: 1 - clamp(slack, 0.01, 0.2),
    });

    return {
        // جاذبه‌ی مؤثر: پارچه‌ی سنگین قاطع‌تر می‌افتد و کمتر شناور می‌ماند
        gravity: -9.81 * clamp(0.7 + (physics.weight ?? 0.15) * 2.2, 0.6, 1.9),
        // میرایی از damping می‌آید که خودش وارونِ لختی است
        damping: clamp((physics.damping ?? 0.08) * 0.55, 0.01, 0.14),
        friction: clamp(physics.friction ?? 0.35, 0, 0.9),
        // ضخامت پارچه به فاصله‌ی نگه‌داشته‌شده از پوست اضافه می‌شود
        thickness: clamp((physics.thickness ?? 0.5) / 1000, 0, 0.006),
        warp: line(warpStretch),
        weft: line(weftStretch),
        shear: { softness: clamp(shear * 2.4, 0.05, 0.9), maxScale: 1 + biasStretch * 0.7, minScale: 0.86 },
        bend: { softness: stiff, maxScale: 1 + biasStretch * 0.5, minScale: 1 - slack * 1.6 },
    };
};

/* ---------------------------------------------------------------------------
 * دنیای پارچه: چند تکه پارچه + برخوردگرهای بدن + زمان‌بندی ثابت
 * ------------------------------------------------------------------------- */

const FIXED_STEP = 1 / 60;

export class ClothWorld {
    constructor({ fabric = {}, skin = 0.006, budget = 8 } = {}) {
        this.law = fabricLaw(fabric);
        this.skin = skin + this.law.thickness;
        this.budget = budget;

        this.patches = [];
        this.colliders = [];

        this.substeps = 2;
        this.iterations = 3;
        this.maxSteps = 3;

        this.accumulator = 0;
        this.sleeping = false;
        this.calm = 0;
        this.energy = 0;
        this.cost = 0;
        this.quality = 'full';
        this.enabled = true;

        // آستانه‌ی خواب: جابه‌جایی کمتر از ده میکرون در یک گام یعنی پارچه نشسته
        this.sleepMotion = 1.2e-5;
        this.sleepFrames = 10;
    }

    addPatch(patch) {
        this.patches.push(patch);

        return patch;
    }

    setColliders(colliders) {
        this.colliders = colliders;
    }

    get particles() {
        return this.patches.reduce((sum, patch) => sum + patch.count, 0);
    }

    get constraints() {
        return this.patches.reduce((sum, patch) => sum + patch.constraintCount, 0);
    }

    wake() {
        this.sleeping = false;
        this.calm = 0;
    }

    /* یک گام کامل با زیرگام‌ها */
    stepOnce(dt) {
        const h = dt / this.substeps;
        const { law, colliders, patches, skin } = this;

        for (let s = 0; s < this.substeps; s++) {
            for (let p = 0; p < patches.length; p++) {
                patches[p].predict(h, law.gravity, law.damping);
            }

            for (let k = 0; k < this.iterations; k++) {
                for (let p = 0; p < patches.length; p++) {
                    patches[p].project();
                }
            }

            if (colliders.length) {
                for (let p = 0; p < patches.length; p++) {
                    patches[p].collide(colliders, skin, law.friction);
                }
            }

            for (let p = 0; p < patches.length; p++) {
                patches[p].finish(h);
            }
        }
    }

    /**
     * جلو بردن شبیه‌سازی به اندازه‌ی زمان سپری‌شده‌ی فریم.
     *
     * @param {number} elapsed ثانیه
     * @returns {number} تعداد گام‌های انجام‌شده (۰ یعنی چیزی تغییر نکرده)
     */
    update(elapsed) {
        if (! this.enabled || this.sleeping || this.patches.length === 0) {
            return 0;
        }

        // اگر تب مرورگر مدتی پنهان بوده، زمان انباشته را دور می‌ریزیم
        this.accumulator = Math.min(this.accumulator + elapsed, FIXED_STEP * this.maxSteps);

        let steps = 0;
        const started = now();

        while (this.accumulator >= FIXED_STEP && steps < this.maxSteps) {
            this.stepOnce(FIXED_STEP);
            this.accumulator -= FIXED_STEP;
            steps++;
        }

        if (steps === 0) {
            return 0;
        }

        const spent = now() - started;

        this.cost = this.cost === 0 ? spent : this.cost * 0.8 + spent * 0.2;
        this.adapt();

        let motion = 0;

        for (let p = 0; p < this.patches.length; p++) {
            motion = Math.max(motion, this.patches[p].motion);
        }

        this.energy = motion;

        if (motion < this.sleepMotion) {
            this.calm++;

            if (this.calm >= this.sleepFrames) {
                this.sleeping = true;
                this.patches.forEach((patch) => patch.rest());
            }
        } else {
            this.calm = 0;
        }

        return steps;
    }

    /*
     * افت کیفیت روی دستگاه کند: اول تکرارها، بعد زیرگام‌ها، در آخر پارچه زودتر
     * می‌نشیند و ثابت می‌ماند. هیچ‌وقت به عقب برنمی‌گردیم تا کیفیت نوسان نکند.
     */
    adapt() {
        if (this.cost <= this.budget) {
            return;
        }

        if (this.iterations > 1) {
            this.iterations--;
            this.quality = 'reduced';
        } else if (this.substeps > 1) {
            this.substeps = 1;
            this.quality = 'low';
        } else if (this.sleepMotion < 2e-4) {
            this.sleepMotion *= 4;
            this.sleepFrames = 4;
            this.maxSteps = 1;
            this.quality = 'frozen';
        }

        this.cost = this.budget;
    }
}
