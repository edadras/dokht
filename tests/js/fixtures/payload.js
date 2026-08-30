/*
 * نمونهٔ دستیِ بستهٔ سرور و جدول‌های مانکن.
 *
 * سمت سرور هم‌زمان و جدا ساخته می‌شود، پس این فایل تنها چیزی است که آزمون‌ها را
 * به قرارداد وصل می‌کند: هر عددی که اینجاست مستقیم از `docs/drape-contract.md`
 * آمده، نه از خروجی واقعی سرور. اگر روزی بسته عوض شد، اول این فایل باید بشکند.
 */

/*
 * بدنِ سنجه همان بدنِ مرورگر است، نه رونوشتش.
 *
 * این‌جا یک جدولِ دستی بود: کمر ۰٫۶۲۵ برابرِ قد، باسن ۰٫۵۳، و همین‌طور تا آخر —
 * رونوشتی از مانکنِ قدیمی که همه را با یک نسبت می‌ساخت. مانکن که از روی
 * اندازه‌های خودِ مشتری بازنویسی شد، این رونوشت جا ماند و هیچ آزمونی هم نگفت،
 * چون خودش مرجعِ خودش بود. اندازه گرفته شد، برای سایز ۴۰: کمرِ این جدول ۱۰۵
 * سانتی‌متر و کمرِ مانکن ۱۰۲٫۴، و باسن پنج سانتی‌متر بالاتر از جایش. یعنی سنجه
 * لباس را روی بدنی می‌سنجید که مرورگر هرگز نشان نمی‌داد.
 *
 * چندضلعی‌های نمونه پایین‌تر دست‌نویس می‌مانند — آن‌ها قرارداد را می‌سنجند و باید
 * از سرور مستقل باشند. ولی بدن قرارداد نیست؛ خودِ mannequin.js است.
 */
import { buildBody, drapeBody } from '../../../resources/js/lib/mannequin.js';

export const makeBody = (avatar = {}) => drapeBody(buildBody({
    height: 165,
    hip: 98,
    high_hip: 90,
    waist: 74,
    under_bust: 78,
    bust: 92,
    neck: 36,
    bicep: 28,
    wrist: 16,
    thigh: 56,
    knee: 37,
    ankle: 23,
    shoulder_width: 39,
    arm_length: 58,
    ...avatar,
}));

/*
 * یک بالاتنه‌ی ساده: جلو و پشت، با درز پهلوی دوطرفه و یک ساسون سینه روی جلو.
 *
 * چندضلعی‌ها به عمد درشت و خوانا نوشته شده‌اند تا وقتی آزمونی می‌شکند بشود با
 * چشم دنبالشان کرد. رأس صفر گوشه‌ی بالا-چپ است و مسیر رو به پایین می‌رود
 * (y الگو رو به پایین است).
 */
export const bodicePayload = () => ({
    scale: 0.01,
    pieces: [
        {
            id: 'front-bodice#0',
            code: 'front-bodice',
            name: 'تنه جلو',
            role: 'torso',
            side: 'front',
            instance: 0,
            mirrored: false,
            layer: 'outer',
            /*
             * تنهٔ کاملِ باز شده از تا: از حلقهٔ آستین راست دور می‌زند تا حلقهٔ
             * چپ، و از خط کمر برمی‌گردد. y رو به پایین است.
             *   ۰ بالای پهلوی راست   ۱ سرشانهٔ راست   ۲ کنار یقه   ۳ گودی یقه
             *   ۴ کنار یقه           ۵ سرشانهٔ چپ     ۶ زیربغل چپ  ۷ پایین پهلوی چپ
             *   ۸ پایین پهلوی راست
             */
            polygon: [
                [0, 9],
                [5, 0],
                [16, 2],
                [23, 6],
                [30, 2],
                [41, 0],
                [46, 9],
                [46, 42],
                [0, 42],
            ],
            edges: [
                { tag: 'armhole-right', start: 0, end: 1, length: 10.3 },
                { tag: 'shoulder-right', start: 1, end: 2, length: 11.2 },
                { tag: 'neck', start: 2, end: 4, length: 16.1 },
                { tag: 'shoulder-left', start: 4, end: 5, length: 11.2 },
                { tag: 'armhole-left', start: 5, end: 6, length: 10.3 },
                { tag: 'side-left', start: 6, end: 7, length: 33 },
                { tag: 'waist', start: 7, end: 8, length: 46 },
                { tag: 'side-right', start: 8, end: 0, length: 33 },
            ],
            /*
             * ساسون سینه که هنوز روی مسیر بریده نشده: چندضلعی از روی دهانه‌اش صاف
             * رد می‌شود و مرورگر باید خودش بریدنش را انجام دهد.
             */
            darts: [
                {
                    legs: [
                        [20, 42],
                        [26, 42],
                    ],
                    apex: [23, 28],
                    intake: 4,
                    on_edge: 6,
                },
            ],
            placement: {
                zone: 'torso_front',
                u0: -1.35,
                u1: 1.35,
                y_top: 0.82,
                radius_hint: 'bust',
                flip: false,
            },
        },
        {
            id: 'back-bodice#0',
            code: 'back-bodice',
            name: 'تنه پشت',
            role: 'torso',
            side: 'back',
            instance: 0,
            mirrored: false,
            layer: 'outer',
            // همان تنه، با یقه‌ی کم‌گودتر
            polygon: [
                [0, 9],
                [5, 0],
                [16, 2],
                [23, 3],
                [30, 2],
                [41, 0],
                [46, 9],
                [46, 42],
                [0, 42],
            ],
            edges: [
                { tag: 'armhole-right', start: 0, end: 1, length: 10.3 },
                { tag: 'shoulder-right', start: 1, end: 2, length: 11.2 },
                { tag: 'neck', start: 2, end: 4, length: 14.1 },
                { tag: 'shoulder-left', start: 4, end: 5, length: 11.2 },
                { tag: 'armhole-left', start: 5, end: 6, length: 10.3 },
                { tag: 'side-left', start: 6, end: 7, length: 33 },
                { tag: 'waist', start: 7, end: 8, length: 46 },
                { tag: 'side-right', start: 8, end: 0, length: 33 },
            ],
            darts: [],
            placement: {
                zone: 'torso_back',
                u0: 1.79,
                u1: 4.49,
                y_top: 0.82,
                radius_hint: 'bust',
                flip: false,
            },
        },
        {
            id: 'lining#0',
            code: 'lining',
            name: 'آستر',
            role: 'torso',
            side: 'front',
            instance: 0,
            mirrored: false,
            layer: 'lining',
            polygon: [
                [0, 0],
                [20, 0],
                [20, 30],
                [0, 30],
            ],
            edges: [],
            darts: [],
            placement: { zone: 'torso_front', u0: -1, u1: 1, y_top: 0.7, radius_hint: 'waist' },
        },
    ],
    /*
     * جلو و پشت هر دو به یک جهت نوشته شده‌اند، ولی روی بدن رو در روی هم می‌نشینند؛
     * پس پهلوی چپِ جلو به پهلوی راستِ پشت می‌رسد و هر کمان وارونهٔ جفتش پیموده
     * می‌شود. همین است که `reverse` را در بستهٔ سرور لازم می‌کند.
     */
    seams: [
        {
            a: { piece: 'front-bodice#0', from: 6, to: 7, length: 33 },
            b: { piece: 'back-bodice#0', from: 8, to: 0, length: 33 },
            label: 'درز پهلو چپ',
            reverse: true,
            ease: 0,
            kind: 'seam',
        },
        {
            a: { piece: 'front-bodice#0', from: 8, to: 0, length: 33 },
            b: { piece: 'back-bodice#0', from: 6, to: 7, length: 33 },
            label: 'درز پهلو راست',
            reverse: true,
            ease: 0,
            kind: 'seam',
        },
        {
            a: { piece: 'front-bodice#0', from: 4, to: 5, length: 11.2 },
            b: { piece: 'back-bodice#0', from: 1, to: 2, length: 11.2 },
            label: 'درز سرشانه چپ',
            reverse: true,
            ease: 0.2,
            kind: 'seam',
        },
        {
            a: { piece: 'front-bodice#0', from: 1, to: 2, length: 11.2 },
            b: { piece: 'back-bodice#0', from: 4, to: 5, length: 11.2 },
            label: 'درز سرشانه راست',
            reverse: true,
            ease: 0.2,
            kind: 'seam',
        },
        {
            a: { piece: 'front-bodice#0', from: 3, to: 3, length: 0 },
            b: { piece: 'front-bodice#0', from: 3, to: 3, length: 0 },
            label: 'تای مرکز جلو',
            reverse: false,
            ease: 0,
            kind: 'fold',
        },
    ],
    budget: { target_edge: 3, max_vertices: 6000 },
});

/*
 * دو مربع جدا با یک درز میان لبه‌ی راستِ یکی و لبه‌ی چپِ دیگری — کوچک‌ترین
 * بسته‌ای که «دوختن» در آن معنی دارد.
 */
export const twoSquares = (gapCm = 6) => ({
    scale: 0.01,
    pieces: [
        {
            id: 'left#0',
            code: 'left',
            name: 'چپ',
            role: 'detail',
            instance: 0,
            layer: 'outer',
            polygon: [
                [0, 0],
                [10, 0],
                [10, 10],
                [0, 10],
            ],
            edges: [],
            darts: [],
            placement: { zone: 'detail', u0: -0.6, u1: -0.1, y_top: 0.7, radius_hint: 'waist' },
        },
        {
            id: 'right#0',
            code: 'right',
            name: 'راست',
            role: 'detail',
            instance: 0,
            layer: 'outer',
            polygon: [
                [0, 0],
                [10, 0],
                [10, 10],
                [0, 10],
            ],
            edges: [],
            darts: [],
            placement: {
                zone: 'detail',
                u0: 0.1 + gapCm / 100,
                u1: 0.6 + gapCm / 100,
                y_top: 0.7,
                radius_hint: 'waist',
            },
        },
    ],
    seams: [
        {
            a: { piece: 'left#0', from: 1, to: 2, length: 10 },
            b: { piece: 'right#0', from: 3, to: 0, length: 10 },
            // لبهٔ راستِ مربع اول رو به پایین پیموده می‌شود و لبهٔ چپِ مربع دوم
            // رو به بالا؛ پس سمت b باید وارونه خوانده شود
            label: 'درز میانی',
            reverse: true,
            ease: 0,
            kind: 'seam',
        },
    ],
    budget: { target_edge: 2, max_vertices: 6000 },
});

/*
 * دو آستین، آینهٔ هم — کوچک‌ترین بسته‌ای که «لباس یک‌وری نمی‌نشیند» را می‌سنجد.
 *
 * هر دو از یک الگو بریده شده‌اند، یکی برای دست چپ و یکی برای راست، و به هم
 * دوخته نمی‌شوند. پس هر ناقرینگی‌ای که در خروجی دیده شود کارِ خودِ چیدن است.
 */
export const twoSleeves = () => {
    const tube = [
        [0, 0],
        [28, 0],
        [28, 55],
        [0, 55],
    ];

    const sleeve = (id, side, mirrored) => ({
        id,
        code: 'sleeve',
        name: 'آستین',
        role: 'sleeve',
        side,
        instance: mirrored ? 1 : 0,
        mirrored,
        layer: 'outer',
        polygon: mirrored ? tube.map(([x, y]) => [28 - x, y]).reverse() : tube,
        edges: [{ tag: 'armhole', start: 0, end: 1, length: 28 }],
        darts: [],
        placement: { zone: 'sleeve', u0: -Math.PI, u1: Math.PI, y_top: 0.775, radius_hint: 'bicep' },
    });

    return {
        scale: 0.01,
        pieces: [sleeve('sleeve#0', 'left', false), sleeve('sleeve#1', 'right', true)],
        seams: [],
        budget: { target_edge: 4, max_vertices: 6000 },
        meta: {},
    };
};

/*
 * یک یقهٔ ایستاده با خط خواب — کوچک‌ترین بسته‌ای که «تا» در آن معنی دارد.
 *
 * سرور یقه را راست می‌کند (standUp)، پس خط یقه کفِ کادر است و لبهٔ بیرونی سقفش.
 * `roll` در همان دستگاهِ چندضلعی است: ۳ سانتی‌متر بالاتر از خط یقه، یعنی پایهٔ
 * ۳ سانتی‌متری و رویهٔ ۴٫۵ سانتی‌متری که باید روی آن برگردد.
 */
export const collarPayload = (height = 7.5, stand = 3) => {
    const body = makeBody();
    const length = 40;

    return {
        scale: 0.01,
        pieces: [
            {
                id: 'collar#0',
                code: 'collar',
                name: 'یقه',
                role: 'collar',
                side: 'front',
                instance: 0,
                mirrored: false,
                layer: 'outer',
                polygon: [
                    [0, 0],
                    [length, 0],
                    [length, height],
                    [0, height],
                ],
                edges: [
                    { tag: 'default', start: 0, end: 1, length },
                    { tag: 'default', start: 1, end: 2, length: height },
                    { tag: 'neck', start: 2, end: 3, length },
                    { tag: 'default', start: 3, end: 0, length: height },
                ],
                darts: [],
                roll: height - stand,
                placement: {
                    zone: 'collar',
                    u0: -Math.PI,
                    u1: Math.PI,
                    y_top: body.level.neck,
                    radius_hint: 'neck',
                },
            },
        ],
        seams: [],
        budget: { target_edge: 2, max_vertices: 6000 },
        meta: {},
    };
};

/*
 * برخوردگرهای بدن — همان‌هایی که نماگر می‌سازد، برای سنجه.
 *
 * سنجه تا امروز هیچ بدنی نداشت: قطعه‌ها را می‌دوخت و رها می‌کرد، پس عددهای
 * «افتادن» و «پوستِ لخت» بدبینانه بودند و از همه مهم‌تر، *بازو* در آن وجود
 * نداشت. کاربر همین را دید و گفت «مانکن دست نداره شاید مشکل از اینه» — و درست
 * بود: آستین چیزی نداشته که رویش بنشیند.
 *
 * ماتریسِ هر برخوردگر ثابت است (ژستِ ایستاده)، پس همین‌جا دستی ساخته می‌شود.
 */
export const bodyColliders = (Collider, body, avatar = {}, grow = 1) => {
    const level = body.level;
    const r = body.radii;
    const height = level.top;
    const armLength = (avatar.arm_length || 58) / 100;
    const out = [];
    // `grow` بدن را نازک می‌کند؛ ببینید ترتیبِ «اول بدوز، بعد تن کن» در سنجه
    const thin = (sections) => sections.map((row) => {
        const scaled = row.slice();

        for (let i = 1; i < scaled.length; i++) {
            scaled[i] *= grow;
        }

        return scaled;
    });
    const at = (sections, name, offset = [0, 0, 0], caps = {}, spin = 0) => {
        const collider = new Collider({ sections: thin(sections), name, ...caps });
        // چرخش حول z، همان کاری که گروهِ بازو در نماگر می‌کند
        const cos = Math.cos(spin);
        const sin = Math.sin(spin);
        const matrix = new Float32Array([
            cos, sin, 0, 0,
            -sin, cos, 0, 0,
            0, 0, 1, 0,
            offset[0], offset[1], offset[2], 1,
        ]);
        // وارونِ یک چرخش‌و‌جابه‌جایی: R⁻¹ و بعد -R⁻¹·t
        const inverse = new Float32Array([
            cos, -sin, 0, 0,
            sin, cos, 0, 0,
            0, 0, 1, 0,
            -(cos * offset[0] + sin * offset[1]),
            -(-sin * offset[0] + cos * offset[1]),
            -offset[2],
            1,
        ]);

        collider.setTransform(matrix, inverse, 0.03);
        out.push(collider);
    };

    at(body.profile.filter(([y]) => y >= level.crotch - 1e-6), 'torso');
    at([[level.neck - 0.02, r.neck * 1.05, r.neck * 1.05], [level.chin, r.neck * 0.92, r.neck * 0.92]], 'neck');

    const headR = (height - level.chin) * 0.62;
    const headY = level.chin + (height - level.chin) * 0.55;

    at([
        [headY - headR * 0.95, headR * 0.39, headR * 0.41],
        [headY, headR * 0.86, headR * 0.9],
        [headY + headR * 0.95, headR * 0.39, headR * 0.41],
    ], 'head');

    /*
     * بازوها: «چپ» روی x منفی، همان قراردادی که سرور برای زاویه دارد — و هشت
     * درجه باز، همان‌طور که نماگر می‌سازدشان (armLZ/armRZ در poseAngles، برای
     * *همهٔ* حالت‌ها از جمله ایستاده).
     *
     * سنجه بازو را عمودی می‌ساخت و همین یک عدد را جا می‌انداخت: در مرورگر آستین
     * تا هشت سانتی‌متر از بازو دور می‌افتاد و روی مانکن کنارِ بازو آویزان می‌ماند،
     * ولی هر نُه عددِ سنجه سالم بود. کاربر دیدش، سنجه نه.
     */
    const tilt = body.armTilt ?? 0;

    for (const [name, side] of [['armL', -1], ['armR', 1]]) {
        at([
            [-armLength - r.wrist * 0.5, r.wrist * 1.1, r.wrist * 1.1],
            [-armLength * 0.55, r.bicep * 0.74, r.bicep * 0.74],
            [-armLength * 0.12, r.bicep * 1.02, r.bicep * 1.02],
            [0, r.bicep * 1.06, r.bicep * 1.06],
            [r.bicep * 0.5, r.bicep * 0.86, r.bicep * 0.86],
        ], name, [side * (body.armOffset ?? r.shoulder * 0.87), level.shoulder - 0.035, 0], {}, side * tilt);
    }

    const thighDrop = level.crotch - level.knee;
    const shinDrop = level.knee - level.ankle;

    for (const [name, side] of [['legL', -1], ['legR', 1]]) {
        const hip = r.hip * 0.42;

        at([
            [-thighDrop, r.knee * 1.02, r.knee * 1.02],
            [-thighDrop * 0.5, r.thigh * 0.84, r.thigh * 0.84],
            [-0.02, r.thigh * 1.02, r.thigh * 1.02],
            [0, r.thigh * 1.04, r.thigh * 1.04],
        ], 'thigh' + name, [side * hip, level.crotch, 0]);

        at([
            [-shinDrop, r.ankle * 1.1, r.ankle * 1.1],
            [-shinDrop * 0.6, r.ankle * 1.42, r.ankle * 1.42],
            [0, r.knee * 0.98, r.knee * 0.98],
        ], 'shin' + name, [side * hip, level.knee, 0]);
    }

    return out;
};
