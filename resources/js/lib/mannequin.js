/*
 * مانکن پارامتری، از روی اندازه‌های همان شخص.
 *
 * نکتهٔ اصلی این فایل یک چیز است: بدن آدم قرینه نیست. اگر هر تراز را یک بیضیِ
 * ساده بگیریم، لوله‌ای درمی‌آید که نه سینه دارد نه باسن نه شکم — و لباسی هم که
 * رویش بنشیند همان لوله را نشان می‌دهد. پس هر تراز دو عمق دارد: جلو و پشت،
 * جدا از هم. برجستگی سینه به عمقِ جلو اضافه می‌شود و برجستگی باسن به عمقِ پشت.
 *
 * هیچ‌جا «مرد» یا «زن» یا «کودک» به‌عنوان کلید انتخاب نمی‌شود. همه‌چیز از خودِ
 * اندازه‌ها درمی‌آید، چون اندازه‌ها همین را می‌گویند:
 *
 *   — اختلاف دور سینه با زیرسینه یعنی برجستگی سینه. صفر باشد، قفسهٔ سینه صاف
 *     است (مردانه یا کودکانه)؛ زیاد باشد، سینه جلو می‌آید.
 *   — اختلاف باسن با کمر یعنی برجستگی باسن.
 *   — اختلاف کمر با زیرسینه، اگر مثبت باشد، یعنی شکم جلو آمده.
 *   — قد کوتاه با دورهای نزدیک به هم یعنی کودک: سرش نسبت به قدش بزرگ‌تر است
 *     (یک‌ششم به‌جای یک‌هشتم) و دست و پایش کوتاه‌تر.
 *
 * پس یک مرد، یک زن و یک کودک سه بدنِ متفاوت می‌شوند بی‌آنکه جایی نامشان برود.
 *
 * همه‌چیز به سانتی‌متر است و y از بالای سر به پایین می‌رود، مثل خودِ الگو.
 */

/** محیط بیضی — تقریب رامانوجان. */
export const perimeter = (a, b) => Math.PI * (3 * (a + b) - Math.sqrt((3 * a + b) * (a + 3 * b)));

/** نیم‌قطر کوچکِ بیضی با محیط و نسبتِ داده‌شده. */
export const minorAxis = (girth, ratio) => {
    if (girth <= 0 || ratio < 1) {
        return 0;
    }

    const unit = perimeter(ratio, 1);

    return unit <= 0 ? 0 : girth / unit;
};

const clamp = (value, low, high) => Math.max(low, Math.min(high, value));

const lerp = (a, b, t) => a + (b - a) * t;

/* تنه پهن‌تر است از ضخیم */
const TORSO = 1.45;

/**
 * یک تراز از بدن.
 *
 * rx نیم‌پهنا، و front/back عمقِ جلو و پشت — که برابر نیستند. محیطِ حاصل همان
 * دورِ اندازه‌گرفته‌شده می‌ماند، چون عمقِ پایه از همان دور درآمده و برجستگی‌ها
 * فقط جابه‌جایش می‌کنند: هرچه به جلو اضافه شود، اگر لازم باشد از پشت کم می‌شود.
 */
const level = (y, girth, options = {}) => {
    const ratio = options.ratio || TORSO;
    const base = minorAxis(girth, ratio);

    /*
     * برجستگی مقدارِ اضافه نیست، جابه‌جایی است.
     *
     * دورِ سینه همان دورِ سینه است — از روی نوکِ سینه اندازه گرفته شده. اگر
     * برجستگی را همین‌طور به عمق اضافه کنیم، مانکن از خودِ مشتری چاق‌تر
     * درمی‌آید (یک بار باسنِ ۱۰۰ روی مانکن ۱۰۴ شد) و آن‌وقت هر دامنِ تنگی از
     * تنش رد می‌شود. پس نصفِ اختلاف به یک طرف می‌رود و همان نصف از طرف دیگر
     * کم می‌شود: مجموع دست‌نخورده می‌ماند، شکل عوض می‌شود.
     */
    const shift = ((options.front || 0) - (options.back || 0)) / 2;

    return {
        y,
        rx: base * ratio * (options.wide || 1),
        front: base + shift,
        back: base - shift,
    };
};

/**
 * بدنِ کامل: ترازها، تنه، دست و پا — همه از اندازه‌های همان شخص.
 */
export const buildBody = (m = {}) => {
    const height = m.height || 168;
    const bust = m.bust || 92;
    const waist = m.waist || 74;
    const hip = m.hip || 98;
    const underBust = m.under_bust || bust - 14;
    const highHip = m.high_hip || (waist + hip) / 2;
    const neckGirth = m.neck || bust * 0.4;
    const shoulderWidth = m.shoulder_width || height * 0.232;
    const backLength = m.back_length || height * 0.245;
    const waistToHip = m.waist_to_hip || height * 0.125;
    const inseam = m.inseam || height * 0.45;
    const armLength = m.arm_length || height * 0.345;
    const bicep = m.bicep || bust * 0.33;
    const elbow = m.elbow || bicep * 0.9;
    const wrist = m.wrist || bicep * 0.62;
    const thigh = m.thigh || hip * 0.58;
    const knee = m.knee || thigh * 0.66;
    const ankle = m.ankle || knee * 0.62;

    /*
     * اندام از روی خودِ اندازه‌ها.
     *
     * cup: اختلاف سینه و زیرسینه. تا چهار سانت یعنی قفسهٔ صاف؛ بیشتر که شد،
     * سینه به جلو می‌آید و مقدارش تقریباً یک‌ششم همان اختلاف است.
     */
    const cup = Math.max(0, bust - underBust);
    const bustLift = cup <= 4 ? 0 : (cup - 4) / 6;

    // باسن: اختلافش با کمر، بیشترش به پشت می‌رود
    const seat = Math.max(0, hip - waist);
    const seatLift = seat / 9;

    // شکم: اگر کمر از زیرسینه بزرگ‌تر باشد، همان اختلاف به جلو می‌آید
    const bellyLift = Math.max(0, waist - underBust) / 7;

    /*
     * کودکانگی: قد کوتاه و دورهای نزدیک به هم. عددش پیوسته است، نه بله/خیر،
     * پس نوجوان هم جایی میان کودک و بزرگسال می‌افتد.
     */
    const childish = clamp((150 - height) / 55, 0, 1) * clamp(1 - (Math.abs(hip - bust) / 18), 0, 1);

    // سرِ کودک نسبت به قدش بزرگ‌تر است
    const headHeight = height * lerp(0.128, 0.165, childish);
    const neckY = headHeight * 1.14;
    const shoulderY = neckY + height * lerp(0.038, 0.03, childish);

    /*
     * ترازها از *مهرهٔ گردن* شمرده می‌شوند، نه از نوکِ شانه.
     *
     * «قد بالاتنهٔ پشت» از همان مهره تا کمر اندازه گرفته می‌شود. یک بار از
     * سرشانه شمردمش و کلِ تنه شش سانت پایین افتاد — کمر زیر ناف درآمد و پای
     * مانکن از قدِ مشتری بلندتر شد. حالا کمر سرِ جای خودش می‌نشیند و بقیه هم
     * با آن جور درمی‌آید.
     */
    const gap = height * 0.022;
    const bustY = Math.max(neckY + backLength * 0.58, shoulderY + gap);
    const underBustY = Math.max(neckY + backLength * 0.75, bustY + gap);
    const waistY = Math.max(neckY + backLength, underBustY + gap);
    const highHipY = waistY + waistToHip * 0.45;
    const hipY = waistY + waistToHip;
    const crotchY = hipY + height * 0.045;

    /*
     * فاصلهٔ فاق تا کف همان «قد داخل پا» است، پس مچ کمی بالاترش می‌ایستد. اگر
     * اندازه‌ها با هم نخوانند، قدِ گفته‌شده حرفِ آخر را می‌زند — مانکن نباید از
     * خودِ مشتری بلندتر شود.
     */
    const ankleY = Math.min(crotchY + inseam * 0.95, height * 0.985);

    const neckRing = level(neckY, neckGirth, { ratio: 1.12 });
    const bustRing = level(bustY, bust, { front: bustLift, back: 0 });
    const shoulderDepth = (bustRing.front + bustRing.back) / 2 * 0.88;

    /*
     * تنه از گردن تا فاق.
     *
     * سرشانه شیب دارد و پله نیست: دو تراز میانی میان گردن و نوک شانه هست،
     * وگرنه روی شانه یک تختهٔ افقی می‌ماند و لباس هم همان را می‌گیرد.
     */
    const torso = [
        /*
         * ستونِ گردن از داخلِ چانه بیرون می‌آید. اگر تنه را از خودِ حلقهٔ گردن
         * شروع کنیم، میان سر و شانه یک شکافِ خالی می‌ماند و مانکن دو تکه
         * دیده می‌شود.
         */
        {
            y: headHeight * 0.9,
            rx: neckRing.rx * 0.9,
            front: neckRing.front * 0.9,
            back: neckRing.back * 0.9,
        },
        neckRing,
        {
            y: lerp(neckY, shoulderY, 0.4),
            rx: lerp(neckRing.rx, shoulderWidth / 2, 0.32),
            front: lerp(neckRing.front, shoulderDepth, 0.5),
            back: lerp(neckRing.back, shoulderDepth, 0.5),
        },
        {
            y: lerp(neckY, shoulderY, 0.76),
            rx: shoulderWidth / 2 * 0.9,
            front: shoulderDepth * 0.96,
            back: shoulderDepth * 0.96,
        },
        { y: shoulderY, rx: shoulderWidth / 2, front: shoulderDepth, back: shoulderDepth * 1.04 },
        // میان سرشانه و سینه، قفسه باز می‌شود
        {
            y: lerp(shoulderY, bustY, 0.55),
            rx: lerp(shoulderWidth / 2, bustRing.rx, 0.7),
            front: lerp(shoulderDepth, bustRing.front, 0.75),
            back: lerp(shoulderDepth * 1.04, bustRing.back, 0.75),
        },
        bustRing,
        level(underBustY, underBust, { front: bustLift * 0.25 }),
        level(waistY, waist, { front: bellyLift }),
        level(highHipY, highHip, { front: bellyLift * 0.5, back: seatLift * 0.7 }),
        level(hipY, hip, { back: seatLift }),
        level(crotchY, hip * 0.96, { back: seatLift * 0.45 }),
    ];

    /*
     * دست: سرشانه، بازو، آرنج، ساعد، مچ. باریک‌شدنش یکنواخت نیست — بازو
     * کلفت است، آرنج باریک، ساعد دوباره کمی پر و مچ باریک‌ترین.
     */
    const arm = [
        { y: 0, r: minorAxis(bicep * 1.1, 1) },
        { y: armLength * 0.16, r: minorAxis(bicep, 1) },
        { y: armLength * 0.46, r: minorAxis(elbow, 1) },
        { y: armLength * 0.62, r: minorAxis(elbow * 1.02, 1) },
        { y: armLength * 0.88, r: minorAxis(wrist * 1.12, 1) },
        { y: armLength, r: minorAxis(wrist, 1) },
        // دست: کوتاه و پهن‌تر از مچ
        { y: armLength * 1.1, r: minorAxis(wrist * 1.15, 1) },
        { y: armLength * 1.16, r: minorAxis(wrist * 0.6, 1) },
    ];

    /*
     * پا: ران، زانو، ساق (پرتر از زانو) و مچ پا.
     *
     * دو پا دو استوانهٔ موازی نیستند: بالا ران‌ها به هم می‌رسند و پایین مچ‌ها از
     * هم فاصله دارند، پس هر تراز مرکزِ خودش را دارد. با فاصلهٔ ثابت، آدم پرانتزی
     * درمی‌آید و شلوار هم مثل دو لولهٔ آویزان دیده می‌شود.
     */
    const legSpan = ankleY - crotchY;
    const thighR = minorAxis(thigh, 1);
    const ankleR = minorAxis(ankle, 1);
    const stance = Math.max(ankleR * 1.6, height * 0.035);
    const legX = (t) => lerp(thighR * 1.02, stance, t);

    const leg = [
        { y: 0, r: thighR, x: legX(0) },
        { y: legSpan * 0.22, r: minorAxis(thigh * 0.86, 1), x: legX(0.22) },
        { y: legSpan * 0.5, r: minorAxis(knee, 1), x: legX(0.5) },
        { y: legSpan * 0.64, r: minorAxis(knee * 1.12, 1), x: legX(0.64) },
        { y: legSpan * 0.88, r: minorAxis(ankle * 1.15, 1), x: legX(0.88) },
        { y: legSpan, r: ankleR, x: legX(1) },
    ];

    return {
        height,
        childish,
        level: {
            neck: neckY, shoulder: shoulderY, bust: bustY, underBust: underBustY,
            waist: waistY, highHip: highHipY, hip: hipY, crotch: crotchY, ankle: ankleY,
        },
        head: {
            radius: headHeight / 2,
            centre: headHeight * 0.52,
            // چانه تا گردن؛ گردنِ کودک کوتاه‌تر است
            neckTop: headHeight * 0.92,
        },
        shoulderRing: torso[4],
        neckRadius: (neckRing.front + neckRing.back) / 2,
        shoulderHalf: shoulderWidth / 2,
        armLength,
        torso,
        arm,
        leg,
    };
};

/*
 * دستِ آویزان: از کجا شروع می‌شود و چقدر از بدن فاصله می‌گیرد.
 *
 * این‌ها شکلِ بدن‌اند، نه شکلِ نمایش، پس همین‌جا می‌مانند تا هم نمای مانکن و هم
 * حل‌کنندهٔ پارچه از یک عدد بخوانند. وقتی هرکدام برای خودش حساب می‌کرد، آستین
 * روی بازو نمی‌نشست و کسی هم نمی‌فهمید کدام‌شان درست است.
 */
export const ARM_TILT = 0.085;

/**
 * مفصلِ شانه: مرکز و ارتفاعِ سرِ بازو.
 *
 * عرض سرشانه تا نوکِ شانه اندازه گرفته می‌شود و بازو از همان‌جا به بیرون
 * می‌افتد؛ برای همین آدم از روی بازوهایش پهن‌تر است تا از روی سرشانه‌اش.
 */
export const armJoint = (body) => ({
    x: body.shoulderRing.rx - body.arm[0].r * 0.35,
    y: body.shoulderRing.y + body.arm[0].r * 0.5,
});

/** مرکزِ دست در فاصلهٔ داده‌شده از مفصل. */
export const armCentre = (body, along) => armJoint(body).x + Math.max(0, along) * ARM_TILT;

/** تراز بدن در ارتفاع دلخواه، با میان‌یابی. */
export const sampleRing = (rings, y) => {
    if (rings.length === 0) {
        return { y, rx: 0, front: 0, back: 0 };
    }

    if (y <= rings[0].y) {
        return rings[0];
    }

    for (let i = 1; i < rings.length; i++) {
        if (y <= rings[i].y) {
            const span = rings[i].y - rings[i - 1].y || 1;
            const t = (y - rings[i - 1].y) / span;

            return {
                y,
                rx: lerp(rings[i - 1].rx, rings[i].rx, t),
                front: lerp(rings[i - 1].front, rings[i].front, t),
                back: lerp(rings[i - 1].back, rings[i].back, t),
            };
        }
    }

    return rings[rings.length - 1];
};

/** دورِ یک تراز (میانگین دو نیم‌بیضی). */
export const girthOf = (ring) => (
    perimeter(ring.rx, ring.front) + perimeter(ring.rx, ring.back)
) / 2;

/* ---------------------------------------------------------------------------
 * همین بدن، به زبانِ حل‌کنندهٔ پارچه
 * ---------------------------------------------------------------------------
 *
 * حل‌کننده و چیدنِ قطعه‌ها دستگاهِ خودشان را دارند: متر، y رو به *بالا* و صفر روی
 * زمین. این‌جا سانتی‌متر است و y از بالای سر رو به پایین، مثل خودِ الگو. پس یک
 * مترجم لازم است — و فقط یکی، وگرنه دو بدنِ متفاوت می‌شود و آستین روی بازویی
 * می‌نشیند که آن‌جا نیست.
 *
 * چیدنِ قطعه‌ها از سرور «نامِ تراز» و «نامِ شعاع» می‌گیرد، نه عدد؛ پس هر بدنی که
 * این نام‌ها را داشته باشد کار می‌کند و لباس روی اندامِ همین مشتری چیده می‌شود.
 */

/* از سانتی‌مترِ رو به پایین، به مترِ رو به بالا */
const upright = (body, y) => (body.height - y) / 100;

/** شعاعِ معادلِ یک تراز: دوری که آن تراز دارد، تقسیم بر دو پی. */
const ringRadius = (body, y) => girthOf(sampleRing(body.torso, y)) / (2 * Math.PI) / 100;

/**
 * جدول‌هایی که buildDrape و برخوردگرها می‌خواهند.
 *
 * @param {object} body خروجی buildBody
 */
export const drapeBody = (body) => {
    const level = body.level;
    const knee = level.crotch + body.leg[2].y;

    /*
     * حلقهٔ آستین میانِ سرشانه و سینه است. جای دقیقش را کسی اندازه نمی‌گیرد،
     * ولی چیدنِ آستین به آن نیاز دارد: نزدیک به نیمهٔ راه، کمی بالاتر.
     */
    const armhole = lerp(level.shoulder, level.bust, 0.47);

    const at = (y) => upright(body, y);

    /* از پایین به بالا، همان ترتیبی که برخوردگر می‌خواهد */
    const ankleR = body.leg[body.leg.length - 1].r / 100;
    const kneeR = body.leg[2].r / 100;
    const legOut = body.leg[body.leg.length - 1].x / 100;

    /*
     * هر سطر پنج عدد دارد: ارتفاع، نیم‌پهنا، نیم‌عمقِ میانگین، و بعد جلو و پشتِ
     * جدا. سه‌تای اول همان قراردادِ قدیمی است، پس هر چیزی که فقط [y, rx, rz]
     * می‌خواند بی‌تغییر کار می‌کند؛ دو تای آخر برای برخوردگر است تا سینه و شکم و
     * باسن از زیرِ پارچه پیدا باشند.
     */
    const profile = [
        [at(level.ankle), legOut + ankleR * 1.25, ankleR * 1.6, ankleR * 1.6, ankleR * 1.6],
        [at(knee), body.leg[2].x / 100 + kneeR, kneeR * 1.6, kneeR * 1.6, kneeR * 1.6],
    ];

    // تنه از فاق به بالا، از خودِ حلقه‌های مانکن
    for (let i = body.torso.length - 1; i >= 0; i--) {
        const ring = body.torso[i];
        const mean = (ring.front + ring.back) / 2 / 100;

        profile.push([at(ring.y), ring.rx / 100, mean, ring.front / 100, ring.back / 100]);
    }

    /* دست: صفر روی مفصل، منفی رو به پایین */
    const armTable = [];

    for (let i = body.arm.length - 1; i >= 0; i--) {
        armTable.push([-body.arm[i].y / 100, body.arm[i].r / 100]);
    }

    return {
        level: {
            ankle: at(level.ankle),
            knee: at(knee),
            crotch: at(level.crotch),
            hip: at(level.hip),
            highHip: at(level.highHip),
            waist: at(level.waist),
            underBust: at(level.underBust),
            bust: at(level.bust),
            armhole: at(armhole),
            shoulder: at(level.shoulder),
            neck: at(level.neck),
            chin: at(body.head.neckTop),
            top: body.height / 100,
        },
        radii: {
            hip: ringRadius(body, level.hip),
            highHip: ringRadius(body, level.highHip),
            waist: ringRadius(body, level.waist),
            underBust: ringRadius(body, level.underBust),
            bust: ringRadius(body, level.bust),
            neck: ringRadius(body, level.neck),
            /*
             * «شعاعِ حلقهٔ آستین» دورِ خودِ حلقه است، نه پهنای تنه در آن ارتفاع —
             * قراردادِ چیدنِ قطعه‌ها همین است و اگر پهنای تنه بدهیم، آستین دو
             * برابر گشاد چیده می‌شود.
             */
            armhole: girthOf(sampleRing(body.torso, level.bust)) * 0.46 / (2 * Math.PI) / 100,
            bicep: body.arm[1].r / 100,
            wrist: body.arm[5].r / 100,
            thigh: body.leg[0].r / 100,
            knee: kneeR,
            ankle: ankleR,
            shoulder: body.shoulderHalf / 100,
        },
        profile,
        armTable,
        armLength: body.armLength / 100,
        armOffset: armJoint(body).x / 100,
        armTilt: ARM_TILT,
        // ارتفاعِ مفصل، برای چیدنِ آستین و ساختِ برخوردگرِ بازو
        armTop: at(armJoint(body).y),
    };
};
