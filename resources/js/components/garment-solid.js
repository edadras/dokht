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

/*
 * صحنه دو چراغِ روشن دارد و جنسِ لامبرت رنگ را در نور ضرب می‌کند، پس رنگِ خامِ
 * رامپ سفید‌شسته درمی‌آید. این ضریب همان را جبران می‌کند تا رنگی که چشم
 * می‌بیند همان رنگی باشد که راهنما نشان می‌دهد.
 */
const dim = (r, g, b) => [r * 0.45, g * 0.45, b * 0.45];

/*
 * رنگِ نقشهٔ کشش: آبی چین‌خورده، خاکستری درست، قرمز کشیده.
 *
 * مرز ±۱۰٪ است، چون پارچهٔ بافته تا همان حد بی‌درد کش می‌آید و چین می‌خورد؛
 * بیرون از آن است که خیاط باید نگاه کند.
 */
const strainColour = (value) => {
    const off = clamp((value - 1) / 0.1, -1, 1);

    return off >= 0
        ? dim(0.55 + off * 0.42, 0.55 - off * 0.36, 0.53 - off * 0.4)
        : dim(0.55 + off * 0.35, 0.55 + off * 0.1, 0.53 - off * 0.42);
};

/*
 * رنگِ نقشهٔ آزادی: قرمز چسبیده، سبز اندازه، آبی گشاد.
 *
 * زیر یک سانتی‌متر یعنی روی تن نشسته، دو تا شش سانتی‌متر آزادیِ معمولِ لباس،
 * و بالای ده سانتی‌متر گشاد. همان مقیاسی که با انگشت سنجیده می‌شود.
 */
const easeColour = (metres) => {
    const cm = clamp(metres * 100, 0, 12);

    if (cm <= 2) {
        return dim(0.92, 0.35 + (cm / 2) * 0.3, 0.3);
    }

    if (cm <= 6) {
        const t = (cm - 2) / 4;

        return dim(0.92 - t * 0.6, 0.65 + t * 0.1, 0.3 + t * 0.1);
    }

    const t = (cm - 6) / 6;

    return dim(0.32 - t * 0.1, 0.75 - t * 0.25, 0.4 + t * 0.45);
};

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



/*
 * اندازهٔ بدن هنگام دوخت — و چرا در آخر «کامل» ماند.
 *
 * فرضیه این بود که باید مثل خیاط اول در فضای آزاد دوخت و بعد تن کرد. آن نیمه
 * درست از آب درآمد و اندازه‌اش گرفته شد: بی بدن، بدترین شکافِ درز روی هشت
 * لباس از ۸٫۵–۳۵٫۴ سانتی‌متر به ۱٫۶–۳٫۱ می‌رسد. یعنی الگو و دوخت سالم‌اند و
 * بدن است که وسطِ راهِ درز می‌ایستد.
 *
 * ولی نیمهٔ دوم گران‌تر از سودش درآمد. لباسِ بی‌تن وا می‌رود (قدِ لباس عروس
 * ۱۵۵ به ۱۱۵) و دوباره تن کردنش دقیق نیست: آستین می‌خوابد و بعد بازو از
 * کنارش رد می‌شود. سنجهٔ بینایی روی پنج مدل، مجموعِ سوراخ: با دوختِ آزاد
 * ۸۸۱۹ پیکسل، با بدنِ کامل ۷۳۳۸.
 *
 * پس بدن سرِ جایش می‌ماند، و آنچه از آن آزمایش ماند دو چیزِ سودمند است که
 * پایین‌تر می‌آیند: برداشتن‌و‌سرِ‌جا‌گذاشتنِ لباس پس از دوخت، و مجالِ کششِ
 * پارچه هنگام تن کردن. عدد را عوض کنید تا دوباره سنجیده شود.
 */
const SEWING_BODY = 1;

/*
 * چقدر پارچه هنگام تن کردن اجازهٔ کشش دارد.
 *
 * یک یعنی همان کشسانیِ خودِ پارچه. با ۲٫۴ لباس شل می‌شود و سُر می‌خورد
 * (سوراخِ پیراهن ۹٫۳٪ به ۱۲٪ رفت)؛ با بدنِ کامل، ۱ بهترین بود.
 */
const DRESS_GIVE = 1;

/**
 * جای لباس: میانگینِ همهٔ رأس‌ها، و بالاترین نقطه‌اش.
 *
 * برای برگرداندنِ لباسِ دوخته‌شده به جای خودش لازم است. میانگین تنهایی کافی
 * نیست: پارچه که جمع می‌شود میانگین بالا می‌آید در حالی که سرشانه پایین رفته.
 */
const middleOf = (drape) => {
    let x = 0;
    let z = 0;
    let count = 0;
    let top = -Infinity;

    for (const { patch } of drape.patches) {
        for (let v = 0; v < patch.count; v++) {
            x += patch.positions[v * 3];
            z += patch.positions[v * 3 + 2];
            top = Math.max(top, patch.positions[v * 3 + 1]);
            count++;
        }
    }

    return count ? { x: x / count, y: top, z: z / count } : { x: 0, y: 0, z: 0 };
};

/** ارتفاعِ همهٔ رأس‌ها، برای اندازه گرفتنِ جابه‌جاییِ بعدی. */
export const heightsOf = (drape) => {
    let count = 0;

    for (const { patch } of drape.patches) {
        count += patch.count;
    }

    const out = new Float64Array(count);
    let at = 0;

    for (const { patch } of drape.patches) {
        for (let v = 0; v < patch.count; v++) {
            out[at++] = patch.positions[v * 3 + 1];
        }
    }

    return out;
};

/*
 * سقفِ برگرداندنِ لباس روی شانه.
 *
 * جاروب شد، روی جمعِ سوراخِ پنج مدل: بی این کار ۲۳۱۳ پیکسل، و با سقفِ
 * ۴ ⇒ ۲۲۶۰، ۵ ⇒ ۲۲۰۳، ۶ ⇒ ۲۰۳۸، ۸ ⇒ ۲۲۲۸، ۱۰ ⇒ ۲۲۳۸ سانتی‌متر.
 *
 * سقف لازم است چون فرو رفتنِ ناحیهٔ سرشانه برای هر لباسی یک‌جور نیست: کت رسمی
 * و راپ ۱۰٫۸ و ۱۰٫۶ سانتی‌متر و ترنچ‌کت ۲٫۷. کتِ رسمی همان ده سانتی‌متر را
 * می‌خواهد ولی راپ نه — بالا بردنِ راپ لبهٔ دامنش را از ران بالاتر می‌برد و
 * پا لخت می‌شود. شش سانتی‌متر همان‌جایی است که هر دو سود می‌برند.
 */
/*
 * شمارِ پرتابِ قید در مرحلهٔ نشستن — که با بودجهٔ *هر فریم* یکی نیست.
 *
 * `stats.solver.iterations` برای نمای زنده است: باید شصت بار در ثانیه اجرا
 * شود، پس برای مشِ سنگین به دو می‌رسد. دوختِ سه‌بعدی ولی یک بار اجرا می‌شود و
 * تصویری ثابت می‌سازد؛ همان دو، آن‌جا کم است.
 *
 * و کجا کم است؟ هرجا زنجیرهٔ پارچه بلند باشد. حل‌کنندهٔ موقعیتی خطا را یال به
 * یال جلو می‌برد، پس لباسِ بلند به پرتابِ بیشتری نیاز دارد تا وزنِ خودش را
 * نگه دارد. اندازه گرفته شد روی لگینگ (سقفِ کششِ یال ۱٪ است):
 *
 *   دو پرتاب    بدترین کششِ یال ۳٫۵۰×  یالِ بیش از ۱۰٪: ۹۴۱۲ از ۲۴۳۸۴  لبه: −۳۴cm
 *   ۲۴ پرتاب    بدترین کششِ یال ۲٫۰۴×  یالِ بیش از ۱۰٪:  ۶۹۹ از ۲۴۳۸۴  لبه:  −۶cm
 *
 * یعنی پیراهنِ بلند و شلوار و مانتو زیرِ وزنِ خودشان سی سانتی‌متر دراز می‌شدند
 * و از کفِ صحنه می‌زدند بیرون — نه چون پارچه اجازهٔ کشش داشت، که نداشت، بلکه
 * چون حل نمی‌رسید که جلویش را بگیرد. سی‌ونه مدل از ۱۷۲ مدلِ نمونه همین بودند.
 *
 * جاروب روی ۴۳ مدل (پذیرفته‌شده) و روی سوراخِ پنج مدل:
 *
 *   ۲  ⇒ ۲۵/۴۳   ۲۱۴۴ پیکسل    ۴٫۱ ثانیه برای یک قبا
 *   ۶  ⇒   —     ۲۰۵۲ پیکسل
 *   ۱۰ ⇒ ۲۸/۴۳
 *   ۱۲ ⇒   —     ۲۰۶۵ پیکسل    ۸ ثانیه
 *   ۲۰ ⇒ ۲۹/۴۳   ۲۳۴۱ پیکسل   ۱۲ ثانیه
 *   ۳۰ ⇒ ۲۹/۴۳                ۱۶ ثانیه
 *
 * دوازده برداشته شد: هم کاتالوگ را بالا می‌برد و هم پنج مدلِ ردیاب را بهتر
 * می‌کند، و بهایش چهار ثانیه است. بالاتر از آن، کاتالوگ یک مدل جلو می‌رود و
 * پیراهنِ راپ عقب.
 */
const SETTLE_PASSES = 12;

const SAG_CAP = 0.06;

/**
 * ناحیهٔ سرشانه در دوخت چقدر *به‌طور معمول* پایین رفت — میانه، نه بیشینه.
 *
 * لنگرِ قدیمیِ این جابه‌جایی «بالاترین رأسِ لباس» بود و همان یک رأس همه‌چیز را
 * خراب می‌کرد: نوکِ برگردانِ یقهٔ کت روی ۱۶۱٫۶ سانتی‌متر چیده می‌شد — بالاتر از
 * سرِ مانکن — و لباس ۹٫۷ سانتی‌متر بالا کشیده می‌شد تا آن یک نقطه سرِ جایش
 * برگردد. برای همین کلاً خاموش شده بود.
 *
 * میانه این مشکل را ندارد: قطعه‌ای که جای خودش را پیدا کرده (سرِ آستین که باید
 * به حلقه برسد، یقه که باید بخوابد) در دو سرِ توزیع می‌ماند و میانه را تکان
 * نمی‌دهد.
 *
 * و فقط رأس‌هایی شمرده می‌شوند که *در چیدن* بالای حلقهٔ آستین بوده‌اند: لباس از
 * شانه آویزان است و همان‌جاست که باید سرِ جایش بماند. با شمردنِ کلِ لباس، دامنِ
 * بلندی که پایینش اصلاً تکان نخورده میانه را به صفر می‌کشاند.
 *
 * @param {object} drape
 * @param {Float64Array} was ارتفاع‌ها پیش از دوخت؛ ببینید heightsOf
 * @param {number} [above] فقط رأس‌های بالاتر از این تراز
 */
export const sagOf = (drape, was, above = -Infinity) => {
    const moves = [];
    let at = 0;

    for (const { patch } of drape.patches) {
        for (let v = 0; v < patch.count; v++) {
            const from = was[at++];

            if (from >= above) {
                moves.push(patch.positions[v * 3 + 1] - from);
            }
        }
    }

    if (moves.length === 0) {
        return 0;
    }

    moves.sort((a, b) => a - b);

    return Math.max(-SAG_CAP, moves[moves.length >> 1]);
};

/**
 * دوختِ بی‌وزنِ لنگردار: هر چهل گام، لباس دوباره سرِ جای چیدنش گذاشته می‌شود.
 *
 * چرا یک بازنشانیِ آخرِ کار بس نبود؟ چون فرو رفتن سقف ندارد. درز + برخورد با
 * هم یک جغجغه می‌سازند: قیدِ درز پارچه را دورِ بدن جمع می‌کند، برخورد آن را
 * بیرونِ بدن نگه می‌دارد، و تنها راهِ باز، سُر خوردن به سمتِ باریکیِ تن است —
 * رو به پایین، هر گام کمی، بی هیچ برگشتی. اندازه گرفته شد روی بلوزِ کلاسیک:
 * بی‌درز صفر، بی‌برخورد ۶٫۳ سانتی‌متر و ایست، و با هر دو ۱۷٫۶ سانتی‌متر در
 * ۲۴۰ گام *و همچنان می‌رفت*. بازنشانیِ یک‌باره سقفِ SAG_CAP دارد (۶ سانتی‌متر)
 * و ۱۹ سانتی‌متر فرو رفتن را برنمی‌گرداند — نوزده مدلِ نمونهٔ پهن همین بودند.
 *
 * لنگرِ هر بازنشانی همان میانهٔ sagOf است، در هر دو سو:
 *   • فرو رفتن ⇒ بالا کشیدن، ولی هرگز بالاتر از بلندترین نقطهٔ *چیدن*: در
 *     بی‌وزنی بالارفتنِ لباس همان‌قدر بی‌معنی است که فرورفتنش، و بی این سقف،
 *     بندِ پاپیون که در دوخت باز می‌شود میانه را منفی می‌کند و بالِ عمودی‌اش
 *     را جلوی صورتِ مانکن می‌فرستد.
 *   • باد کردن ⇒ پایین آوردن، باز هم با سقفِ SAG_CAP در هر تکه: جلیقهٔ
 *     دوردیفه در دوخت ۹ سانتی‌متر بالا می‌رفت و بالای چانه می‌ایستاد.
 *
 * جابه‌جایی صُلب است؛ هیچ درزی باز نمی‌شود و دوخت میانِ تکه‌ها ادامه دارد.
 *
 * @param {object} world حل‌کننده
 * @param {object} drape خروجی buildDrape
 * @param {Float64Array} placed ارتفاعِ رأس‌ها سرِ چیدن؛ ببینید heightsOf
 * @param {number} above فقط رأس‌های بالای این تراز لنگرند (حلقهٔ آستین)
 * @param {number} steps شمارِ کلِ گام‌های بی‌وزنی
 */
export const sewAnchored = (world, drape, placed, above, steps, chunk = 40) => {
    let placedHigh = -Infinity;

    for (let i = 0; i < placed.length; i++) {
        if (placed[i] > placedHigh) {
            placedHigh = placed[i];
        }
    }

    for (let done = 0; done < steps; done += chunk) {
        world.presettle(Math.min(chunk, steps - done));

        let sag = sagOf(drape, placed, above);

        if (sag < 0) {
            let high = -Infinity;

            for (const { patch } of drape.patches) {
                for (let v = 0; v < patch.count; v++) {
                    if (patch.positions[v * 3 + 1] > high) {
                        high = patch.positions[v * 3 + 1];
                    }
                }
            }

            sag = Math.max(sag, -Math.max(0, placedHigh - high));
        } else {
            sag = Math.min(sag, SAG_CAP);
        }

        if (Math.abs(sag) > 0.002) {
            shift(drape, { x: 0, y: 0, z: 0 }, { x: 0, y: sag, z: 0 });
        }
    }
};

/**
 * پایانِ دوخت: جوش، صاف‌کردن، بیرون‌راندنِ بدن با حلِ دوباره، جوشِ دوباره.
 *
 * ترتیب از اندازه‌گیری آمد (کت رسمی / پیراهن، مثلثِ خراب و خطای درز و رأسِ
 * درونِ بدن):
 *
 *   جوش → صاف → راندنِ تکی            ۲۴۳ / ۲٫۶۵ / ۴۱     ۲۳۱ / ۰٫۳۵ / ۵۱
 *   جوش → صاف → راندن با حلِ دوباره    ۱۱۷ / ۸٫۱۹ / ۴۹     ۱۰۹ / ۵٫۴۴ / ۶۵
 *   … → جوشِ دوباره → راندنِ تکی       ۲۱۹ / ۲٫۶۶ / ۲۸     ۲۱۴ / ۰٫۴۸ / ۴۴
 *
 * حلِ دوباره مش را سالم می‌کند ولی جوش را باز می‌کند (جوش قید نیست)؛ جوشِ
 * دوباره درز را برمی‌گرداند و راندنِ تکیِ آخر بدن را بیرون نگه می‌دارد. سطرِ
 * سوم در هر سه عدد از سطرِ اول بهتر یا برابر است.
 */
export const finishGarment = (world, drape, weldSeams) => {
    weldSeams(drape);
    relax(drape);
    world.pushOutside(4);
    weldSeams(drape);
    world.pushOutside(1);
};

/** جابه‌جاییِ صُلبِ کلِ لباس؛ هیچ درزی باز نمی‌شود. */
export const shift = (drape, want, have) => {
    const dx = want.x - have.x;
    const dy = want.y - have.y;
    const dz = want.z - have.z;

    if (Math.abs(dx) + Math.abs(dy) + Math.abs(dz) < 1e-6) {
        return;
    }

    for (const { patch } of drape.patches) {
        for (let v = 0; v < patch.count; v++) {
            patch.positions[v * 3] += dx;
            patch.positions[v * 3 + 1] += dy;
            patch.positions[v * 3 + 2] += dz;
        }

        patch.remember();
    }
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
 *
 * `grow` بدن را نازک‌تر می‌کند: یک یعنی خودِ بدن، صفر یعنی هیچ. برای «تن کردنِ»
 * لباسِ دوخته‌شده لازم است — ببینید ترتیبِ دوخت در boot().
 */
export const bodyColliders = (Collider, body, table, grow = 1) => {
    const out = [];
    const level = table.level;
    /*
     * `grow` همهٔ بدن را با هم کوچک می‌کند.
     *
     * امتحان کردم بازو و پا را کامل نگه دارم تا آستین سرِ جایش بماند، ولی
     * درزِ حلقه دیگر بسته نمی‌شد — بازو دقیقاً همان‌جا ایستاده که دو لبهٔ حلقه
     * باید به هم برسند. دروازهٔ کیفیت از پنج مدل به دو مدل افتاد. پس بدن
     * یک‌دست آب می‌رود.
     */
    const thin = (sections, keep = false) => sections.map((row) => {
        const scaled = row.slice();

        // ستونِ صفر ارتفاع است و بقیه شعاع؛ فقط شعاع‌ها کوچک می‌شوند
        for (let i = 1; i < scaled.length && ! keep; i++) {
            scaled[i] *= grow;
        }

        return scaled;
    });

    const at = (sections, name, offset = [0, 0, 0], spin = 0, keep = false, caps = {}) => {
        const cos = Math.cos(spin);
        const sin = Math.sin(spin);
        const collider = new Collider({ sections: thin(sections, keep), name, ...caps });

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

    /*
     * `hull` است نه `profile`: برخوردگر باید همان پوستی باشد که addBody رسم
     * می‌کند. نیم‌رخِ چیدن از سرشانه تا سینه عقب می‌نشیند و آن نوار هیچ
     * برخوردگری نداشت — پارچه از تویش رد می‌شد و پوستِ سرشانه دیده می‌شد.
     */
    at((table.hull || table.profile).filter(([y]) => y >= level.crotch - 1e-6), 'torso');

    const neck = table.radii.neck;
    /*
     * گردن و سر دورِ مرکزِ خودِ حلقهٔ گردن، نه دورِ محورِ تن: گردنِ آواتار
     * چند سانتی‌متر پشتِ مرکزِ سینه است (ستونِ ششمِ نیم‌رخ؛ مانکنِ محاسباتی صفر).
     */
    const neckZ = sampleRing(body.torso, body.level.neck).z || 0;

    at([
        [level.neck - 0.02, neck * 1.05, neck * 1.05, neck * 1.05, neck * 1.05, neckZ / 100],
        [level.chin, neck * 0.92, neck * 0.92, neck * 0.92, neck * 0.92, neckZ / 100],
    ], 'neck');

    const headR = body.head.radius / 100;
    const headY = (body.height - body.head.centre) / 100;

    at([
        [headY - headR * 0.95, headR * 0.42, headR * 0.44, headR * 0.44, headR * 0.44, neckZ / 100],
        [headY, headR * 0.9, headR * 0.95, headR * 0.95, headR * 0.95, neckZ / 100],
        [headY + headR * 0.95, headR * 0.42, headR * 0.44, headR * 0.44, headR * 0.44, neckZ / 100],
    ], 'head');

    /* دست و پا: زنجیرهٔ خودشان، از پایین به بالا */
    const chain = (rows) => rows
        .map((row) => [-row.y / 100, row.r / 100, row.r / 100])
        .sort((a, b) => a[0] - b[0]);

    /*
     * سرِ بالای بازو و ران گنبد ندارد — و همین یک سطر بود که لباس را بالا می‌بُرد.
     *
     * برخوردگرِ بازو یک استوانهٔ مقطع‌دار است که پیش‌فرضش هر دو سر را گرد می‌کند.
     * سرِ پایینش دست است و باید گرد باشد؛ سرِ بالایش ولی *مفصلِ شانه* است و
     * تویِ تنه فرو رفته — آن‌جا گنبدی وجود ندارد که پارچه رویش بنشیند. با گنبد،
     * برخوردگرِ بازو ۵ سانتی‌متر بالاتر از مفصل (۱۳۴٫۶) یعنی تا ۱۳۹٫۶ بالا
     * می‌آمد، در حالی که سرشانه ۱۳۷٫۱ است: هر رأسی که روی سرشانه و سرِ آستین
     * می‌نشست، درونِ آن گنبد بود و هر گام به بیرون و *بالا* پس زده می‌شد. درزها
     * بقیهٔ لباس را دنبالش می‌کشیدند.
     *
     * اندازه گرفته شد (کتِ رسمی، مرحلهٔ دوختِ بی‌وزنی): سهمِ هر برخوردگر از
     * جابه‌جاییِ عمودی — تنه ۰٫۰، گردن ۰٫۰، سر ۰٫۰، پا ۰٫۰، بازوی چپ ۳٫۵،
     * بازوی راست ۳٫۳ سانتی‌متر. دو بازو، تمامِ کار. و با برداشتنِ گنبد، لبهٔ
     * پایینِ تنه از ۹۷ به ۸۳ سانتی‌متر برگشت — یعنی همان‌جا که الگو چیده بودش —
     * و آستین از ۱۰۳ به ۸۰ رسید، یعنی از آرنج تا مچ.
     *
     * ران هم همین است: سرِ بالایش کشالهٔ ران است، نه یک سرِ آزاد.
     */
    /*
     * ...ولی سرِ شانه باید بماند، چون *کشیده* می‌شود.
     *
     * بی گنبد، کتِ اسپرت سرِ شانه لخت شد (۱٫۳٪ ⇒ ۳٫۷٪، هر دو لکه روی y=۱۰۷
     * پیکسل یعنی درست روی سرشانه). دلیلش این است که تنه از سرشانه تا سینه به
     * پهنای دنده‌ها باریک می‌شود تا آستین کنارش جا بگیرد، پس سرِ دلتویید را نه
     * تنه می‌پوشاند نه بازو.
     *
     * راهِ درست همان چیزی است که نماگر *می‌کشد*: دو حلقهٔ سرِ شانه، با همان
     * شعاع‌های ۰٫۵۵ و ۰٫۸۶ برابرِ بازو و همان ارتفاع‌های ۰٫۸۵ و ۰٫۴۵ برابر بالاتر
     * از مفصل. آن‌وقت برخوردگر همان پوستی است که دیده می‌شود — نه نیم‌کره‌ای به
     * شعاعِ کاملِ بازو که ۵ سانتی‌متر بالاتر از مفصل می‌ایستد.
     */
    const ball = body.arm[0].r / 100;

    [[-1, 'armL'], [1, 'armR']].forEach(([side, name]) => {
        at(
            [
                ...chain(body.arm),
                [ball * 0.45, ball * 0.86, ball * 0.86],
                [ball * 0.85, ball * 0.55, ball * 0.55],
            ],
            name,
            [side * table.armOffset, table.armTop, table.armDepth || 0],
            side * table.armTilt,
            false,
            { capMax: false },
        );
    });

    [[-1, 'legL'], [1, 'legR']].forEach(([side, name]) => {
        at(chain(body.leg), name, [side * body.leg[0].x / 100, level.crotch, 0], 0, false, { capMax: false });
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
 *
 * و لبهٔ آزاد — دمِ لباس، لبهٔ جلو، دهانهٔ یقه، سرِ آستین — قانونِ خودش را دارد.
 *
 * میانگین‌گیری با «همهٔ همسایه‌ها» فقط برای رأسی درست است که همسایه‌هایش دورِ
 * تا دورش باشند. رأسِ روی لبه نصفِ همسایه‌ها را دارد و همه‌شان *درونِ* قطعه‌اند،
 * پس میانگین همیشه به داخل می‌افتد و رأس را به تو می‌کشد. هر رأسِ لبه هم به
 * اندازهٔ شمارِ مثلث‌هایش کشیده می‌شود، نه یک‌اندازه — و همین است که لبه را
 * دندانه‌دار می‌کند: با سه دور، دمِ ترنچ‌کت ریش‌ریش می‌ماند و با سی دور، مثل
 * پرده کنگره‌دار می‌شد. هیچ تعداد دوری لبه را صاف نمی‌کرد، چون خودِ قانون غلط
 * بود، نه شمارِ دورها.
 *
 * لبه یک *خط* است، نه یک سطح؛ پس روی خطِ خودش صاف می‌شود: هر رأسِ لبه به میانهٔ
 * دو همسایهٔ لبه‌ایِ خودش نزدیک می‌شود و بس. موجِ رأس‌به‌رأس پاک می‌شود، ولی
 * لبه سرِ جای خودش می‌ماند و به درونِ پارچه کشیده نمی‌شود.
 *
 * و تا کجا نزدیک شود؟ تا همان‌جا که الگوی تخت می‌گوید.
 *
 * خطِ لبه همه‌جا صاف نیست: حلقهٔ آستین کمان است، دمِ دامن کمان است، و گوشهٔ
 * دمِ لباس با لبهٔ جلو یک زاویهٔ تیزِ واقعی دارد. کشاندنِ همهٔ این‌ها به میانهٔ
 * دو همسایه یعنی گرد کردنِ چیزی که خیاط تیز بریده — با همین قانون و بی هیچ
 * دندانه‌ای، گوشهٔ تختهٔ آزمایشی ۸٫۲ میلی‌متر تو می‌رفت.
 *
 * ولی خودِ الگوی تخت می‌گوید هر رأس چقدر *باید* از وترِ دو همسایه‌اش فاصله
 * داشته باشد. پس همان اندازه نگه داشته می‌شود و فقط چیزی که از آن بیشتر است —
 * یعنی همانی که حل‌کننده اضافه کرده — برداشته می‌شود. روی هشت لباسِ سنجه،
 * انحرافِ اضافیِ رأس‌های لبهٔ آزاد از وترِ همسایه‌هایشان از ۲٫۳ به ۱٫۴
 * میلی‌متر رسید و شمارِ دندانه‌های بلندتر از چهار میلی‌متر از ۴۱۲ به ۱۸۸.
 */
export const relax = (drape, rounds = 3, weight = 0.4) => {
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
        const grain = entry.mesh.grain;
        const count = patch.count;
        const hold = locked.get(patch) || new Uint8Array(count);

        /*
         * لبهٔ آزاد: یالی که فقط یک مثلث دارد. دو همسایهٔ لبه‌ایِ هر رأس همین‌جا
         * درمی‌آید — و رأسی که سه یال لبه‌ای دارد (جایی که قطعه نیشگون شده)
         * همسایهٔ یکتا ندارد، پس مثل رأسِ درونی رفتار می‌کند.
         */
        const seen = new Map();

        for (let t = 0; t < indices.length; t += 3) {
            for (let e = 0; e < 3; e++) {
                const a = indices[t + e];
                const b = indices[t + (e + 1) % 3];
                const key = a < b ? a * count + b : b * count + a;
                const found = seen.get(key);

                if (found) {
                    found.twice = true;
                } else {
                    seen.set(key, { a, b, twice: false });
                }
            }
        }

        const rim = new Int32Array(count * 2).fill(-1);
        const rimmed = new Uint8Array(count);

        for (const edge of seen.values()) {
            if (edge.twice) {
                continue;
            }

            for (const [v, other] of [[edge.a, edge.b], [edge.b, edge.a]]) {
                if (rimmed[v] < 2) {
                    rim[v * 2 + rimmed[v]] = other;
                }

                rimmed[v]++;
            }
        }

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

                /* رأسِ لبه فقط روی خطِ لبه صاف می‌شود، نه به سمتِ درونِ قطعه */
                const onRim = rimmed[v] === 2;
                const from = onRim ? v * 2 : heads[v];
                const to = onRim ? v * 2 + 2 : heads[v + 1];

                if (to === from) {
                    continue;
                }

                let x = 0;
                let y = 0;
                let z = 0;

                for (let k = from; k < to; k++) {
                    const n = (onRim ? rim[k] : near[k]) * 3;

                    x += positions[n];
                    y += positions[n + 1];
                    z += positions[n + 2];
                }

                const n = to - from;
                const at = v * 3;
                let toX = x / n;
                let toY = y / n;
                let toZ = z / n;

                if (onRim && grain) {
                    /*
                     * سهمِ خودِ الگو از این انحراف، سرِ جایش می‌ماند: رأس فقط تا
                     * آن‌جا به وترِ دو همسایه نزدیک می‌شود که فاصله‌اش همان
                     * فاصله‌ی روی الگوی تخت شود — و اگر همین حالا هم بیشتر
                     * نیست، اصلاً تکان نمی‌خورد.
                     */
                    const a = rim[v * 2] * 2;
                    const b = rim[v * 2 + 1] * 2;
                    const flat = Math.hypot(
                        grain[v * 2] - (grain[a] + grain[b]) / 2,
                        grain[v * 2 + 1] - (grain[a + 1] + grain[b + 1]) / 2,
                    );
                    const off = Math.hypot(toX - positions[at], toY - positions[at + 1], toZ - positions[at + 2]);
                    const keep = off > 1e-9 ? Math.min(1, flat / off) : 0;

                    toX = lerp(toX, positions[at], keep);
                    toY = lerp(toY, positions[at + 1], keep);
                    toZ = lerp(toZ, positions[at + 2], keep);
                }

                next[at] += (toX - positions[at]) * weight;
                next[at + 1] += (toY - positions[at + 1]) * weight;
                next[at + 2] += (toZ - positions[at + 2]) * weight;
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
    /* نما: پارچه | کشش | آزادی — ببینید setView */
    view: 'fabric',
    /* مانکن برداشته شده؟ */
    bare: false,

    async boot() {
        if (! this.payload || ! this.payload.ok) {
            this.failed = true;
            this.message = (this.payload && this.payload.notes && this.payload.notes[0])
                || 'این مدل روی مانکن نشانده نشد.';

            return;
        }

        try {
            THREE ??= await import('three');
            await this.loadAvatar();
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
            const [{ ClothWorld, Collider, clearanceAt }, { buildDrape, supportGarment, weldSeams }] = await Promise.all([
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

            const world = new ClothWorld({ fabric, floor: 0 });

            drape.patches.forEach((entry) => world.addPatch(entry.patch));
            drape.seams.forEach((seam) => world.addSeam(seam));

            world.substeps = drape.stats.solver.substeps;
            world.iterations = Math.max(6, drape.stats.solver.iterations);

            /*
             * ترتیبش مهم است، و همان ترتیبِ خیاطیِ واقعی است: اول لباس دوخته
             * می‌شود، بعد تنِ مانکن.
             *
             * پیش از این، قطعه‌ها روی بدن چیده می‌شدند و همان‌جا دوخته — یعنی
             * وقتی دو لبه می‌خواستند به هم برسند، بدن وسطِ راهشان بود. هر درزی
             * که آن‌جا بسته نمی‌شد، روی مانکن یک سوراخ می‌ماند: همان شکافِ
             * سرشانه و گودیِ گردن که در عکس‌ها دیده می‌شد.
             *
             * اندازه گرفته شد. بدترین شکافِ درز، وقتی بدن وسط نباشد:
             *
             *     پیراهن ۹٫۰ → ۱٫۸   کت ۸٫۵ → ۱٫۷   کت رسمی ۱۳٫۱ → ۱٫۶
             *     ترنچ‌کت ۱۱٫۱ → ۲٫۷   چیپائو ۳۵٫۴ → ۲٫۱
             *
             * پس الگو و دوخت سالم بودند؛ ایراد از این بود که در حالِ پوشیده
             * بودن می‌دوختیم.
             *
             * ولی لباسِ دوخته‌شدهٔ بی‌تن وا می‌رود — قدِ لباس عروس ۱۵۵ به ۱۱۵
             * سانتی‌متر می‌رسید — چون چیزی داخلش نیست. پس مثل خیاط که لباس را
             * روی مانکن می‌کشد، بدن از هیچ تا اندازهٔ خودش بزرگ می‌شود و پارچه
             * را از داخل باز می‌کند.
             */
            const gravity = world.law.gravity;
            const dress = (grow) => world.setColliders(bodyColliders(Collider, body, table, grow));

            world.law.gravity = 0;

            /*
             * ۱) دوخت، با بدنی که فقط نیم‌قدِ خودش است.
             *
             * اول بدن را به‌کل برداشتم و درزها عالی بسته شدند، ولی لباس شکلش را
             * از دست می‌داد: آستین تخت می‌خوابید و بعد که بازو بزرگ می‌شد، از
             * کنارِ آستین بیرون می‌زد به‌جای آنکه داخلش برود — همان سرشانهٔ لخت.
             * بدنِ کوچک هم جلوی بسته شدنِ درز را نمی‌گیرد و هم اندام را داخلِ
             * لباس نگه می‌دارد.
             */
            dress(SEWING_BODY);

            const before = middleOf(drape);
            const placed = heightsOf(drape);

            /*
             * دوختِ بی‌وزن، با لنگر: هر چهل گام لباس دوباره سرِ جای چیدنش
             * گذاشته می‌شود — نه پایین‌تر (جغجغهٔ درز+برخورد) نه بالاتر (باد
             * کردن). ببینید sewAnchored؛ بازنشانیِ یک‌بارهٔ قبلی سقفِ ۶
             * سانتی‌متری داشت و فرورفتنِ ۱۹ سانتی‌متریِ بلوزها را برنمی‌گرداند.
             */
            sewAnchored(world, drape, placed, table.level.armhole, Math.max(240, drape.stats.presettle));

            /*
             * ۲) لباسِ دوخته‌شده را برمی‌داریم و سرِ جایش می‌گذاریم.
             *
             * پارچه‌ی بی‌تن جمع می‌شود و مرکزش جابه‌جا: پیراهنِ راپ در همین یک
             * مرحله ده سانتی‌متر پایین می‌رفت و بعد بدن که بزرگ می‌شد، لباس از
             * سینه افتاده می‌ماند. جابه‌جاییِ صُلبِ کلِ لباس هیچ درزی را باز
             * نمی‌کند — همان برداشتنِ لباس از میز و انداختنش روی مانکن است.
             *
             * ولی وقتی بدن سرِ جایش بوده، لباس جایی نرفته که برگردانده شود — و
             * لنگرِ این جابه‌جایی «بالاترین نقطهٔ چیدن» است، که برای کت ۱۶۱٫۶
             * سانتی‌متر بود: کلِ لباس ۹٫۷ سانتی‌متر بالا کشیده می‌شد و از بالای
             * سر می‌زد بیرون. پس فقط وقتی که واقعاً بی‌بدن دوخته باشیم.
             */
            if (SEWING_BODY < 1) {
                shift(drape, before, middleOf(drape));
            }

            /*
             * ۳) تن کردن: بدن آرام‌آرام داخلِ لباس بزرگ می‌شود.
             *
             * و پارچه همان چند لحظه اجازهٔ کشش می‌گیرد. بی آن، سقفِ کشسانی سفت
             * است و ارزان‌ترین راهِ حل‌کننده کنار زدنِ پارچه بود نه کشیدنش —
             * بازو از حلقهٔ آستین بیرون می‌زد و سرشانه لخت می‌ماند. لباسِ تنگ
             * هم در واقعیت کش می‌آید و بدن داخلش می‌ماند.
             *
             * ولی وقتی بدن از همان اول کامل است، این مرحله کاری ندارد و فقط
             * ۲۲۸ گامِ بی‌وزنیِ اضافه می‌زند — و پارچه در بی‌وزنی *باد می‌کند*:
             * تنهٔ پیراهن ۱۴۳٫۵ به ۱۴۹٫۱ و آستین ۱۳۳ به ۱۴۵٫۴. یقهٔ هر سه کت
             * از بالای چانه می‌زد بیرون و کلِ لباس رد می‌شد. پس اگر بدنی برای
             * بزرگ کردن نیست، اصلاً اجرا نمی‌شود.
             */
            if (SEWING_BODY < 1) {
                world.allowStretch(DRESS_GIVE);

                for (let step = 1; step <= 12; step++) {
                    dress(SEWING_BODY + (1 - SEWING_BODY) * (step / 12));
                    world.presettle(14);
                }

                // و سقف به جای خودش برمی‌گردد؛ حالا پارچه روی بدنِ داخلش جمع می‌شود
                world.allowStretch(1);
                world.presettle(60);
            }

            /* ۳) حالا لباس پوشیده است؛ سرشانه گرفته می‌شود و وزن برمی‌گردد */
            supportGarment(drape, { band: 0.08, strength: 1 });
            drape.patches.forEach((entry) => entry.patch.applyPins(IDENTITY));

            world.law.gravity = gravity;
            world.presettle(40);
            world.iterations = Math.max(SETTLE_PASSES, drape.stats.solver.iterations);
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
            /*
             * از این‌جا به بعد پارچه خودش را هم می‌بیند، نه فقط بدن را.
             *
             * در دوختِ بی‌وزنی و نشستنِ اصلی عمداً خاموش است: قطعه‌ها باید از
             * روی هم رد شوند تا سرِ جایشان برسند، و اندازه گرفته شد که تماسِ
             * زودهنگام پوشش را باز می‌کند (جمعِ سوراخِ پنج مدل ۲۰۴۷ ⇒ ۳۹۳۷
             * پیکسل). وقتی لباس نشسته، دیگر لایه‌ها حق ندارند در هم فرو بروند —
             * برگردانِ یقه روی سینه می‌خوابد، نه در آن.
             */
            world.enableContact();
            world.presettle(150);
            // جوش، صاف‌کردن، بیرون‌راندنِ بدن با حلِ دوباره، جوشِ دوباره؛ ببینید finishGarment
            finishGarment(world, drape, weldSeams);

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

            this.paintFit(drape, world.colliders, clearanceAt);
            this.showStitched(drape);
            this.sewn = true;
        } catch (error) {
            // نمای چرخشی سرِ جایش می‌ماند؛ چیزی از دست نمی‌رود
            this.sewn = false;
        }

        this.sewing = false;
    },

    /**
     * حلقه‌های بدنِ آواتار، پیش از ساختنِ صحنه.
     *
     * باید *پیش از* build و sew برسد: دوخت از ctx.body می‌خواند و اگر آواتار
     * دیر برسد، لباس روی مانکنِ محاسباتی دوخته می‌شود و آواتار زیرش کشیده —
     * یک بار همین شد و نمای چرخشی به‌جای پارچهٔ دوخته دیده شد.
     */
    async loadAvatar() {
        const shell = this.payload;
        const wanted = new URLSearchParams(window.location.search).get('avatar');

        if (! wanted || shell.avatar !== undefined) {
            return;
        }

        const name = encodeURIComponent(wanted);
        const pattern = (window.location.pathname.match(/\/patterns\/(\d+)/) || [])[1];

        try {
            /*
             * اول آواتارِ به‌اندازهٔ همین الگو (سرور آن را برای اندازه‌های مشتری
             * می‌پزد و cache می‌کند)؛ اگر نبود، آواتارِ خام با اندازه‌های خودش.
             */
            if (pattern) {
                const fitted = await fetch(`/avatars/${name}/fit/${pattern}`, { headers: { Accept: 'application/json' } });
                const fit = fitted.ok ? await fitted.json() : null;

                if (fit && fit.ok && fit.body && fit.body.torso) {
                    shell.avatar = { ...fit.body, url: fit.url };

                    return;
                }
            }

            const response = await fetch(`/avatars/${name}/body.json`);
            const json = response.ok ? await response.json() : null;

            // posed.glb همان آواتار است با بازوی آویزان و پخته‌شده — همان تنی که body.json توصیف می‌کند
            shell.avatar = json ? { ...json, url: `/avatars/${name}/posed.glb` } : false;
        } catch (error) {
            shell.avatar = false;
        }
    },

    build() {
        const ctx = contextFor(this.$root);
        const canvas = this.$refs.canvas;
        const shell = this.payload;

        /*
         * مانکن از آواتارِ GLB، اگر خواسته شده باشد (‎?avatar=نام؛ ببینید loadAvatar).
         *
         * پروندهٔ body.json همان حلقه‌هایی است که buildBody می‌سازد — از
         * برش‌زدنِ مشِ ژست‌گرفتهٔ آواتار (tests/js/avatar-body.mjs) — پس
         * حل‌کننده، چیدن و برخوردگرها هیچ فرقی نمی‌بینند؛ فقط آنچه *کشیده*
         * می‌شود خودِ آواتار است، با همان ژستِ بازوها.
         */
        const avatar = shell.avatar && shell.avatar.torso ? shell.avatar : null;
        const body = avatar || buildBody(shell.measurements || {});
        ctx.body = body;

        // preserveDrawingBuffer تا دکمهٔ عکس بتواند همین قاب را بخواند
        const renderer = new THREE.WebGLRenderer({
            canvas,
            antialias: true,
            alpha: true,
            preserveDrawingBuffer: true,
        });
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

        ctx.camera = camera;
        /*
         * دوربینِ مداری، به‌جای زاویهٔ ثابت.
         *
         * تا امروز فقط یک دکمهٔ «چرخش» بود و کاربر نمی‌توانست جایی را که
         * می‌خواهد ببیند: خیاط برای دیدنِ حلقهٔ آستین باید از بالا نگاه کند و
         * برای درزِ پهلو از پهلو. حالا کشیدن می‌چرخاند، غلتک نزدیک و دور
         * می‌کند و کشیدن با دکمهٔ راست (یا دو انگشت) قاب را جابه‌جا می‌کند.
         *
         * حالتِ دوربین در `ctx.eye` نگه داشته می‌شود تا هم چرخشِ خودکار و هم
         * دستِ کاربر روی همان یک چیز کار کنند.
         */
        /*
         * قابِ نخست همان قابِ قدیمی است، عمداً و دقیقاً: دوربین در
         * ‎(۰٫۴۰،‏ −۰٫۴۵،‏ ۱٫۹)×قد و صحنه ۰٫۴۲ رادیان چرخیده. سنجهٔ بینایی با
         * همین قاب تنظیم شده و یک جابه‌جاییِ کوچکِ زاویه، عددهایش را تا دو
         * برابر تکان می‌دهد — یک بار همین اشتباه، سه اندازه‌گیری را بی‌اعتبار
         * کرد.
         */
        const start = {
            yaw: Math.atan2(0.40, 1.9),
            pitch: Math.asin(0.02 / 1.9433),
            far: tall * 1.9433,
            at: [0, -tall * 0.47, 0],
        };

        ctx.eye = { ...start, at: [...start.at], home: { ...start, at: [...start.at] } };
        this.aim();
        this.grip(canvas, tall);

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
        // چرخشِ آغازین و چرخشِ خودکار روی خودِ گروه‌اند، نه دوربین: نور ثابت
        // می‌ماند و مدل زیرش می‌گردد — همان قابی که سنجه با آن تنظیم شده.
        group.rotation.y = -0.42;
        scene.add(group);
        ctx.group = group;

        if (avatar) {
            this.addAvatar(group, avatar);
        } else {
            this.addBody(group, body);
        }

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

        // برای سنجه‌های مرورگری (Playwright): هندسهٔ دوخته‌شده در دسترس باشد
        window.__dokhtDrape = drape;

        /*
         * حل‌کننده در دستگاهِ خودش کار می‌کند — متر، y رو به بالا، صفر روی زمین —
         * و این صحنه از بالای سر رو به پایین می‌شمارد. یک جابه‌جاییِ ساده هر دو
         * را یکی می‌کند؛ آینه لازم نیست، پس جهتِ مثلث‌ها هم به‌هم نمی‌ریزد.
         */
        cloth.position.y = -ctx.body.height * CM;

        /*
         * جنسِ دومِ هر قطعه، برای نقشه‌های فیت.
         *
         * رنگِ رأس‌ها روی همان هندسه می‌نشیند، پس جابه‌جا کردنِ نما فقط یک
         * اشاره‌گر است و هیچ چیزی از نو ساخته نمی‌شود.
         */
        const painted = track(ctx, new THREE.MeshLambertMaterial({
            vertexColors: true,
            side: THREE.DoubleSide,
        }));

        ctx.painted = painted;
        ctx.pieces = [];

        for (const mesh of drape.meshes) {
            const geometry = track(ctx, new THREE.BufferGeometry());

            geometry.setAttribute('position', new THREE.BufferAttribute(mesh.positions, 3));
            geometry.setIndex(new THREE.BufferAttribute(mesh.indices, 1));

            if (mesh.strain) {
                geometry.setAttribute(
                    'color',
                    new THREE.BufferAttribute(new Float32Array(mesh.strain.length * 3), 3),
                );
            }

            geometry.computeVertexNormals();

            const piece = new THREE.Mesh(geometry, ctx.fabric);

            piece.castShadow = true;
            piece.receiveShadow = true;
            piece.userData.map = mesh;
            cloth.add(piece);
            ctx.pieces.push(piece);
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

    /**
     * مانکن از آواتارِ GLB: همان پرونده، با بازوهای آویزان.
     *
     * ژستِ استخوان‌ها از body.json می‌آید و دقیقاً همان چرخشی است که ابزارِ
     * برش‌زدن روی مش اعمال کرده؛ پس بدنی که کشیده می‌شود همان بدنی است که
     * پارچه به آن می‌خورد. دستگاهِ GLB متر و y رو به بالا با پا روی صفر است —
     * همان دستگاهِ حل‌کننده — پس همان جابه‌جایی‌ای که پارچه می‌گیرد، این هم
     * می‌گیرد (ببینید showStitched).
     */
    addAvatar(group, avatar) {
        const ctx = contextFor(this.$root);

        ctx.skin = track(ctx, new THREE.MeshStandardMaterial({ color: 0xcdc2b8, roughness: 0.96 }));
        ctx.figure = [];

        import('three/examples/jsm/loaders/GLTFLoader.js').then(({ GLTFLoader }) => {
            new GLTFLoader().load(avatar.url, (gltf) => {
                const root = gltf.scene;
                const pose = (avatar.avatar && avatar.avatar.pose) || {};
                const hide = new Set((avatar.avatar && avatar.avatar.hide) || []);
                const bakedPose = /(?:^|\\/)posed\\.glb(?:$|\\?)/i.test(String(avatar.url || ''));

                root.traverse((node) => {
                    // posed.glb ژست را از قبل داخل مش پخته است؛ دوباره‌کاری دست‌ها را بالای سر می‌برد.
                    if (! bakedPose && pose[node.name]) {
                        node.quaternion.set(...pose[node.name]);
                    }

                    if (hide.has(node.name)) {
                        node.visible = false;
                    }

                    if (node.isMesh) {
                        node.material = ctx.skin;
                        node.castShadow = true;
                        node.receiveShadow = true;
                        // مشِ پوست‌دار پس از چرخشِ بازو از جعبهٔ اولیه‌اش بیرون می‌زند
                        node.frustumCulled = false;
                        ctx.figure.push(node);
                    }
                });

                /*
                 * دستگاهِ فایل Mixamo برعکسِ دستگاهِ حل‌کننده است: سر روی y=0
                 * و کف پا روی y=قد قرار دارد. آن را حول z برمی‌گردانیم و به
                 * اندازهٔ قد بالا می‌بریم تا کف روی صفر و سر روی قد بنشیند.
                 */
                root.rotation.z = Math.PI;
                root.position.y = Number(avatar.top || avatar.height * CM || 0);
                group.add(root);
                ctx.avatar = root;
                window.__dokhtAvatar = root;
            });
        }).catch(() => {
            // اگر آواتار بار نشد، مانکنِ محاسباتی کشیده می‌شود
            this.addBody(group, avatar);
        });
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

        // فهرستِ مش‌های بدن، تا بشود مانکن را برداشت و درزهای پشت را دید
        ctx.figure = [];

        const add = (geometry) => {
            if (! geometry) {
                return;
            }

            track(ctx, geometry);
            const mesh = new THREE.Mesh(geometry, skin);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            group.add(mesh);
            ctx.figure.push(mesh);
        };

        add(tube(body.torso));

        // سر: بیضی‌گون، نه کره — کرهٔ کامل شبیه توپ می‌شود
        const head = track(ctx, new THREE.SphereGeometry(body.head.radius * CM, 32, 24));
        const headMesh = new THREE.Mesh(head, skin);
        headMesh.scale.set(0.82, 1.12, 0.9);
        headMesh.position.y = -body.head.centre * CM;
        headMesh.castShadow = true;
        group.add(headMesh);
        ctx.figure.push(headMesh);

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

    /**
     * نما را عوض می‌کند: پارچه، نقشهٔ کشش، یا نقشهٔ آزادی.
     *
     * `پارچه` همان جنسِ انتخاب‌شده است. دو نقشهٔ دیگر رنگِ رأس‌ها را از عددهای
     * `paintFit` می‌سازند:
     *
     *   کشش   آبی = چین خورده (زیر ۱)، خاکستری = درست، قرمز = کشیده. مرز روی
     *          ±۱۰٪ است، چون پارچهٔ بافته تا همان حد بی‌درد کش می‌آید.
     *   آزادی قرمز = چسبیده به تن (زیر یک سانتی‌متر)، سبز = دو تا شش، آبی =
     *          گشاد. همان مقیاسی که خیاط با انگشت می‌سنجد.
     */
    setView(mode) {
        const ctx = contextFor(this.$root);

        this.view = mode;

        if (! ctx.pieces) {
            return;
        }

        for (const piece of ctx.pieces) {
            const mesh = piece.userData.map;
            const values = mode === 'strain' ? mesh?.strain : mode === 'ease' ? mesh?.ease : null;
            const colours = piece.geometry.getAttribute('color');

            if (! values || ! colours) {
                piece.material = ctx.fabric;

                continue;
            }

            for (let v = 0; v < values.length; v++) {
                const [r, g, b] = mode === 'strain' ? strainColour(values[v]) : easeColour(values[v]);

                colours.setXYZ(v, r, g, b);
            }

            colours.needsUpdate = true;
            piece.material = ctx.painted;
        }
    },

    /** برداشتنِ مانکن از صحنه، برای دیدنِ درزهای پشت. */
    toggleBody() {
        const ctx = contextFor(this.$root);

        this.bare = ! this.bare;

        (ctx.figure || []).forEach((item) => {
            item.visible = ! this.bare;
        });
    },

    /** ذخیرهٔ همین قاب به‌صورت تصویر. */
    snapshot() {
        const ctx = contextFor(this.$root);

        if (! ctx.renderer) {
            return;
        }

        ctx.renderer.render(ctx.scene, ctx.camera);

        const link = document.createElement('a');

        link.href = ctx.renderer.domElement.toDataURL('image/png');
        link.download = 'دوخت-سه-بعدی.png';
        link.click();
    },

    /**
     * نقشهٔ کشش و نقشهٔ آزادی — همان چیزی که خیاط با دست روی تن می‌سنجد.
     *
     * دو عدد برای هر رأس، هر دو از خودِ حل‌کننده و بی هیچ حدسی:
     *
     *   کشش   نسبتِ طولِ یال‌های آن رأس به طولشان روی الگوی تخت. یک یعنی
     *          پارچه همان‌قدر است که بریده شده؛ بیشتر یعنی کشیده و زیرِ فشار،
     *          کمتر یعنی چین خورده.
     *   آزادی فاصلهٔ رأس تا نزدیک‌ترین پوست. صفر یعنی چسبیده به تن.
     *
     * رنگ‌ها روی همان بافرِ مش می‌نشینند تا عوض کردنِ نما فقط جنسِ متریال را
     * جابه‌جا کند و هیچ چیزی از نو ساخته نشود.
     */
    paintFit(drape, colliders, clearanceAt) {
        for (const entry of drape.patches) {
            const mesh = entry.mesh;

            if (! mesh) {
                continue;
            }

            const patch = entry.patch;
            const count = patch.count;
            const sum = new Float64Array(count);
            const seen = new Uint16Array(count);

            for (const group of patch.groups) {
                for (let i = 0; i < group.rest.length; i++) {
                    const a = group.a[i];
                    const b = group.b[i];
                    const rest = group.rest[i];

                    if (rest < 1e-6) {
                        continue;
                    }

                    const pa = a * 3;
                    const pb = b * 3;
                    const now = Math.hypot(
                        patch.positions[pa] - patch.positions[pb],
                        patch.positions[pa + 1] - patch.positions[pb + 1],
                        patch.positions[pa + 2] - patch.positions[pb + 2],
                    ) / rest;

                    sum[a] += now;
                    sum[b] += now;
                    seen[a]++;
                    seen[b]++;
                }
            }

            const strain = new Float32Array(count);
            const ease = new Float32Array(count);

            for (let v = 0; v < count; v++) {
                strain[v] = seen[v] ? sum[v] / seen[v] : 1;
                ease[v] = clearanceAt(
                    colliders,
                    patch.positions[v * 3],
                    patch.positions[v * 3 + 1],
                    patch.positions[v * 3 + 2],
                );
            }

            mesh.strain = strain;
            mesh.ease = ease;
        }
    },

    /** دوربین را از حالتِ `ctx.eye` سرِ جایش می‌گذارد. */
    aim() {
        const ctx = contextFor(this.$root);
        const eye = ctx.eye;

        if (! eye || ! ctx.camera) {
            return;
        }

        const [ax, ay, az] = eye.at;
        const cos = Math.cos(eye.pitch);

        ctx.camera.position.set(
            ax + eye.far * cos * Math.sin(eye.yaw),
            ay + eye.far * Math.sin(eye.pitch),
            az + eye.far * cos * Math.cos(eye.yaw),
        );
        ctx.camera.lookAt(ax, ay, az);
    },

    /**
     * دست گرفتنِ صحنه: کشیدن می‌چرخاند، غلتک نزدیک می‌کند، کشیدنِ راست جابه‌جا.
     *
     * دو انگشت هم همین کار را می‌کند: فاصله‌شان نزدیک/دور و میانه‌شان جابه‌جا.
     * چرخشِ خودکار با اولین لمس می‌ایستد، وگرنه صحنه زیرِ دستِ کاربر می‌چرخد.
     */
    grip(canvas, tall) {
        const ctx = contextFor(this.$root);
        const touches = new Map();
        let last = null;
        let pinch = 0;

        const middle = () => {
            const points = [...touches.values()];

            return [
                points.reduce((sum, p) => sum + p.x, 0) / points.length,
                points.reduce((sum, p) => sum + p.y, 0) / points.length,
            ];
        };
        const spread = () => {
            const points = [...touches.values()];

            return points.length < 2 ? 0 : Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
        };
        const zoom = (by) => {
            ctx.eye.far = clamp(ctx.eye.far * by, tall * 0.35, tall * 6);
            this.aim();
        };

        canvas.style.touchAction = 'none';
        canvas.style.cursor = 'grab';

        canvas.addEventListener('pointerdown', (event) => {
            canvas.setPointerCapture(event.pointerId);
            touches.set(event.pointerId, { x: event.clientX, y: event.clientY });
            last = middle();
            pinch = spread();
            this.spin = false;
            canvas.style.cursor = 'grabbing';
        });

        canvas.addEventListener('pointermove', (event) => {
            if (! touches.has(event.pointerId)) {
                return;
            }

            touches.set(event.pointerId, { x: event.clientX, y: event.clientY });

            const now = middle();
            const dx = now[0] - last[0];
            const dy = now[1] - last[1];

            last = now;

            // دو انگشت یا دکمهٔ راست: جابه‌جاییِ قاب، نه چرخش
            if (touches.size > 1 || event.buttons === 2 || event.shiftKey) {
                const scale = ctx.eye.far * 0.0016;

                ctx.eye.at[0] -= dx * scale * Math.cos(ctx.eye.yaw);
                ctx.eye.at[2] += dx * scale * Math.sin(ctx.eye.yaw);
                ctx.eye.at[1] += dy * scale;
            } else {
                ctx.eye.yaw -= dx * 0.008;
                ctx.eye.pitch = clamp(ctx.eye.pitch + dy * 0.006, -1.2, 1.2);
            }

            if (touches.size > 1) {
                const wide = spread();

                if (pinch > 4 && wide > 4) {
                    zoom(pinch / wide);
                }

                pinch = wide;
            }

            this.aim();
        });

        const release = (event) => {
            touches.delete(event.pointerId);
            last = touches.size ? middle() : null;
            pinch = spread();
            canvas.style.cursor = 'grab';
        };

        canvas.addEventListener('pointerup', release);
        canvas.addEventListener('pointercancel', release);
        canvas.addEventListener('contextmenu', (event) => event.preventDefault());
        canvas.addEventListener('wheel', (event) => {
            event.preventDefault();
            zoom(event.deltaY > 0 ? 1.12 : 1 / 1.12);
        }, { passive: false });
    },

    /**
     * برگرداندنِ دوربین (و چرخشِ خودکارِ صحنه) به قابِ نخست.
     *
     * چرخشِ صحنه هم صفر می‌شود، و این فقط سلیقه نیست: سنجهٔ بینایی پیش از
     * عکس همین را صدا می‌زند تا قاب *قطعی* باشد. پیش از این، زاویهٔ عکس
     * ۰٫۴۲ رادیان ضرب در هر ثانیه‌ای بود که دوخت طول کشیده بود — یعنی هر
     * تغییری در سرعتِ دوخت، عددهای سنجه را جابه‌جا می‌کرد بی‌آنکه لباس عوض
     * شده باشد. سه اندازه‌گیری با همین دام بی‌اعتبار شد.
     */
    recentre() {
        const ctx = contextFor(this.$root);

        if (! ctx.eye) {
            return;
        }

        ctx.eye.yaw = ctx.eye.home.yaw;
        ctx.eye.pitch = ctx.eye.home.pitch;
        ctx.eye.far = ctx.eye.home.far;
        ctx.eye.at = [...ctx.eye.home.at];

        if (ctx.group) {
            ctx.group.rotation.y = -0.42;
        }

        this.aim();
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
