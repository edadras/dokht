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
 *   • ارزانی: وقتی جنب‌وجوش پارچه از یک آستانه پایین‌تر برود — یعنی شکلش در چند
 *     فریم پشت سر هم عملاً تغییر نکند — حل‌کننده می‌خوابد و صفحه‌ی بی‌کار هیچ
 *     هزینه‌ای ندارد تا کاربر دوباره حالتی را عوض کند.
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
     * @param {number[][]} options.sections سطرهای [y, rx, rz] به ترتیب صعودی.
     *   سطر می‌تواند دو عدد دیگر هم داشته باشد — [y, rx, rz, جلو, پشت] — و
     *   آن‌وقت مقطع دو نیم‌بیضیِ جداگانه است، نه یک بیضیِ قرینه. بدنِ آدم قرینه
     *   نیست: سینه و شکم جلو می‌زنند و باسن پشت، و پارچه‌ای که رویشان می‌افتد
     *   باید همان را نشان بدهد. بی این، هر برجستگی زیر یک استوانه گم می‌شد.
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
        this.fronts = new Float32Array(rows);
        this.backs = new Float32Array(rows);
        this.capMin = capMin;
        this.capMax = capMax;

        let maxRx = 0;
        let maxRz = 0;

        for (let i = 0; i < rows; i++) {
            const row = sections[i];

            this.ys[i] = row[0];
            this.rxs[i] = Math.max(1e-4, row[1]);
            this.rzs[i] = Math.max(1e-4, row[2]);
            this.fronts[i] = Math.max(1e-4, row.length > 3 ? row[3] : row[2]);
            this.backs[i] = Math.max(1e-4, row.length > 4 ? row[4] : this.fronts[i]);
            maxRx = Math.max(maxRx, this.rxs[i]);
            maxRz = Math.max(maxRz, this.fronts[i], this.backs[i]);
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

    /*
     * نیم‌پهنا و نیم‌عمق در ارتفاع محلی y (درون‌یابی خطی روی جدول مقطع).
     *
     * out[0] نیم‌پهنا، out[1] نیم‌عمقِ جلو و out[2] نیم‌عمقِ پشت. اگر مقطع قرینه
     * باشد هر دو یکی‌اند، پس هر خواننده‌ای که فقط out[1] را می‌خواند همان
     * رفتار پیشین را می‌بیند.
     */
    sectionAt(y, out) {
        const ys = this.ys;
        const last = ys.length - 1;

        if (y <= ys[0]) {
            out[0] = this.rxs[0];
            out[1] = this.fronts[0];
            out[2] = this.backs[0];

            return -1;
        }

        if (y >= ys[last]) {
            out[0] = this.rxs[last];
            out[1] = this.fronts[last];
            out[2] = this.backs[last];

            return 1;
        }

        let i = 0;

        while (i < last && ys[i + 1] < y) {
            i++;
        }

        const t = (y - ys[i]) / Math.max(1e-6, ys[i + 1] - ys[i]);

        out[0] = this.rxs[i] + (this.rxs[i + 1] - this.rxs[i]) * t;
        out[1] = this.fronts[i] + (this.fronts[i + 1] - this.fronts[i]) * t;
        out[2] = this.backs[i] + (this.backs[i + 1] - this.backs[i]) * t;

        return 0;
    }
}

/* سهم هر تکرار از کشش «چسبندگی به اندام» */
const FOLLOW_RATE = 0.2;

/* بیشترین سرعت مجاز هر رأس (متر بر ثانیه) */
const MAX_SPEED = 6;

/*
 * اصطکاکِ پارچه روی پوست در مرحله‌ی بی‌وزنی — ببینید stepOnce.
 *
 * همان سقفی که fabricLaw برای زبرترین پارچه می‌گذارد؛ اینجا هم از همان بالاتر
 * نمی‌رویم تا رفتار مرحله با پارچه‌ی زبرِ واقعی یکی باشد.
 */
const STITCH_GRIP = 0.9;

/* بافرهای موقتِ سطح ماژول؛ داخل حلقه‌ی داغ هیچ شیئی ساخته نمی‌شود */
const local = [0, 0, 0];
const world = [0, 0, 0];
const section = [0, 0, 0];

/* ---------------------------------------------------------------------------
 * پایه‌ی مشترک تکه‌های پارچه
 * ---------------------------------------------------------------------------
 * دو جور تکه داریم: شبکه‌ی منظمِ حلقه‌ای (ClothPatch، همان نمای پارامتری) و مشِ
 * مثلثیِ یک قطعه‌ی واقعی الگو (TriPatch). توپولوژی‌شان هیچ شباهتی به هم ندارد،
 * ولی چرخه‌ی حل، برخورد با بدن، معیار خواب و یخ‌زدن برای هر دو مو به مو یکی است.
 * پس همه‌ی آن رفتار اینجا یک بار نوشته شده و هر تکه فقط «قیدهای خودش» را
 * می‌سازد. هر چه اینجاست دقیقاً همان کدِ پیشین ClothPatch است، فقط یک پله
 * بالاتر رفته تا مشِ مثلثی هم بی‌کم‌وکاست همان رفتار را داشته باشد.
 */
class PatchBase {
    /**
     * @param {object} options
     * @param {Float32Array} options.positions آرایه‌ی ۳n مختصات جهانی
     * @param {number} options.count تعداد رأس‌ها
     * @param {Uint8Array} [options.pinned] ۱ یعنی این رأس به بدن دوخته است
     * @param {Float32Array} [options.follow] چسبندگی هر رأس به اندام زیرش (۰ تا ۱)
     * @param {object} options.fabric خروجی fabricLaw()
     */
    constructor({ positions, count, pinned = null, follow = null, fabric }) {
        this.positions = positions;
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

        /*
         * «چسبندگی به اندام».
         *
         * آستین یک لوله است که دور بازو می‌چرخد و هیچ چیزی جز خود بازو نگهش
         * نمی‌دارد. اگر فقط سرِ آستین دوخته باشد، با بالا رفتن دست پارچه از روی
         * بازو سُر می‌خورد و مثل یک تکه پارچه‌ی آویزان کنار تن می‌ماند — چیزی که
         * در هیچ لباس واقعی اتفاق نمی‌افتد چون درز حلقه و خود آستین پارچه را
         * روی بازو نگه می‌دارند.
         *
         * راه‌حل: هر رأس با شدتی که خودش دارد به جای اصلی‌اش روی اندام کشیده
         * می‌شود؛ نزدیک سرشانه محکم و نزدیک مچ خیلی ملایم. پارچه هنوز تاب
         * می‌خورد و چین دارد، ولی از بازو پیاده نمی‌شود.
         */
        this.follow = follow;
        this.followRest = follow ? new Float32Array(count * 3) : null;
        this.matrix = new Float32Array(16);

        /*
         * قیدها را سازنده‌ی هر نوع تکه پر می‌کند. تا آن موقع تکه یک ابر ذره‌ی
         * آزاد است؛ همین باعث می‌شود ساخت تکه هیچ‌وقت با آرایه‌ی تعریف‌نشده
         * روبه‌رو نشود، حتی اگر ساختِ قید نیمه‌کاره بماند.
         */
        this.groups = [];
        this.tetherIndex = new Uint32Array(0);
        this.tetherAnchor = new Uint32Array(0);
        this.tetherMax = new Float32Array(0);
        this.constraintCount = 0;

        // عکس مرجع برای تشخیص نشستن پارچه
        this.snapshot = new Float32Array(positions);
        this.motion = 0;
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

    /*
     * گرفتن موقعیت محلی رأس‌ها از روی وضعیت فعلی و ماتریس وارونِ گروه بدن.
     * هم رأس‌های دوخته‌شده و هم مرجع چسبندگی از همین‌جا پر می‌شوند.
     */
    capturePins(inverse) {
        const { pins, positions, follow, followRest, count } = this;

        for (let p = 0; p < pins.length; p++) {
            const at = pins[p] * 3;

            applyMatrix(inverse, positions[at], positions[at + 1], positions[at + 2], local);
            this.setPinRest(p, local[0], local[1], local[2]);
        }

        if (! follow) {
            return;
        }

        for (let i = 0; i < count; i++) {
            const at = i * 3;

            applyMatrix(inverse, positions[at], positions[at + 1], positions[at + 2], local);

            followRest[at] = local[0];
            followRest[at + 1] = local[1];
            followRest[at + 2] = local[2];
        }
    }

    /* بردن رأس‌های دوخته‌شده به جای تازه‌شان در فضای جهانی */
    applyPins(matrix) {
        const { pins, pinRest, positions, previous } = this;

        this.matrix.set(matrix);

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
        // سقف سرعت: اگر قیدها لحظه‌ای با هم بجنگند، هیچ رأسی نباید از صحنه بپرد
        const limit = MAX_SPEED;

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

            const speed =
                velocity[at] * velocity[at] +
                velocity[at + 1] * velocity[at + 1] +
                velocity[at + 2] * velocity[at + 2];

            if (speed > limit * limit) {
                const brake = limit / Math.sqrt(speed);

                velocity[at] *= brake;
                velocity[at + 1] *= brake;
                velocity[at + 2] *= brake;
            }

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

        this.projectFollow();
        this.projectTethers();

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

    /* کشش ملایم هر رأس به سمت جای اصلی‌اش روی اندام (فقط آستین‌ها) */
    projectFollow() {
        const { follow, followRest, positions, invMass, count, matrix } = this;

        if (! follow) {
            return;
        }

        for (let i = 0; i < count; i++) {
            const strength = follow[i];

            if (strength <= 0 || invMass[i] === 0) {
                continue;
            }

            const at = i * 3;

            applyMatrix(matrix, followRest[at], followRest[at + 1], followRest[at + 2], world);

            const k = strength * FOLLOW_RATE;

            positions[at] += (world[0] - positions[at]) * k;
            positions[at + 1] += (world[1] - positions[at + 1]) * k;
            positions[at + 2] += (world[2] - positions[at + 2]) * k;
        }
    }

    /* سقف فاصله تا رأس دوخته‌شده؛ یک‌طرفه است و فقط وقتی پارچه دور شده کار می‌کند */
    projectTethers() {
        const { positions, tetherIndex, tetherAnchor, tetherMax } = this;

        for (let i = 0; i < tetherMax.length; i++) {
            const here = tetherIndex[i] * 3;
            const to = tetherAnchor[i] * 3;
            const dx = positions[here] - positions[to];
            const dy = positions[here + 1] - positions[to + 1];
            const dz = positions[here + 2] - positions[to + 2];
            const length = Math.sqrt(dx * dx + dy * dy + dz * dz);

            if (length <= tetherMax[i] || length < 1e-9) {
                continue;
            }

            const scale = tetherMax[i] / length;

            positions[here] = positions[to] + dx * scale;
            positions[here + 1] = positions[to + 1] + dy * scale;
            positions[here + 2] = positions[to + 2] + dz * scale;
        }
    }

    /*
     * گام ۳ — برخورد با بدن.
     *
     * هر ذره‌ای که داخل یکی از برخوردگرها افتاده باشد، دقیقاً روی پوسته‌ی آن
     * به‌اضافه‌ی یک فاصله‌ی پوستی گذاشته می‌شود. اصلاح «سخت» است، نه فنری؛
     * چون قرار است هیچ‌وقت ران از دامن بیرون نزند، نه اینکه کمتر بزند.
     *
     * دو نکته که بدون آن‌ها پارچه هیچ‌وقت نمی‌نشیند:
     *   • همه‌ی برخوردگرها آزمایش می‌شوند، نه فقط اولی. ذره‌ای که بین ران چپ و
     *     راست گیر کرده، اگر بعد از هل دادن از یکی بیرون بیاید و دیگری آزمایش
     *     نشود، فریم بعد دوباره داخل آن یکی است و تا ابد بین دو ران می‌رقصد.
     *   • جهش نداریم: مؤلفه‌ی عمودِ بر سطحِ سرعت پس از هل دادن صفر می‌شود.
     *     وگرنه همان هل دادن خودش به پارچه سرعت رو به بیرون می‌دهد، پارچه بالا
     *     می‌پرد، جاذبه برش می‌گرداند و صحنه هرگز آرام نمی‌گیرد.
     * اصطکاک هم روی مؤلفه‌ی مماسی می‌نشیند تا پارچه روی پوست سُر نخورد؛ پارچه‌ی
     * زبر بیشتر ترمز می‌گیرد.
     */
    collide(colliders, skin, friction) {
        const { positions, previous, invMass, count } = this;
        const slide = 1 - friction;

        for (let i = 0; i < count; i++) {
            if (invMass[i] === 0) {
                continue;
            }

            const at = i * 3;

            for (let c = 0; c < colliders.length; c++) {
                const collider = colliders[c];

                if (! collider.active) {
                    continue;
                }

                const box = collider.box;
                const px = positions[at];
                const py = positions[at + 1];
                const pz = positions[at + 2];

                if (px < box[0] || px > box[3] || py < box[1] || py > box[4] || pz < box[2] || pz > box[5]) {
                    continue;
                }

                applyMatrix(collider.inverse, positions[at], positions[at + 1], positions[at + 2], local);

                const side = collider.sectionAt(local[1], section);
                /*
                 * فاصله‌ی پوستی همین‌جا به شعاع اضافه می‌شود، نه بعد از پیدا کردن
                 * نقطه‌ی سطح.
                 *
                 * اگر بعد اضافه شود، ذره‌ای که درست روی پوست نشسته هر گام به
                 * اندازه‌ی همان فاصله بیرون پرت می‌شود، گام بعد دوباره برمی‌گردد و
                 * لباس یک لرزش دائمیِ چندمیلی‌متری می‌گیرد که هیچ‌وقت نمی‌خوابد.
                 * با شعاع بادکرده، «روی سطح بودن» یک حالت پایدار است.
                 */
                const rx = section[0] + skin;
                /*
                 * جلو یا پشت؟ ذره یا این‌سوی بدن است یا آن‌سو، و همان نیم‌بیضی
                 * را می‌بیند. دو نیمه در پهلو (z=0) هم‌عمق‌اند و مماسشان
                 * هم‌راستا، پس درزی میانشان نمی‌ماند.
                 */
                const rz = (local[2] >= 0 ? section[1] : section[2]) + skin;

                let dy = 0;
                let ry = 0;

                if (side < 0) {
                    if (! collider.capMin) {
                        continue;
                    }

                    ry = collider.capLow + skin;
                    dy = local[1] - collider.ys[0];
                } else if (side > 0) {
                    if (! collider.capMax) {
                        continue;
                    }

                    ry = collider.capHigh + skin;
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

                local[0] = sx;
                local[1] = baseY + sy;
                local[2] = sz;

                applyMatrix(collider.matrix, local[0], local[1], local[2], world);

                positions[at] = world[0];
                positions[at + 1] = world[1];
                positions[at + 2] = world[2];

                // نرمالِ سطح در فضای جهانی (ماتریس صُلب است، پس فقط چرخانده می‌شود)
                applyMatrix(collider.matrix, nx, ny, nz, world);

                const wx = world[0] - collider.matrix[12];
                const wy = world[1] - collider.matrix[13];
                const wz = world[2] - collider.matrix[14];

                const mx = positions[at] - previous[at];
                const my = positions[at + 1] - previous[at + 1];
                const mz = positions[at + 2] - previous[at + 2];
                const along = mx * wx + my * wy + mz * wz;

                // مؤلفه‌ی مماسی، با اصطکاک؛ مؤلفه‌ی رو به بیرون، حذف
                const tx = (mx - wx * along) * slide + wx * Math.min(0, along);
                const ty = (my - wy * along) * slide + wy * Math.min(0, along);
                const tz = (mz - wz * along) * slide + wz * Math.min(0, along);

                previous[at] = positions[at] - tx;
                previous[at + 1] = positions[at + 1] - ty;
                previous[at + 2] = positions[at + 2] - tz;
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

    /*
     * بیشترین جابه‌جایی نسبت به آخرین عکس مرجع.
     *
     * معیار خواب نمی‌تواند «حرکت در یک زیرگام» باشد: پارچه‌ی آویزان همیشه یک
     * تکان ریزِ رفت‌وبرگشتی دارد و آن عدد هیچ‌وقت صفر نمی‌شود. چیزی که مهم است
     * این است که شکل پارچه در طول چند فریم تغییر کند یا نه.
     */
    drift() {
        const { positions, snapshot, count } = this;
        let worst = 0;

        for (let i = 0; i < count; i++) {
            const at = i * 3;
            const dx = positions[at] - snapshot[at];
            const dy = positions[at + 1] - snapshot[at + 1];
            const dz = positions[at + 2] - snapshot[at + 2];
            const square = dx * dx + dy * dy + dz * dz;

            if (square > worst) {
                worst = square;
            }
        }

        return Math.sqrt(worst);
    }

    remember() {
        this.snapshot.set(this.positions);
    }

    /*
     * یخ زدن.
     *
     * وقتی کاربر شبیه‌سازی زنده را خاموش می‌کند، پارچه باید شکل همان لحظه‌اش را
     * نگه دارد ولی همچنان روی بدن سوار بماند؛ وگرنه با عوض کردن حالت، لباس در
     * هوا جا می‌ماند و مثل یک ایراد به‌نظر می‌رسد. پس شکل فعلی در دستگاه مختصات
     * همان گروه بدن ذخیره می‌شود و بعد فقط با ماتریس آن گروه جابه‌جا می‌شود.
     */
    freeze(inverse) {
        const { positions, count } = this;

        this.frozen ??= new Float32Array(count * 3);

        for (let i = 0; i < count; i++) {
            const at = i * 3;

            applyMatrix(inverse, positions[at], positions[at + 1], positions[at + 2], local);

            this.frozen[at] = local[0];
            this.frozen[at + 1] = local[1];
            this.frozen[at + 2] = local[2];
        }
    }

    applyFrozen(matrix) {
        const { positions, frozen, count } = this;

        if (! frozen) {
            return;
        }

        for (let i = 0; i < count; i++) {
            const at = i * 3;

            applyMatrix(matrix, frozen[at], frozen[at + 1], frozen[at + 2], world);

            positions[at] = world[0];
            positions[at + 1] = world[1];
            positions[at + 2] = world[2];
        }

        this.previous.set(positions);
        this.snapshot.set(positions);
        this.velocity.fill(0);
    }

    /* خواباندن کامل: سرعت‌ها صفر می‌شوند تا پارچه دیگر نلرزد */
    rest() {
        this.velocity.fill(0);
        this.previous.set(this.positions);
        this.snapshot.set(this.positions);
        this.motion = 0;
    }
}

/* ---------------------------------------------------------------------------
 * یک تکه پارچه‌ی شبکه‌ای
 * ---------------------------------------------------------------------------
 * هر قطعه‌ی لباسِ نمای پارامتری (بالاتنه، دامن، هر آستین) یک ClothPatch است.
 * موقعیت‌ها همان آرایه‌ی position هندسه‌ی three هستند؛ پس نوشتن نتیجه هزینه‌ی
 * کپی ندارد.
 */
export class ClothPatch extends PatchBase {
    /**
     * @param {object} options
     * @param {Float32Array} options.positions آرایه‌ی ۳n مختصات جهانی
     * @param {number} options.rows تعداد حلقه‌ها (از پایین به بالا)
     * @param {number} options.segments تعداد نقطه در هر حلقه (حلقه بسته است)
     * @param {Uint8Array} options.pinned ۱ یعنی این رأس به بدن دوخته است
     * @param {Float32Array} [options.follow] چسبندگی هر رأس به اندام زیرش (۰ تا ۱)
     * @param {object} options.fabric خروجی fabricLaw()
     */
    constructor({ positions, rows, segments, pinned, follow = null, fabric }) {
        super({ positions, count: rows * segments, pinned, follow, fabric });

        this.rows = rows;
        this.segments = segments;

        this.groups = this.buildConstraints();
        this.buildTethers();
        this.constraintCount =
            this.groups.reduce((sum, group) => sum + group.rest.length, 0) + this.tetherMax.length;
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
                    shear.push(here, at(row + 1, col + 1));
                    shear.push(at(row, col + 1), at(row + 1, col));
                }

                if (row + 2 < rows) {
                    bendWarp.push(here, at(row + 2, col));
                }
            }
        }

        /*
         * قیدهای عمودی از بالا به پایین ساخته می‌شوند، نه برعکس.
         *
         * حل گاوس-زایدل هر قید را به ترتیب اصلاح می‌کند؛ اگر از لبه‌ی پایین شروع
         * کنیم، کشش درزها باید چند تکرار بالا برود تا به سرشانه برسد و لباس تا
         * آن موقع کِش آمده است. با شروع از سرشانه، همان یک تکرار اول کشش را تا
         * لبه می‌رساند و پارچه دیگر زیر وزن خودش «تلمبه» نمی‌زند.
         */
        for (let row = rows - 2; row >= 0; row--) {
            for (let col = 0; col < segments; col++) {
                warp.push(at(row + 1, col), at(row, col));
            }
        }

        make(warp, fabric.warp);
        make(weft, fabric.weft);
        make(shear, fabric.shear);
        make(bendWarp, fabric.bend);
        make(bendWeft, fabric.bend);

        return groups;
    }

    /*
     * «مهار بلند»: از هر رأس یک قید مستقیم به نزدیک‌ترین رأس دوخته‌شده‌ی همان
     * ستون، با بیشترین طول ممکنِ همان مسیر.
     *
     * چرا لازم است؟ چون زنجیره‌ی درزها با دو سه تکرار هیچ‌وقت کامل حل نمی‌شود و
     * دامنِ بلند زیر وزن خودش کِش می‌آید و بالا و پایین می‌رود. این قید فقط یک
     * سقف است — اگر رأس از سرشانه دورتر از طول پارچه شد، همان‌جا برمی‌گردد — و
     * چون سرِ دیگرش بی‌حرکت است، با یک تکرار حل می‌شود.
     *
     * طول مسیر از جمع طول درزهای همان ستون گرفته می‌شود؛ این عدد از فاصله‌ی
     * واقعی روی پارچه بیشتر است، پس قید هیچ‌وقت لباس را بی‌جا تنگ نمی‌کند.
     */
    buildTethers() {
        const { rows, segments, positions, invMass, fabric } = this;
        const stretch = fabric.warp.maxScale;
        const index = [];
        const anchor = [];
        const max = [];

        const length = (a, b) => {
            const dx = positions[a * 3] - positions[b * 3];
            const dy = positions[a * 3 + 1] - positions[b * 3 + 1];
            const dz = positions[a * 3 + 2] - positions[b * 3 + 2];

            return Math.sqrt(dx * dx + dy * dy + dz * dz);
        };

        const distance = new Float32Array(rows);
        const owner = new Int32Array(rows);

        for (let col = 0; col < segments; col++) {
            distance.fill(Infinity);
            owner.fill(-1);

            for (let row = 0; row < rows; row++) {
                const here = row * segments + col;

                if (invMass[here] === 0) {
                    distance[row] = 0;
                    owner[row] = here;
                } else if (row > 0 && owner[row - 1] >= 0) {
                    distance[row] = distance[row - 1] + length(here, here - segments);
                    owner[row] = owner[row - 1];
                }
            }

            for (let row = rows - 2; row >= 0; row--) {
                const here = row * segments + col;

                if (owner[row + 1] < 0) {
                    continue;
                }

                const candidate = distance[row + 1] + length(here, here + segments);

                if (candidate < distance[row]) {
                    distance[row] = candidate;
                    owner[row] = owner[row + 1];
                }
            }

            for (let row = 0; row < rows; row++) {
                const here = row * segments + col;

                if (owner[row] < 0 || owner[row] === here || invMass[here] === 0) {
                    continue;
                }

                index.push(here);
                anchor.push(owner[row]);
                max.push(distance[row] * stretch);
            }
        }

        this.tetherIndex = new Uint32Array(index);
        this.tetherAnchor = new Uint32Array(anchor);
        this.tetherMax = new Float32Array(max);
    }
}

/* ---------------------------------------------------------------------------
 * یک قطعه‌ی واقعیِ الگو
 * ---------------------------------------------------------------------------
 * ClothPatch یک شبکه‌ی منظم است و همین برای پوسته‌ی پارامتری بس بود. ولی قطعه‌ی
 * الگو نه مستطیل است نه استوانه: حلقه‌ی آستین گود است، یقه بریده است و کنارش
 * ساسون خالی شده. چنین چیزی فقط با یک مشِ مثلثیِ دلخواه بیان می‌شود.
 *
 * سه تفاوت با تکه‌ی شبکه‌ای، هر سه از همین‌جا می‌آیند:
 *
 *   • طول استراحتِ قیدها از خودِ الگوی تخت گرفته می‌شود، نه از جایی که قطعه
 *     برای شروع دور بدن چیده شده. پارچه در واقعیت تخت بافته شده و فرم سه‌بعدی
 *     را درز و ساسون به آن می‌دهند؛ اگر طول استراحت را از چیدنِ اولیه بگیریم،
 *     همان استوانه‌ی چیدن می‌شود «فرم درست» و دوختن هیچ چیزی را عوض نمی‌کند.
 *   • وزن هر رأس از مساحت مثلث‌های پیرامونش می‌آید. مش مثلثی یکنواخت نیست و
 *     رأس‌های لبه سهم کمتری از پارچه دارند؛ اگر همه هم‌وزن باشند، لبه‌ی قطعه
 *     مثل زنجیر سنگین آویزان می‌شود و درز را با خودش پایین می‌کشد.
 *   • جهت هر یال روی الگو تعیین می‌کند از کدام قانون پارچه پیروی کند: یال
 *     نزدیک به راستای طولِ الگو تار است، نزدیک به عرض پود، و بینشان اریب.
 *     همین است که یک قطعه‌ی اریب‌بُر جور دیگری می‌افتد.
 */

/* یک خانواده‌ی قید فاصله؛ طول استراحت از منبعی که به آن داده می‌شود */
const distanceGroup = (pairs, law, restOf) => {
    const size = pairs.length / 2;
    const a = new Uint32Array(size);
    const b = new Uint32Array(size);
    const rest = new Float32Array(size);

    for (let i = 0; i < size; i++) {
        a[i] = pairs[i * 2];
        b[i] = pairs[i * 2 + 1];
        rest[i] = restOf(a[i], b[i]) || 1e-5;
    }

    return { a, b, rest, ...law };
};

/* هرمِ دودویی کوچک؛ فقط برای مسیر کوتاه روی مش، در زمان ساخت */
const heapPush = (heap, node, cost) => {
    let i = heap.length / 2;

    heap.push(node, cost);

    while (i > 0) {
        const parent = (i - 1) >> 1;

        if (heap[parent * 2 + 1] <= cost) {
            break;
        }

        heap[i * 2] = heap[parent * 2];
        heap[i * 2 + 1] = heap[parent * 2 + 1];
        heap[parent * 2] = node;
        heap[parent * 2 + 1] = cost;
        i = parent;
    }
};

const heapPop = (heap) => {
    const size = heap.length / 2;
    const node = heap[0];
    const lastNode = heap[(size - 1) * 2];
    const lastCost = heap[(size - 1) * 2 + 1];

    heap.length -= 2;

    if (size === 1) {
        return node;
    }

    heap[0] = lastNode;
    heap[1] = lastCost;

    let i = 0;

    for (;;) {
        const left = i * 2 + 1;
        const right = left + 1;
        let best = i;

        if (left < size - 1 && heap[left * 2 + 1] < heap[best * 2 + 1]) {
            best = left;
        }

        if (right < size - 1 && heap[right * 2 + 1] < heap[best * 2 + 1]) {
            best = right;
        }

        if (best === i) {
            break;
        }

        const bn = heap[best * 2];
        const bc = heap[best * 2 + 1];

        heap[best * 2] = heap[i * 2];
        heap[best * 2 + 1] = heap[i * 2 + 1];
        heap[i * 2] = bn;
        heap[i * 2 + 1] = bc;
        i = best;
    }

    return node;
};

/* بیشترین و کمترین وزن مجاز هر رأس نسبت به میانگین؛ یک مثلث تیغه‌ای نباید
 * رأسش را چنان سبک کند که با یک اصلاح از صحنه بپرد */
const MASS_SPREAD = 6;

export class TriPatch extends PatchBase {
    /**
     * @param {object} options
     * @param {Float32Array} options.positions ۳n مختصات جهانی (متر)
     * @param {ArrayLike<number>} options.indices سه‌تایی‌های مثلث (پادساعتگرد)
     * @param {ArrayLike<number>} [options.grain] ۲n مختصات همان رأس روی الگوی تخت،
     *        بر حسب **متر**. اگر داده شود طول استراحت از اینجا خوانده می‌شود.
     * @param {Uint8Array} [options.pinned]
     * @param {Float32Array} [options.follow]
     * @param {object} options.fabric خروجی fabricLaw()
     */
    constructor({ positions, indices, grain = null, pinned = null, follow = null, fabric }) {
        super({ positions, count: positions.length / 3, pinned, follow, fabric });

        this.indices = indices instanceof Uint32Array ? indices : Uint32Array.from(indices);
        this.grain = grain;
        this.triangles = this.indices.length / 3;

        this.weigh();
        this.groups = this.buildConstraints();
        this.buildTethers();
        this.constraintCount =
            this.groups.reduce((sum, group) => sum + group.rest.length, 0) + this.tetherMax.length;
    }

    /*
     * وزن رأس‌ها از مساحت.
     *
     * سهم هر مثلث میان سه رأسش پخش می‌شود؛ همان چیزی که در مکانیک به آن جرمِ
     * توده‌ای می‌گویند. بعد همه بر میانگین تقسیم می‌شوند تا اعداد در همان مقیاسِ
     * ClothPatch بمانند و ضریب‌های تنظیم‌شده‌ی حل‌کننده دست‌نخورده کار کنند.
     */
    weigh() {
        const { positions, indices, invMass, count, grain } = this;
        const mass = new Float64Array(count);

        for (let t = 0; t < indices.length; t += 3) {
            const ia = indices[t];
            const ib = indices[t + 1];
            const ic = indices[t + 2];
            let area;

            if (grain) {
                const ax = grain[ib * 2] - grain[ia * 2];
                const ay = grain[ib * 2 + 1] - grain[ia * 2 + 1];
                const bx = grain[ic * 2] - grain[ia * 2];
                const by = grain[ic * 2 + 1] - grain[ia * 2 + 1];

                area = Math.abs(ax * by - ay * bx) / 2;
            } else {
                const ax = positions[ib * 3] - positions[ia * 3];
                const ay = positions[ib * 3 + 1] - positions[ia * 3 + 1];
                const az = positions[ib * 3 + 2] - positions[ia * 3 + 2];
                const bx = positions[ic * 3] - positions[ia * 3];
                const by = positions[ic * 3 + 1] - positions[ia * 3 + 1];
                const bz = positions[ic * 3 + 2] - positions[ia * 3 + 2];
                const cx = ay * bz - az * by;
                const cy = az * bx - ax * bz;
                const cz = ax * by - ay * bx;

                area = Math.sqrt(cx * cx + cy * cy + cz * cz) / 2;
            }

            mass[ia] += area / 3;
            mass[ib] += area / 3;
            mass[ic] += area / 3;
        }

        let sum = 0;
        let live = 0;

        for (let i = 0; i < count; i++) {
            if (invMass[i] !== 0 && mass[i] > 0) {
                sum += mass[i];
                live++;
            }
        }

        this.area = mass.reduce((total, value) => total + value, 0);

        if (! live) {
            return;
        }

        const mean = sum / live;

        for (let i = 0; i < count; i++) {
            if (invMass[i] === 0) {
                continue;
            }

            invMass[i] = clamp(mean / Math.max(mass[i], 1e-12), 1 / MASS_SPREAD, MASS_SPREAD);
        }
    }

    /*
     * ساخت قیدها از مثلث‌ها.
     *
     *   هر یال            → یک قید فاصله (تار/پود/اریب بر اساس جهتش روی الگو)
     *   هر جفت مثلثِ همسایه → یک قید فاصله میان دو رأس روبه‌رو، با قانون خمش
     *
     * قید خمشِ «فاصله‌ی دو رأس روبه‌رو» ساده‌ترین شکل ممکن است و همان کاری را
     * می‌کند که در ClothPatch قیدِ یکی‌درمیان می‌کرد: هرچه آن فاصله به حالت تختِ
     * الگو نزدیک‌تر بماند، پارچه کمتر تا می‌خورد. چون طول استراحتش از الگوی تخت
     * می‌آید، پارچه‌ی سفت خودش را صاف نگه می‌دارد و پارچه‌ی لخت راحت چین می‌خورد.
     *
     * ترتیب قیدها از بالا به پایین مرتب می‌شود؛ به همان دلیلی که در ClothPatch
     * قیدهای عمودی از سرشانه شروع می‌شدند: حل گاوس-زایدل کشش را در همان تکرار
     * اول تا لبه‌ی پایین می‌رساند و پارچه زیر وزن خودش تلمبه نمی‌زند.
     */
    buildConstraints() {
        const { positions, indices, grain, fabric, count } = this;
        const map = new Map();

        const touch = (u, v, opposite) => {
            const key = u < v ? u * count + v : v * count + u;
            const found = map.get(key);

            if (found) {
                found.other = opposite;

                return;
            }

            map.set(key, { a: u, b: v, own: opposite, other: -1 });
        };

        for (let t = 0; t < indices.length; t += 3) {
            const ia = indices[t];
            const ib = indices[t + 1];
            const ic = indices[t + 2];

            touch(ia, ib, ic);
            touch(ib, ic, ia);
            touch(ic, ia, ib);
        }

        const edges = [...map.values()];

        // ارتفاعِ بالاترین سرِ هر یال؛ کلید مرتب‌سازی از بالا به پایین
        const height = (edge) => Math.max(positions[edge.a * 3 + 1], positions[edge.b * 3 + 1]);

        edges.sort((one, two) => {
            const diff = height(two) - height(one);

            // گره‌گشاییِ قطعی: هم‌ارتفاع‌ها با شماره‌ی رأس مرتب می‌شوند تا خروجی
            // به پایداری sort موتور جاوااسکریپت وابسته نباشد
            return diff !== 0 ? diff : one.a - two.a || one.b - two.b;
        });

        const restOf = grain
            ? (a, b) => Math.hypot(grain[a * 2] - grain[b * 2], grain[a * 2 + 1] - grain[b * 2 + 1])
            : (a, b) =>
                  Math.hypot(
                      positions[a * 3] - positions[b * 3],
                      positions[a * 3 + 1] - positions[b * 3 + 1],
                      positions[a * 3 + 2] - positions[b * 3 + 2],
                  );

        const warp = [];
        const weft = [];
        const bias = [];
        const bend = [];

        for (let i = 0; i < edges.length; i++) {
            const edge = edges[i];

            if (grain) {
                const dx = grain[edge.b * 2] - grain[edge.a * 2];
                const dy = grain[edge.b * 2 + 1] - grain[edge.a * 2 + 1];
                const length = Math.hypot(dx, dy) || 1e-9;
                // نسبت مؤلفه‌ی طولی؛ ۱ یعنی کاملاً هم‌راستای تار، ۰ یعنی پود
                const along = Math.abs(dy) / length;

                (along > 0.866 ? warp : along < 0.5 ? weft : bias).push(edge.a, edge.b);
            } else {
                warp.push(edge.a, edge.b);
            }

            if (edge.other >= 0) {
                bend.push(edge.own, edge.other);
            }
        }

        const groups = [];

        if (warp.length) groups.push(distanceGroup(warp, fabric.warp, restOf));
        if (weft.length) groups.push(distanceGroup(weft, fabric.weft, restOf));
        if (bias.length) groups.push(distanceGroup(bias, fabric.shear, restOf));
        if (bend.length) groups.push(distanceGroup(bend, fabric.bend, restOf));

        this.edgeCount = edges.length;

        return groups;
    }

    /*
     * مهار بلند روی مش مثلثی.
     *
     * در تکه‌ی شبکه‌ای، «مسیر تا نزدیک‌ترین رأس دوخته‌شده» همان ستون بود. اینجا
     * ستونی در کار نیست، پس همان مسیر با کوتاه‌ترین راه روی گرافِ یال‌ها پیدا
     * می‌شود. فلسفه‌اش عوض نشده: یک سقفِ یک‌طرفه که فقط وقتی پارچه از تکیه‌گاهش
     * دورتر از طول واقعی پارچه شود به کار می‌افتد.
     *
     * اگر قطعه به هیچ جای بدن دوخته نشده باشد (که حالت عادیِ درز-محور است)،
     * هیچ مهاری ساخته نمی‌شود و هزینه‌ای هم ندارد.
     */
    buildTethers() {
        const { count, invMass, groups, fabric } = this;

        if (this.pins.length === 0) {
            return;
        }

        const degree = new Uint32Array(count);
        let links = 0;

        for (const group of groups) {
            for (let i = 0; i < group.rest.length; i++) {
                degree[group.a[i]]++;
                degree[group.b[i]]++;
                links += 2;
            }
        }

        const start = new Uint32Array(count + 1);

        for (let i = 0; i < count; i++) {
            start[i + 1] = start[i] + degree[i];
        }

        const cursor = start.slice(0, count);
        const target = new Uint32Array(links);
        const weight = new Float32Array(links);

        for (const group of groups) {
            for (let i = 0; i < group.rest.length; i++) {
                const ia = group.a[i];
                const ib = group.b[i];

                target[cursor[ia]] = ib;
                weight[cursor[ia]++] = group.rest[i];
                target[cursor[ib]] = ia;
                weight[cursor[ib]++] = group.rest[i];
            }
        }

        const distance = new Float64Array(count).fill(Infinity);
        const owner = new Int32Array(count).fill(-1);
        const heap = [];

        for (let p = 0; p < this.pins.length; p++) {
            const pin = this.pins[p];

            distance[pin] = 0;
            owner[pin] = pin;
            heapPush(heap, pin, 0);
        }

        while (heap.length) {
            const node = heapPop(heap);
            const base = distance[node];

            for (let e = start[node]; e < start[node + 1]; e++) {
                const next = target[e];
                const candidate = base + weight[e];

                if (candidate < distance[next] - 1e-12) {
                    distance[next] = candidate;
                    owner[next] = owner[node];
                    heapPush(heap, next, candidate);
                }
            }
        }

        const stretch = fabric.warp.maxScale;
        const index = [];
        const anchor = [];
        const max = [];

        for (let i = 0; i < count; i++) {
            if (owner[i] < 0 || owner[i] === i || invMass[i] === 0) {
                continue;
            }

            index.push(i);
            anchor.push(owner[i]);
            max.push(distance[i] * stretch);
        }

        this.tetherIndex = new Uint32Array(index);
        this.tetherAnchor = new Uint32Array(anchor);
        this.tetherMax = new Float32Array(max);
    }
}

/* ---------------------------------------------------------------------------
 * درز: دو کمان که به هم می‌رسند
 * ---------------------------------------------------------------------------
 * یک درز فهرستی از جفت‌رأس است؛ هر جفت یک سوزن. دو سرِ جفت می‌توانند روی دو
 * قطعه‌ی جدا باشند (درز پهلو) یا روی یک قطعه (ساسون، درز خودِ قطعه).
 *
 * چرا «شدت» لازم است؟ چون قطعه‌ها اولِ کار دور بدن پخش شده‌اند و دو لبه‌ی یک درز
 * گاهی ده سانتی‌متر از هم فاصله دارند. اگر همان لحظه‌ی اول جفت‌ها را روی هم
 * بگذاریم، پارچه با یک پرش کشیده می‌شود، قیدهای فاصله می‌ترکند و مش تا آخرِ کار
 * چروکِ بی‌جا نگه می‌دارد. پس به‌جای «چسباندن»، هدفِ هر جفت از فاصله‌ی امروزش
 * آرام‌آرام به صفر می‌رسد: درز مثل یک بخیه‌ی واقعی کشیده می‌شود، قطعه‌ها در چند
 * دهم ثانیه به هم می‌رسند و پارچه فرصت دارد خودش را مرتب کند.
 *
 * درز فقط می‌کشد و هیچ‌وقت هُل نمی‌دهد: دو لبه‌ای که نزدیک‌تر از هدف شده‌اند
 * لابد جای بهتری پیدا کرده‌اند و باز کردنشان فقط لرزش می‌سازد.
 */
export class SeamSet {
    /**
     * @param {object} options
     * @param {PatchBase} options.a تکه‌ی سمت اول
     * @param {PatchBase} [options.b] تکه‌ی سمت دوم؛ نبودنش یعنی درز درون همان تکه
     * @param {ArrayLike<number>} options.pairs زوج‌های [iA, iB, iA, iB, …]
     * @param {string} [options.label] برچسب فارسی برای گزارش
     * @param {string} [options.kind] seam | dart
     * @param {number} [options.rest] فاصله‌ی هدف (متر)؛ صفر یعنی روی هم
     * @param {number} [options.duration] ثانیه‌ی شبیه‌سازی تا کاملاً دوخته شود
     * @param {number} [options.stiffness] سهم هر تکرار از بستن فاصله
     * @param {ArrayLike<number>} [options.hinge] سه‌گانه‌های [iA, iB, restLength] برای
     *        نگه‌داشتن جهتِ پارچه روی درز؛ ببینید projectHinge
     * @param {number} [options.hingeStiffness] سختیِ همان قید
     */
    constructor({
        a,
        b = null,
        pairs,
        label = '',
        kind = 'seam',
        rest = 0,
        duration = 0.6,
        stiffness = 0.7,
        hinge = null,
        hingeStiffness = 0.25,
        second = null,
        weight = null,
    }) {
        this.a = a;
        this.b = b || a;
        this.pairs = pairs instanceof Uint32Array ? pairs : Uint32Array.from(pairs);
        this.count = this.pairs.length / 2;

        /*
         * سوزن می‌تواند میانِ دو رأس بنشیند، نه فقط روی یک رأس.
         *
         * دو سمتِ یک درز هیچ‌وقت رأس‌های هم‌ردیف ندارند. تا وقتی هر سوزن باید سرِ
         * یک رأس می‌خورد، جایی که یک سمت ریزتر بود چند رأسش روی *یک* رأسِ سمتِ
         * دیگر می‌افتاد و درز نوارِ پارچه را روی یک نقطه جمع می‌کرد. اندازه گرفته
         * شد، روی الگوی تخت و بی هیچ فیزیکی: در درزِ سرشانه دو سوزنِ پی‌درپی یک
         * سمتش ۱٫۴۴ سانتی‌متر جلو می‌رفت و سمتِ دیگرش صفر — یعنی ۱٫۴ سانتی‌متر
         * پارچه زیرِ یک سوزن لِه می‌شد و همسایه‌اش جِر می‌خورد. روی مانکن: مثلثِ
         * سی‌برابر کشیده در سرشانه و حلقه، و لبه‌های تیکه‌تیکه.
         *
         * حالا سرِ دومِ هر سوزن می‌تواند نقطه‌ای روی پاره‌خطِ میانِ دو رأس باشد:
         * P = (1-w)·B₀ + w·B₁. پس هر رأسِ سمتِ ریز شریکِ خودش را دارد، بی بادبزن
         * و بی کم شدنِ شمارِ سوزن. جرمِ مؤثرِ آن نقطه هم به همان نسبت پخش می‌شود،
         * همان کاری که قیدِ باریسنتریک می‌کند.
         */
        this.second = second ? (second instanceof Uint32Array ? second : Uint32Array.from(second)) : null;
        this.weight = weight ? (weight instanceof Float32Array ? weight : Float32Array.from(weight)) : null;
        this.label = label;
        this.kind = kind;
        this.rest = Math.max(0, rest);
        this.stiffness = clamp(stiffness, 0.05, 1);
        this.duration = Math.max(1e-3, duration);
        this.strength = 0;
        this.gap = new Float32Array(this.count);

        /*
         * درز، لولاست — و لولای بی‌سختی پارچه را روی خودش برمی‌گرداند.
         *
         * قیدِ درز فقط جای دو لبه را یکی می‌کند و هیچ چیزی نمی‌گوید که پارچه از
         * آن‌سو به کدام سمت ادامه پیدا کند. اندازه گرفتیم: روی پیراهنِ نشسته،
         * فاصلهٔ دو لبه زیر یک سانتی‌متر بود ولی زاویهٔ میان دو رویه روی درزِ
         * سرآستین ۱۳۶ درجه (بدترین ۱۷۰). ۱۷۰ درجه یعنی آستین پشت‌به‌پشتِ حلقه
         * تا خورده؛ از بیرون فقط ضخامتِ لبه دیده می‌شود و چشم آن را «آستین وصل
         * نیست» می‌خواند. کاربر هم دقیقاً همین را دید.
         *
         * دوختِ واقعی این‌طور نیست: نخ دو لبه را به هم می‌بندد و دو تکه پارچه
         * کنارِ هم ادامه پیدا می‌کنند. مدلش همان مدلِ خمشِ درونِ قطعه است — یک
         * قیدِ فاصله میان دو رأسِ *پشتِ* درز. اگر پارچه صاف باشد آن دو رأس به
         * اندازهٔ مجموعِ فاصله‌شان از لبه از هم دورند؛ تا خوردن این فاصله را کم
         * می‌کند، پس قید بازش می‌کند.
         *
         * سختی‌اش نرم است (پیش‌فرض ۰٫۲۵): آستین باید بتواند دور بازو بخوابد،
         * فقط نباید برگردد.
         */
        this.hinge = hinge ? Float32Array.from(hinge) : null;
        this.hinges = this.hinge ? this.hinge.length / 3 : 0;
        this.hingeStiffness = clamp(hingeStiffness, 0, 1);

        this.measure(this.gap);
    }

    /* جای سرِ دومِ سوزنِ شماره i؛ اگر شریکِ کسری باشد، نقطه‌ای روی پاره‌خط */
    target(i, out) {
        const { b, pairs, second, weight } = this;
        const ib = pairs[i * 2 + 1] * 3;
        const w = weight ? weight[i] : 0;

        if (! second || w <= 0) {
            out[0] = b.positions[ib];
            out[1] = b.positions[ib + 1];
            out[2] = b.positions[ib + 2];

            return out;
        }

        const ic = second[i] * 3;

        out[0] = b.positions[ib] * (1 - w) + b.positions[ic] * w;
        out[1] = b.positions[ib + 1] * (1 - w) + b.positions[ic + 1] * w;
        out[2] = b.positions[ib + 2] * (1 - w) + b.positions[ic + 2] * w;

        return out;
    }

    /* فاصله‌ی امروزِ هر جفت؛ مبنای رمپِ دوختن و هم معیار سلامت درز */
    measure(out = null) {
        const { a, pairs, count } = this;
        const point = [0, 0, 0];
        let total = 0;

        for (let i = 0; i < count; i++) {
            const ia = pairs[i * 2] * 3;

            this.target(i, point);

            const length = Math.hypot(
                a.positions[ia] - point[0],
                a.positions[ia + 1] - point[1],
                a.positions[ia + 2] - point[2],
            );

            if (out) {
                out[i] = length;
            }

            total += length;
        }

        return count ? total / count : 0;
    }

    /* میانگین فاصله‌ی جفت‌ها؛ همان «چقدر درز هنوز باز است» */
    error() {
        return this.measure(null);
    }

    /* جلو بردن رمپ به اندازه‌ی زمان یک زیرگام */
    advance(dt) {
        if (this.strength < 1) {
            this.strength = clamp(this.strength + dt / this.duration, 0, 1);
        }
    }

    /* دوختنِ آنی؛ برای بارگذاری دوباره‌ی یک لباس که قبلاً نشسته بوده */
    weld() {
        this.strength = 1;
    }

    /* باز کردن دوباره‌ی درز، با اندازه‌گیری تازه‌ی فاصله‌ها */
    reset() {
        this.strength = 0;
        this.measure(this.gap);
    }

    project() {
        const strength = this.strength;

        if (strength <= 0 || this.count === 0) {
            return;
        }

        /*
         * لولا پیش از دوخت پرتاب می‌شود تا حرفِ آخر مالِ دوخت باشد.
         *
         * هر دو قید روی همان رأس‌ها کار می‌کنند و کمی با هم می‌جنگند: لولا
         * می‌خواهد پارچه صاف بماند و دوخت می‌خواهد دو لبه روی هم بیفتند. اول که
         * لولا را آخر گذاشتیم، درزِ سرشانه ۵٫۸ میلی‌متر باز ماند و آزمون گرفتش.
         * بستن درز مهم‌تر است؛ صافی چیزی است که تا حدِ ممکن نگه داشته می‌شود.
         */
        this.projectHinge(strength);

        const { a, b, pairs, second, weight, gap, rest, count } = this;
        const pa = a.positions;
        const pb = b.positions;
        const wa = a.invMass;
        const wb = b.invMass;
        const k = this.stiffness * strength;
        const point = [0, 0, 0];

        for (let i = 0; i < count; i++) {
            const na = pairs[i * 2];
            const nb = pairs[i * 2 + 1];
            const w = second && weight ? weight[i] : 0;
            const nc = w > 0 ? second[i] : nb;
            const ma = wa[na];

            /*
             * جرمِ مؤثرِ نقطه‌ی کسری: (1-w)²·w₀ + w²·w₁ — همان چیزی که قیدِ
             * باریسنتریک می‌دهد. اگر w صفر باشد، همان جرمِ خودِ رأس درمی‌آید.
             */
            const mb = w > 0
                ? ((1 - w) * (1 - w) * wb[nb]) + (w * w * wb[nc])
                : wb[nb];
            const sum = ma + mb;

            if (sum === 0) {
                continue;
            }

            const ia = na * 3;

            this.target(i, point);

            const dx = pa[ia] - point[0];
            const dy = pa[ia + 1] - point[1];
            const dz = pa[ia + 2] - point[2];
            const length = Math.sqrt(dx * dx + dy * dy + dz * dz);

            if (length < 1e-9) {
                continue;
            }

            // هدفِ امروز: از فاصله‌ی اولیه به سمت فاصله‌ی نهایی، به اندازه‌ی شدت
            const goal = rest + Math.max(0, gap[i] - rest) * (1 - strength);

            if (length <= goal) {
                continue;
            }

            const scale = ((length - goal) / length) * k / sum;
            const cx = dx * scale;
            const cy = dy * scale;
            const cz = dz * scale;

            if (ma !== 0) {
                pa[ia] -= cx * ma;
                pa[ia + 1] -= cy * ma;
                pa[ia + 2] -= cz * ma;
            }

            if (w > 0) {
                const share = (1 - w) * wb[nb];
                const rest2 = w * wb[nc];
                const ib = nb * 3;
                const ic = nc * 3;

                pb[ib] += cx * share;
                pb[ib + 1] += cy * share;
                pb[ib + 2] += cz * share;
                pb[ic] += cx * rest2;
                pb[ic + 1] += cy * rest2;
                pb[ic + 2] += cz * rest2;
            } else if (mb !== 0) {
                const ib = nb * 3;

                pb[ib] += cx * mb;
                pb[ib + 1] += cy * mb;
                pb[ib + 2] += cz * mb;
            }
        }
    }

    /**
     * باز نگه داشتنِ درز: دو رأسِ پشتِ درز از هم دور می‌مانند.
     *
     * تنها یک‌سویه عمل می‌کند — فاصله‌ی کمتر از هدف را باز می‌کند و به فاصله‌ی
     * بیشتر کاری ندارد. چون خواستهٔ ما «تا نخوردن» است، نه «صاف ماندن»: پارچه‌ای
     * که دور بازو می‌پیچد فاصله‌اش بیشتر نمی‌شود، کمتر می‌شود.
     */
    projectHinge(strength) {
        if (! this.hinge || this.hingeStiffness <= 0) {
            return;
        }

        const { a, b, hinge, hinges } = this;
        const pa = a.positions;
        const pb = b.positions;
        const wa = a.invMass;
        const wb = b.invMass;
        const k = this.hingeStiffness * strength;

        for (let i = 0; i < hinges; i++) {
            const na = hinge[i * 3];
            const nb = hinge[i * 3 + 1];
            const goal = hinge[i * 3 + 2];
            const ma = wa[na];
            const mb = wb[nb];
            const sum = ma + mb;

            if (sum === 0 || goal <= 0) {
                continue;
            }

            const ia = na * 3;
            const ib = nb * 3;
            const dx = pa[ia] - pb[ib];
            const dy = pa[ia + 1] - pb[ib + 1];
            const dz = pa[ia + 2] - pb[ib + 2];
            const length = Math.sqrt(dx * dx + dy * dy + dz * dz);

            if (length >= goal) {
                continue;
            }

            if (length < 1e-9) {
                continue; // جهت معلوم نیست؛ تکرارِ بعد بازش می‌کند
            }

            const scale = ((goal - length) / length) * k / sum;
            const cx = dx * scale;
            const cy = dy * scale;
            const cz = dz * scale;

            if (ma !== 0) {
                pa[ia] += cx * ma;
                pa[ia + 1] += cy * ma;
                pa[ia + 2] += cz * ma;
            }

            if (mb !== 0) {
                pb[ib] -= cx * mb;
                pb[ib + 1] -= cy * mb;
                pb[ib + 2] -= cz * mb;
            }
        }
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
        this.seams = [];

        this.substeps = 2;
        this.iterations = 3;

        /*
         * پاسِ اضافیِ *فقط درز* در هر تکرار.
         *
         * درزها دیر بسته می‌شوند: اندازه گرفته شد که با تکرارِ ۳، خطای ماندگارِ
         * درز روی ترنچ‌کت ۱۲٫۲ سانتی‌متر می‌ماند و با تکرارِ ۸ به ۸٫۰ می‌رسد.
         * ولی سخت‌ترکردنِ قید جواب نداد — با سختیِ ۱٫۰ به ۱۹٫۹ *بدتر* شد، چون
         * هر تکرار زیادی هل می‌دهد و حل نوسان می‌کند. یعنی مسئله تعدادِ پاس است،
         * نه شدتش.
         *
         * تکرارِ کامل، قیدِ پارچه را هم تکرار می‌کند و گران است؛ شمارِ قیدهای درز
         * کسری از قیدهای پارچه است، پس پاسِ اضافه فقط روی درز کمِ‌هزینه است.
         */
        this.seamPasses = 1;
        // روی صفحه‌ی کند، حداکثر دو گام در فریم؛ پارچه کمی آهسته‌تر می‌نشیند ولی
        // هزینه‌ی هر فریم از کنترل خارج نمی‌شود
        this.maxSteps = 2;

        this.accumulator = 0;
        this.sleeping = false;
        this.energy = 0;
        this.cost = 0;
        this.quality = 'full';
        // چند فریم اول اندازه‌گیری نمی‌شوند؛ گرم شدن مرورگر نباید کیفیت را
        // برای همیشه پایین بیاورد
        this.warmup = 30;
        this.overBudget = 0;
        this.enabled = true;

        // آستانه‌ی خواب: اگر شکل پارچه در ۱۲ فریم (حدود یک‌پنجم ثانیه) کمتر از یک
        // میلی‌متر جابه‌جا شود، دیگر چیزی برای دیدن نمانده و حل‌کننده می‌خوابد.
        // پارچه‌ی خیلی لخت مثل حریر یک لرزش ریزِ همیشگی دارد که با چشم دیده
        // نمی‌شود؛ معیار «تغییر شکل» است نه «سکون کامل».
        this.sleepDrift = 1e-3;
        this.sleepFrames = 12;
        this.window = 0;
    }

    addPatch(patch) {
        this.patches.push(patch);

        return patch;
    }

    /*
     * افزودن یک درز.
     *
     * درز به هیچ تکه‌ای «تعلق» ندارد؛ دو سرش می‌توانند روی دو تکه‌ی جدا باشند.
     * پس فهرستش جدا نگه داشته می‌شود و در همان حلقه‌ی حل، کنار قیدهای پارچه
     * اجرا می‌شود.
     */
    addSeam(seam) {
        this.seams.push(seam);

        return seam;
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

    /* هنوز دوختن تمام نشده؟ تا وقتی درزی نیمه‌باز است، صحنه حق خوابیدن ندارد */
    get sewing() {
        for (let i = 0; i < this.seams.length; i++) {
            if (this.seams[i].strength < 1) {
                return true;
            }
        }

        return false;
    }

    /* بیشترین فاصله‌ی باقی‌مانده‌ی درزها؛ برای گزارش و اشکال‌زدایی */
    seamError() {
        let worst = 0;

        for (let i = 0; i < this.seams.length; i++) {
            // تا، درزِ باز نیست: فاصله‌اش ضخامتِ خودِ تاست و باید بماند
            if (this.seams[i].kind === 'crease') {
                continue;
            }

            worst = Math.max(worst, this.seams[i].error());
        }

        return worst;
    }

    wake() {
        this.sleeping = false;
        this.window = 0;
        this.patches.forEach((patch) => patch.remember());
    }

    /*
     * چند گام پیش از اولین فریم.
     *
     * لباس تازه‌ساخته دقیقاً روی فرم الگوست و هنوز زیر وزن خودش ننشسته. اگر
     * همان را نشان بدهیم، کاربر اول یک لباس سفت می‌بیند و بعد چند لحظه افتادنش
     * را. با چند گام خشک پیش از اولین رندر، صفحه از همان اول درست باز می‌شود و
     * وقت پردازنده‌ی کمتری هم می‌گیرد.
     */
    presettle(steps = 25) {
        for (let i = 0; i < steps; i++) {
            this.stepOnce(FIXED_STEP);
        }

        this.patches.forEach((patch) => patch.remember());
        this.window = 0;
    }

    /*
     * یک گام کامل با زیرگام‌ها.
     *
     * ترتیب داخل هر تکرار عمدی است: اول درزها، بعد قیدهای پارچه، و برخورد در
     * آخر.
     *
     *   • درز پیش از پارچه، چون درز فقط یک «هدف» است؛ اگر آخرین حرف را بزند،
     *     رأس‌های لبه را جلوتر از همسایه‌هایشان می‌کِشد و مش کنار درز تیغه‌تیغه
     *     می‌شود. وقتی قیدهای پارچه بعد از آن اجرا شوند، همان کشش را در تمام
     *     قطعه پخش می‌کنند — دقیقاً کاری که پارچه‌ی واقعی زیر دست خیاط می‌کند.
     *   • برخورد در آخر، چون هیچ درزی حق ندارد پارچه را داخل بدن ببرد. حرف
     *     آخر همیشه با بدن است.
     */
    stepOnce(dt) {
        const h = dt / this.substeps;
        const { law, colliders, patches, seams, skin } = this;

        for (let s = 0; s < this.substeps; s++) {
            for (let p = 0; p < patches.length; p++) {
                patches[p].predict(h, law.gravity, law.damping);
            }

            for (let i = 0; i < seams.length; i++) {
                seams[i].advance(h);
            }

            for (let k = 0; k < this.iterations; k++) {
                for (let n = 0; n < this.seamPasses; n++) {
                    for (let i = 0; i < seams.length; i++) {
                        seams[i].project();
                    }
                }

                for (let p = 0; p < patches.length; p++) {
                    patches[p].project();
                }
            }

            if (colliders.length) {
                /*
                 * در بی‌وزنی پارچه روی پوست سُر نمی‌خورد.
                 *
                 * مرحله‌ی دوختِ بی‌وزنی هیچ نیرویی ندارد جز کشش خودِ درزها. آن
                 * کشش لباس را جمع می‌کند، بدن جلوی جمع شدنش را می‌گیرد، و
                 * تفاوت به بالا (یا پایینِ) مخروطِ بدن می‌رود: با اصطکاکِ
                 * پیش‌فرضِ ۰٫۳۵ لباس هر گام کمی سُر می‌خورد و در پایانِ همان
                 * مرحله، جایی است که الگو نگفته بود. اندازه گرفته شد — بی هیچ
                 * جاذبه‌ای: پشتِ پیراهن از ۱۳۴ به ۱۴۵ سانتی‌متر بالا رفت (روی
                 * یوک تا شد، همان تکه‌های روی هم افتاده‌ی سرشانه) و بالاتنه‌ی
                 * پیراهنِ راپ از ۱۳۹ به ۱۲۵ پایین آمد (خطِ یقه تا زیرِ سینه باز
                 * ماند). بعد supportGarment همان‌جای غلط را میخکوب می‌کرد و
                 * جاذبه هم دیگر برش نمی‌گرداند. با برخوردگرِ خاموش این سُر خوردن
                 * اصلاً نبود — پس اصطکاک است، نه دوخت.
                 *
                 * پارچه‌ی واقعی هم روی تن همین‌طور است: تا وزنی رویش نیفتاده،
                 * زیرِ دستِ خیاط از جایش تکان نمی‌خورد.
                 */
                const grip = law.gravity === 0 ? STITCH_GRIP : law.friction;

                for (let p = 0; p < patches.length; p++) {
                    patches[p].collide(colliders, skin, grip);
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

        // هزینه را برای «هر گام» می‌سنجیم، نه برای هر فریم؛ وگرنه روی صفحه‌ای که
        // به دلیلی کند شده و سه گام در فریم می‌رود، بودجه الکی سه‌برابر می‌شود
        const spent = (now() - started) / steps;

        this.cost = this.cost === 0 ? spent : this.cost * 0.8 + spent * 0.2;
        this.adapt();

        let motion = 0;

        for (let p = 0; p < this.patches.length; p++) {
            motion = Math.max(motion, this.patches[p].motion);
        }

        this.energy = motion;
        this.window++;

        // مقایسه‌ی شکل با عکس مرجع فقط هر چند فریم یک بار؛ خودِ مقایسه هزینه دارد
        if (this.window >= this.sleepFrames) {
            let drift = 0;

            for (let p = 0; p < this.patches.length; p++) {
                drift = Math.max(drift, this.patches[p].drift());
            }

            this.window = 0;
            this.drift = drift;

            // تا وقتی سوزنِ آخرین درز نخورده، لباس هنوز در حال شکل گرفتن است؛
            // خوابیدن در این حال یعنی نیم‌دوخته ماندنِ همیشگیِ لباس
            if (drift < this.sleepDrift && ! this.sewing) {
                this.sleeping = true;
                this.patches.forEach((patch) => patch.rest());
            } else {
                this.patches.forEach((patch) => patch.remember());
            }
        }

        return steps;
    }

    /*
     * افت کیفیت روی دستگاه کند: اول تکرارها، بعد زیرگام‌ها، در آخر پارچه زودتر
     * می‌نشیند و ثابت می‌ماند. هیچ‌وقت به عقب برنمی‌گردیم تا کیفیت نوسان نکند.
     */
    adapt() {
        if (this.warmup > 0) {
            this.warmup--;
            this.cost = 0;

            return;
        }

        // فقط کندیِ پایدار مهم است؛ یک فریم شلوغ دلیل افت کیفیت نیست
        this.overBudget = this.cost > this.budget ? this.overBudget + 1 : 0;

        if (this.overBudget < 8) {
            return;
        }

        this.overBudget = 0;

        if (this.iterations > 1) {
            this.iterations--;
            this.quality = 'reduced';
        } else if (this.substeps > 1) {
            this.substeps = 1;
            this.quality = 'low';
        } else if (this.sleepFrames > 4) {
            this.sleepDrift *= 6;
            this.sleepFrames = 4;
            this.maxSteps = 1;
            this.quality = 'frozen';
        }

        this.cost = this.budget;
    }
}
