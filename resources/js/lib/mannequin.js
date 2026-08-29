/*
 * مانکن پارامتری.
 *
 * از اندازه‌های همان مشتری ساخته می‌شود و هیچ عددِ ثابتی در آن نیست جز نسبت‌های
 * تشریحی که برای همهٔ آدم‌ها تقریباً یکی است (مثلاً اینکه سرِ آدم حدود یک‌هشتم
 * قدش است، یا مچ از آرنج باریک‌تر).
 *
 * چرا جدا از کامپوننت: هم مانکنِ نمای «روی مانکن» از این می‌آید و هم می‌شود
 * جای دیگری از آن استفاده کرد؛ و مهم‌تر، شکل بدن جای خودش را دارد و قاطی
 * منطقِ لباس نمی‌شود.
 *
 * همه‌چیز به سانتی‌متر است و y از بالای سر به پایین می‌رود (مثل خودِ الگو).
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

/* تنه پهن‌تر است از ضخیم؛ همان نسبتی که سرور هم برای لباس به کار می‌برد */
const TORSO = 1.45;

/**
 * ترازها و شعاع‌های بدن.
 *
 * خروجی یک شیء است با ارتفاع هر تراز و حلقه‌های تنه و دست و پا — هرچه برای
 * ساختنِ مانکن و نشاندنِ لباس روی آن لازم است.
 */
export const buildBody = (m = {}) => {
    const height = m.height || 168;
    const bust = m.bust || 92;
    const waist = m.waist || 74;
    const hip = m.hip || 98;
    const underBust = m.under_bust || bust - 14;
    const neckGirth = m.neck || bust * 0.4;
    const shoulderWidth = m.shoulder_width || height * 0.232;
    const backLength = m.back_length || height * 0.245;
    const waistToHip = m.waist_to_hip || height * 0.125;
    const inseam = m.inseam || height * 0.45;
    const armLength = m.arm_length || height * 0.345;
    const bicep = m.bicep || bust * 0.33;
    const wrist = m.wrist || bicep * 0.62;
    const thigh = m.thigh || hip * 0.58;
    const knee = m.knee || thigh * 0.66;
    const ankle = m.ankle || knee * 0.62;

    /*
     * ترازها از بالای *سر* شمرده می‌شوند تا مانکن یک آدم کامل باشد. قد سر حدود
     * یک‌هشتم قد کل است و گردن کمی زیرش.
     */
    const headHeight = height * 0.125;
    const chin = headHeight;
    const neckY = headHeight * 1.15;
    const shoulderY = neckY + height * 0.038;
    const bustY = shoulderY + backLength * 0.52;
    const underBustY = shoulderY + backLength * 0.72;
    const waistY = shoulderY + backLength;
    const highHipY = waistY + waistToHip * 0.45;
    const hipY = waistY + waistToHip;
    const crotchY = hipY + height * 0.045;
    const kneeY = crotchY + inseam * 0.5;
    const ankleY = crotchY + inseam;

    const ring = (y, girth, ratio = TORSO) => {
        const rz = minorAxis(girth, ratio);

        return { y, rx: rz * ratio, rz };
    };

    // تنه: از گردن تا فاق. سرشانه پهن است ولی کم‌عمق، پس نسبتش کشیده‌تر است
    const bustRing = ring(bustY, bust);

    /*
     * سرشانه پله نیست، شیب است. اگر مستقیم از حلقهٔ گردن به حلقهٔ سرشانه
     * برویم، بین آن دو یک حلقهٔ تقریباً افقی می‌ماند که مثل تخته روی شانه
     * می‌نشیند — و لباسی هم که رویش سوار شود همان تخته را می‌گیرد.
     *
     * پس میانشان دو تراز گذاشته می‌شود: کنارِ گردن که هنوز باریک است، و بالای
     * بازو که تقریباً به پهنای سرشانه رسیده. عمقِ سرشانه هم کم نیست — قفسهٔ
     * سینه آن‌جا شروع شده — وگرنه حلقه‌اش تیغه‌ای می‌شود.
     */
    const shoulderRz = bustRing.rz * 0.86;

    const torso = [
        ring(neckY, neckGirth, 1.12),
        {
            y: neckY + (shoulderY - neckY) * 0.42,
            rx: (neckGirth / Math.PI) * 1.25 + (shoulderWidth / 2 - (neckGirth / Math.PI) * 1.25) * 0.34,
            rz: minorAxis(neckGirth, 1.12) + (shoulderRz - minorAxis(neckGirth, 1.12)) * 0.5,
        },
        {
            y: neckY + (shoulderY - neckY) * 0.78,
            rx: shoulderWidth / 2 * 0.9,
            rz: shoulderRz * 0.94,
        },
        { y: shoulderY, rx: shoulderWidth / 2, rz: shoulderRz },
        ring(bustY, bust),
        ring(underBustY, underBust),
        ring(waistY, waist),
        ring(highHipY, (waist + hip) / 2),
        ring(hipY, hip),
        ring(crotchY, hip * 0.97),
    ];

    const arm = [
        { y: 0, r: minorAxis(bicep * 1.08, 1) },
        { y: armLength * 0.45, r: minorAxis(bicep * 0.86, 1) },
        { y: armLength * 0.75, r: minorAxis(bicep * 0.7, 1) },
        { y: armLength, r: minorAxis(wrist, 1) },
    ];

    const leg = [
        { y: 0, r: minorAxis(thigh, 1) },
        { y: (ankleY - crotchY) * 0.5, r: minorAxis(knee, 1) },
        { y: (ankleY - crotchY) * 0.82, r: minorAxis(knee * 0.8, 1) },
        { y: ankleY - crotchY, r: minorAxis(ankle, 1) },
    ];

    return {
        height,
        level: {
            chin, neck: neckY, shoulder: shoulderY, bust: bustY, underBust: underBustY,
            waist: waistY, highHip: highHipY, hip: hipY, crotch: crotchY, knee: kneeY, ankle: ankleY,
        },
        head: { radius: headHeight / 2, centre: headHeight * 0.5 },
        // حلقهٔ نوکِ سرشانه؛ لباس و آستین از همین‌جا می‌آویزند
        shoulderRing: { y: shoulderY, rx: shoulderWidth / 2, rz: bustRing.rz * 0.86 },
        neckRadius: minorAxis(neckGirth, 1.12),
        shoulderHalf: shoulderWidth / 2,
        torso,
        arm,
        leg,
        legGap: Math.max(minorAxis(thigh, 1) * 1.02, hip * 0.055),
        girthAt: (y) => girthOf(torso, y),
    };
};

/** دورِ بدن در ارتفاع دلخواه (از حلقه‌های تنه، با میان‌یابی). */
export const girthOf = (torso, y) => {
    const ring = sampleRing(torso, y);

    return perimeter(ring.rx, ring.rz);
};

/** حلقهٔ تنه در ارتفاع دلخواه. */
export const sampleRing = (rings, y) => {
    if (rings.length === 0) {
        return { y, rx: 0, rz: 0 };
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
                rx: rings[i - 1].rx + (rings[i].rx - rings[i - 1].rx) * t,
                rz: rings[i - 1].rz + (rings[i].rz - rings[i - 1].rz) * t,
            };
        }
    }

    return rings[rings.length - 1];
};
