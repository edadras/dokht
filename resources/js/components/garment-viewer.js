/*
 * نمای سه‌بعدی لباس روی مانکن.
 *
 * این فایل چهار کار انجام می‌دهد:
 *   ۱) از اندازه‌های بدن یک مانکن پارامتری می‌سازد (تن، گردن، سر، دست‌ها، پاها).
 *   ۲) روی همان مانکن لباسی می‌پوشاند که فرم و قدش از الگو می‌آید.
 *   ۳) پارچه را به یک حل‌کننده‌ی واقعی می‌سپارد: لباس زیر وزن خودش می‌افتد، به
 *      بدن برخورد می‌کند و با تغییر حالت دوباره روی بدن می‌نشیند.
 *   ۴) نواحی تنگ و گشاد را با رنگ روی لباس نشان می‌دهد.
 *
 * نکتهٔ کلیدی شکل‌گیری: بدن و لباس هر دو از یک جدول مشترک «مقطع بدن» ساخته
 * می‌شوند (bodyProfile). هر مقطع یک بیضی افقی است با نیم‌پهنا (rx) و نیم‌عمق (rz)
 * جدا از هم؛ چون بدن از پهلو گشاد و از جلو به عقب باریک است و سرشانه پهن ولی
 * کم‌عمق است. لباس همان جدول را با «آزادی» هر ناحیه می‌خواند، پس هیچ‌وقت از بدن
 * جدا نمی‌افتد و هیچ‌وقت هم داخل بدن فرو نمی‌رود.
 *
 * همان جدول، عیناً به حل‌کننده هم داده می‌شود تا برخوردگرهای بدن ساخته شوند؛
 * برای همین چیزی که کاربر می‌بیند دقیقاً همان چیزی است که پارچه با آن برخورد
 * می‌کند.
 *
 * فضای مختصات پارچه: لباس‌ها بعد از ساخته شدن از گروه بدن جدا و مستقیم به صحنه
 * وصل می‌شوند، یعنی رأس‌هایشان در فضای جهانی‌اند. فقط رأس‌های «دوخته‌شده»
 * (سرشانه، کمربند، سرِ آستین) هر فریم از روی ماتریس همان گروه بدن جابه‌جا
 * می‌شوند و بقیه‌ی پارچه دنبالشان می‌افتد. همین است که تغییر حالت به‌جای پرش،
 * یک افتادن نرم چندصد میلی‌ثانیه‌ای دیده می‌شود.
 *
 * هیچ کتابخانه‌ی کمکی لازم نیست؛ چرخش با ماوس هم دستی نوشته شده است.
 */

const RAD = Math.PI / 180;

/* کمترین فاصلهٔ پارچه از پوست (متر)؛ جلوی فرورفتن لباس در بدن را می‌گیرد */
const SKIN_GAP = 0.006;

/* مدت گذر نرم از یک حالت بدن به حالت بعد (ثانیه) */
const POSE_DURATION = 0.45;

/*
 * کتابخانه سه‌بعدی فقط زمانی دانلود می‌شود که کاربر به صفحه نمای سه‌بعدی برسد؛
 * بقیه صفحه‌ها نباید هزینه این حجم را بپردازند. حل‌کننده‌ی پارچه هم با همان
 * درخواست می‌آید تا بستهٔ اصلی سنگین‌تر نشود.
 */
let THREE = null;
let CLOTH = null;
let DRAPE = null;

/*
 * اشیای three بیرون از حالت واکنشی آلپاین نگه داشته می‌شوند.
 *
 * اگر یک شیء three از داخل دادهٔ آلپاین خوانده شود، در یک Proxy واکنشی پیچیده
 * می‌شود و three روی ویژگی‌های فقط‌خواندنی مثل modelViewMatrix خطا می‌دهد و صحنه
 * سیاه می‌ماند. WeakMap زیر همین مسیر را دور می‌زند.
 *
 * کلید نقشه همیشه ریشه‌ی کامپوننت است ($root نه $el)؛ چون آلپاین داخل شنونده‌ی
 * رویداد، $el را برابر همان دکمه‌ای می‌گذارد که کلیک شده و نه ریشه‌ی x-data.
 */
const contexts = new WeakMap();

const contextFor = (element) => {
    if (! contexts.has(element)) {
        contexts.set(element, {});
    }

    return contexts.get(element);
};

const loadThree = async () => {
    THREE ??= await import('three');

    /*
     * اگر به هر دلیلی حل‌کننده نیامد، صفحه نباید بمیرد: لباس ایستا نمایش داده
     * می‌شود و فقط شبیه‌سازی زنده در دسترس نیست.
     */
    if (! CLOTH) {
        try {
            CLOTH = await import('../lib/cloth-solver');
        } catch (error) {
            CLOTH = null;
        }
    }

    /* دوختِ واقعیِ قطعه‌های الگو؛ نیامدنش یعنی برگشت به نمای پارامتری */
    if (! DRAPE) {
        try {
            DRAPE = await import('../lib/pattern-drape');
        } catch (error) {
            DRAPE = null;
        }
    }

    return THREE;
};

/* تبدیل دور (سانتی‌متر) به شعاع در مقیاس صحنه (متر) */
const radius = (girthCm) => Math.max(0.02, girthCm / (2 * Math.PI) / 100);

/* تبدیل آزادی (سانتی‌متر) به فاصلهٔ شعاعی؛ آزادی منفی هم مجاز است (لباس تنگ) */
const gapFor = (cm) => cm / (2 * Math.PI) / 100;

const lerp = (a, b, t) => a + (b - a) * t;

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

/* منحنی نرم؛ برای گوشه‌های سرشانه و باز شدن دامن */
const smooth = (t) => t * t * (3 - 2 * t);

/*
 * درون‌یابی خطی روی جدولی از [ارتفاع, مقدار۱, مقدار۲, ...] که بر اساس ارتفاع
 * صعودی مرتب شده است. بیرون از بازه، نزدیک‌ترین سطر برگردانده می‌شود.
 */
const sample = (table, y) => {
    if (y <= table[0][0]) {
        return table[0].slice(1);
    }

    const last = table[table.length - 1];

    if (y >= last[0]) {
        return last.slice(1);
    }

    for (let i = 0; i < table.length - 1; i++) {
        const low = table[i];
        const high = table[i + 1];

        if (y >= low[0] && y <= high[0]) {
            const t = (y - low[0]) / Math.max(1e-6, high[0] - low[0]);

            return low.slice(1).map((value, index) => lerp(value, high[index + 1], t));
        }
    }

    return last.slice(1);
};

/* عدد شبه‌تصادفی ثابت برای هر شماره؛ چین‌ها همیشه یک شکل دربیایند */
const hash = (i) => {
    const x = Math.sin(i * 12.9898 + 4.1414) * 43758.5453;

    return x - Math.floor(x);
};

/* حالت‌های بدن: زاویه‌ی اندام‌ها بر حسب درجه */
const POSES = {
    stand: {},
    walk: { legL: -18, legR: 16, armL: 10, armR: -10, torso: 2 },
    arm_raise: { armLZ: -120, armRZ: 120, torso: -3 },
    bend: { torso: 42, armL: 18, armR: 18 },
    sit: { legL: -85, legR: -85, knee: 80, drop: -0.18, torso: 4 },
    turn: { spin: 55 },
};

export default (config = {}) => ({
    /* ------------------------------------------------------------------
     * وضعیتِ قابل استفاده در قالب
     * ------------------------------------------------------------------ */
    supported: true,
    autoRotate: false,
    showZones: true,
    liveSim: true,
    /* دوختِ واقعیِ قطعه‌های الگو؛ خاموش که شود، پوستهٔ پارامتری برمی‌گردد */
    trueSeams: true,
    seamCount: 0,
    pose: config.pose || 'stand',
    payload: config.payload || {},

    loading: true,

    init() {
        if (!this.hasWebGl()) {
            this.supported = false;
            this.loading = false;

            return;
        }

        loadThree()
            .then(() => {
                this.loading = false;
                this.$nextTick(() => this.build());
            })
            .catch(() => {
                this.supported = false;
                this.loading = false;
            });
    },

    destroy() {
        this.teardown();
    },

    get zones() {
        return this.payload.zones || [];
    },

    hasWebGl() {
        try {
            const canvas = document.createElement('canvas');

            return !!(window.WebGLRenderingContext && (canvas.getContext('webgl2') || canvas.getContext('webgl')));
        } catch (error) {
            return false;
        }
    },

    /* ------------------------------------------------------------------
     * ساخت صحنه
     * ------------------------------------------------------------------ */
    build() {
        const stage = this.$refs.stage;

        if (!stage) {
            this.supported = false;

            return;
        }

        const ctx = contextFor(this.$root);
        const width = stage.clientWidth || 480;
        const height = stage.clientHeight || 360;

        try {
            ctx.renderer = new THREE.WebGLRenderer({ antialias: true });
        } catch (error) {
            this.supported = false;

            return;
        }

        ctx.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        ctx.renderer.setSize(width, height);
        ctx.renderer.domElement.style.width = '100%';
        ctx.renderer.domElement.style.height = '100%';
        ctx.renderer.domElement.style.touchAction = 'none';
        stage.appendChild(ctx.renderer.domElement);

        ctx.scene = new THREE.Scene();
        ctx.scene.background = new THREE.Color('#1c1917');
        ctx.camera = new THREE.PerspectiveCamera(38, width / height, 0.1, 40);
        ctx.orbit = { yaw: 0, pitch: 4 * RAD, distance: 3.4, target: new THREE.Vector3(0, 0.95, 0) };
        ctx.disposables = [];

        this.addLights();
        this.buildModel();

        /*
         * مدل در حالت خنثی ساخته می‌شود ولی «ایستاده» یعنی دست‌ها هشت درجه از تن
         * فاصله دارند. پیش از تحویل لباس به حل‌کننده باید بدن دقیقاً در همین حالت
         * پایه باشد، وگرنه سرِ آستین یک تکان اولیه می‌خورد.
         */
        ctx.poseNow = this.poseAngles('stand');
        this.writePose(ctx.poseNow);

        this.buildCloth();
        this.applyPose(this.pose);
        this.updateCamera();
        this.bindPointer();
        this.observeResize();
        this.loop();
    },

    /* نور: یک نور اصلی نرم، یک نور پرکننده و کمی نور محیط */
    addLights() {
        const ctx = contextFor(this.$root);

        ctx.scene.add(new THREE.HemisphereLight('#fdf6ee', '#3f3a35', 0.85));

        const key = new THREE.DirectionalLight('#fff8ef', 1.15);
        key.position.set(1.6, 2.6, 2.4);
        ctx.scene.add(key);

        const fill = new THREE.DirectionalLight('#cfd8e3', 0.45);
        fill.position.set(-2, 1.2, -1.8);
        ctx.scene.add(fill);

        const ground = new THREE.Mesh(
            new THREE.CircleGeometry(1.6, 48),
            new THREE.MeshStandardMaterial({ color: '#2a2522', roughness: 1, metalness: 0 }),
        );
        ground.rotation.x = -Math.PI / 2;
        ctx.scene.add(ground);
        ctx.disposables.push(ground.geometry, ground.material);
    },

    /* ------------------------------------------------------------------
     * مانکن پارامتری
     * ------------------------------------------------------------------
     * همه‌ی ارتفاع‌ها ضریبی از قد هستند و همه‌ی شعاع‌ها از دورهای بدن می‌آیند؛
     * پس با تغییر دور سینه یا باسن، شکل مانکن به‌چشم عوض می‌شود.
     */
    buildModel() {
        const ctx = contextFor(this.$root);
        const avatar = this.payload.avatar || {};
        const H = (avatar.height || 165) / 100;

        const level = {
            ankle: H * 0.045,
            knee: H * 0.28,
            crotch: H * 0.475,
            hip: H * 0.53,
            highHip: H * 0.575,
            waist: H * 0.625,
            underBust: H * 0.69,
            bust: H * 0.725,
            armhole: H * 0.775,
            shoulder: H * 0.82,
            neck: H * 0.855,
            chin: H * 0.885,
            top: H,
        };

        const r = {
            hip: radius(avatar.hip || 98),
            highHip: radius(avatar.high_hip || (avatar.hip || 98) - 8),
            waist: radius(avatar.waist || 74),
            underBust: radius(avatar.under_bust || (avatar.bust || 92) - 14),
            bust: radius(avatar.bust || 92),
            neck: radius(avatar.neck || 36),
            armhole: radius(avatar.armhole || 40),
            bicep: radius(avatar.bicep || 28),
            wrist: radius(avatar.wrist || 16),
            thigh: radius(avatar.thigh || 56),
            knee: radius(avatar.knee || 37),
            ankle: radius(avatar.ankle || 23),
            shoulder: (avatar.shoulder_width || 39) / 100 / 2,
        };

        ctx.level = level;
        ctx.radii = r;
        ctx.armLength = (avatar.arm_length || 58) / 100;
        ctx.profile = this.bodyProfile();

        const skin = new THREE.MeshStandardMaterial({ color: '#d9c3ad', roughness: 0.85, metalness: 0.02 });
        ctx.disposables.push(skin);

        // گروه‌ها تا حالت‌های بدن فقط با چرخش ساخته شوند
        ctx.root = new THREE.Group();
        ctx.torso = new THREE.Group();
        ctx.armL = new THREE.Group();
        ctx.armR = new THREE.Group();
        ctx.legL = new THREE.Group();
        ctx.legR = new THREE.Group();
        ctx.kneeL = new THREE.Group();
        ctx.kneeR = new THREE.Group();

        /*
         * لولای کمر: بالاتنه باید دور خط کمر خم شود، نه دور کف صحنه. گروه spine
         * روی خط کمر می‌نشیند و گروه torso همان‌قدر پایین می‌آید تا مختصات بچه‌هایش
         * همان ارتفاع واقعی بماند.
         */
        ctx.spine = new THREE.Group();
        ctx.spine.position.y = level.waist;
        ctx.torso.position.y = -level.waist;

        ctx.scene.add(ctx.root);
        ctx.root.add(ctx.spine, ctx.legL, ctx.legR);
        ctx.spine.add(ctx.torso);
        ctx.torso.add(ctx.armL, ctx.armR);

        // ---- تن: سطحی از مقطع‌های بیضی، از خط فاق تا گردن ----
        const torsoRings = [];
        const torsoSteps = 34;

        for (let i = 0; i <= torsoSteps; i++) {
            const y = lerp(level.crotch, level.neck, i / torsoSteps);
            const [rx, rz] = sample(ctx.profile, y);

            torsoRings.push(this.ringPoints({ y, rx, rz }, 48));
        }

        const torsoMesh = new THREE.Mesh(this.ringSurface(torsoRings), skin);
        ctx.torso.add(torsoMesh);
        ctx.disposables.push(torsoMesh.geometry);

        // ---- گردن و سر ----
        const neck = new THREE.Mesh(
            new THREE.CylinderGeometry(r.neck * 0.9, r.neck * 1.05, Math.max(0.03, level.chin - level.neck + 0.02), 20),
            skin,
        );
        neck.position.y = (level.neck + level.chin) / 2 - 0.01;
        ctx.torso.add(neck);
        ctx.disposables.push(neck.geometry);

        const head = new THREE.Mesh(new THREE.SphereGeometry((H - level.chin) * 0.62, 28, 20), skin);
        head.position.y = level.chin + (H - level.chin) * 0.55;
        head.scale.set(0.86, 1.05, 0.9);
        ctx.torso.add(head);
        ctx.disposables.push(head.geometry);

        // ---- دست‌ها: هر دست یک گروه با لولا روی سرشانه ----
        const armLength = ctx.armLength;
        ctx.armTable = [
            [-armLength, r.wrist],
            [-armLength * 0.55, r.bicep * 0.72],
            [-armLength * 0.12, r.bicep],
            [0, r.bicep * 1.02],
        ];

        [
            [ctx.armL, 1],
            [ctx.armR, -1],
        ].forEach(([group, side]) => {
            group.position.set(side * r.shoulder * 0.87, level.shoulder - 0.035, 0);

            const arm = new THREE.Mesh(
                new THREE.LatheGeometry(
                    this.spline([
                        [r.wrist, -armLength],
                        [r.bicep * 0.72, -armLength * 0.55],
                        [r.bicep, -armLength * 0.12],
                        [r.bicep * 1.02, 0],
                    ]),
                    20,
                ),
                skin,
            );
            group.add(arm);
            ctx.disposables.push(arm.geometry);

            /*
             * سرشانه‌ی گرد: بالای چرخانه‌ی بازو باز است و لبه‌ی تیزش بیرون از تن
             * مثل یک باله‌ی تخت دیده می‌شود. این کره همان سوراخ را می‌بندد.
             */
            const ball = new THREE.Mesh(new THREE.SphereGeometry(r.bicep * 1.05, 18, 12), skin);
            ball.scale.set(1, 0.8, 1);
            group.add(ball);
            ctx.disposables.push(ball.geometry);

            const hand = new THREE.Mesh(new THREE.SphereGeometry(r.wrist * 1.35, 14, 10), skin);
            hand.position.y = -armLength - r.wrist;
            hand.scale.set(0.8, 1.3, 0.5);
            group.add(hand);
            ctx.disposables.push(hand.geometry);
        });

        // ---- پاها: لولای ران و لولای زانو ----
        [
            [ctx.legL, ctx.kneeL, 1],
            [ctx.legR, ctx.kneeR, -1],
        ].forEach(([group, kneeGroup, side]) => {
            group.position.set(side * r.hip * 0.42, level.crotch, 0);

            const thigh = new THREE.Mesh(
                new THREE.LatheGeometry(
                    this.spline([
                        [r.knee, -(level.crotch - level.knee)],
                        [r.thigh * 0.82, -(level.crotch - level.knee) * 0.5],
                        [r.thigh, -0.02],
                        [r.thigh * 1.02, 0],
                    ]),
                    20,
                ),
                skin,
            );
            group.add(thigh);
            group.add(kneeGroup);
            kneeGroup.position.y = -(level.crotch - level.knee);
            ctx.disposables.push(thigh.geometry);

            const shin = new THREE.Mesh(
                new THREE.LatheGeometry(
                    this.spline([
                        [r.ankle, -(level.knee - level.ankle)],
                        [r.ankle * 1.4, -(level.knee - level.ankle) * 0.6],
                        [r.knee * 0.95, 0],
                    ]),
                    18,
                ),
                skin,
            );
            kneeGroup.add(shin);
            ctx.disposables.push(shin.geometry);

            const foot = new THREE.Mesh(new THREE.BoxGeometry(r.ankle * 1.8, r.ankle * 0.9, r.ankle * 4), skin);
            foot.position.set(0, -(level.knee - level.ankle) - r.ankle * 0.4, r.ankle * 1.2);
            kneeGroup.add(foot);
            ctx.disposables.push(foot.geometry);
        });

        this.buildGarment();
    },

    /* ------------------------------------------------------------------
     * مقطع بدن در هر ارتفاع
     * ------------------------------------------------------------------
     * تنها منبع شکل بدن. هر سطر [ارتفاع, نیم‌پهنا, نیم‌عمق] است.
     *
     * دو نکته:
     *   • نیم‌عمق جدا از نیم‌پهنا نوشته می‌شود؛ سرشانه پهن است ولی عمق آن به
     *     اندازه‌ی عمق سینه است، نه بیشتر. (اشتباهِ قدیمی این بود که نیم‌پهنای
     *     سرشانه به‌عنوان «شعاع» دور محور چرخانده می‌شد و لباس به شکل شنل درمی‌آمد.)
     *   • پایین‌تر از فاق «بدن» یعنی پوشش هر دو پا؛ پس دامن‌های بلند و باریک هم
     *     می‌دانند تا کجا می‌توانند تنگ شوند.
     */
    bodyProfile() {
        const ctx = contextFor(this.$root);
        const level = ctx.level;
        const r = ctx.radii;

        // فاصله‌ی مرکز هر پا از محور بدن (همان جایی که گروه پا گذاشته می‌شود)
        const legOffset = r.hip * 0.42;
        const legAnkle = legOffset + r.ankle * 1.25;
        const legKnee = legOffset + r.knee;

        return [
            [level.ankle, legAnkle, legAnkle * 0.82],
            [level.knee, legKnee, legKnee * 0.82],
            [level.crotch, r.hip * 0.95, r.hip * 0.95 * 0.74],
            [level.hip, r.hip, r.hip * 0.76],
            [level.highHip, r.highHip, r.highHip * 0.76],
            [level.waist, r.waist, r.waist * 0.74],
            [level.underBust, r.underBust, r.underBust * 0.78],
            [level.bust, r.bust, r.bust * 0.84],
            // از سینه به بالا بدن آرام‌آرام پهن می‌شود (سرشانه پهن ولی کم‌عمق است)؛
            // اگر یک‌باره پهن شود، روی شانه‌ها یک طاقچه‌ی معلق درمی‌آید.
            [level.armhole, r.bust * 1.02, r.bust * 0.74],
            [level.shoulder, r.shoulder * 0.9, r.bust * 0.68],
            // شیب سرشانه: از سرشانه به گردن تند بالا می‌آید تا جای یقه باز شود
            [lerp(level.shoulder, level.neck, 0.45), r.neck * 1.38, r.neck * 1.3],
            [level.neck, r.neck * 1.1, r.neck * 1.04],
        ];
    },

    /* مقطع بدن (نیم‌پهنا و نیم‌عمق) در ارتفاع دلخواه */
    bodyAt(y) {
        const [rx, rz] = sample(contextFor(this.$root).profile, y);

        return { rx, rz };
    },

    /* شعاع بازو در ارتفاع محلیِ گروه دست (۰ روی سرشانه، منفی به سمت مچ) */
    armAt(y) {
        return sample(contextFor(this.$root).armTable, y)[0];
    },

    /* ------------------------------------------------------------------
     * ابزار هندسه: حلقه → سطح
     * ------------------------------------------------------------------ */

    /*
     * یک حلقه‌ی افقی از نقطه‌ها.
     *
     * حلقه یک بیضی با نیم‌پهنای rx و نیم‌عمق rz است. اگر پارچه چین داشته باشد
     * (spec.fold) شعاع با یک موج دوره‌ای کم و زیاد می‌شود و جاهایی که پارچه بیرون
     * می‌زند کمی هم پایین می‌افتد؛ همین دو با هم «چینِ عمودی» می‌سازند.
     */
    ringPoints(spec, segments, drape = null) {
        const points = [];
        const fold = spec.fold || 0;
        const lobes = drape?.lobes ?? 8;
        const phase = drape?.phase ?? 0;

        // موج دوم با نصفِ تعداد چین؛ وگرنه چین‌ها یکنواخت و ماشینی به‌نظر می‌رسند
        const halfLobes = Math.max(2, Math.round(lobes / 2));
        const offset = hash(lobes) * Math.PI * 2;

        for (let j = 0; j < segments; j++) {
            const angle = (j / segments) * Math.PI * 2;
            // فاز با ارتفاع کمی می‌چرخد تا چین‌ها مثل پارچه‌ی واقعی مورب بیفتند
            const drift = spec.y * 6 + phase;
            const wave =
                Math.sin(angle * lobes + drift) * 0.72 +
                Math.sin(angle * halfLobes + drift * 1.6 + offset) * 0.28;
            const swell = 1 + fold * wave;

            points.push(
                new THREE.Vector3(
                    spec.rx * Math.cos(angle) * swell,
                    spec.y - fold * spec.rx * 0.8 * (0.5 + wave * 0.5),
                    spec.rz * Math.sin(angle) * swell,
                ),
            );
        }

        return points;
    },

    /*
     * حلقه‌ها (از پایین به بالا، هرکدام با تعداد نقطه‌ی یکسان) را به یک سطح
     * لوله‌ای بسته تبدیل می‌کند. ترتیب مثلث‌ها طوری است که نرمال‌ها به بیرون
     * بیفتند، وگرنه نور روی لباس تخت و مرده می‌شود.
     */
    ringSurface(rings) {
        const segments = rings[0].length;
        const rows = rings.length;
        const positions = new Float32Array(rows * segments * 3);
        const indices = [];

        for (let row = 0; row < rows; row++) {
            for (let j = 0; j < segments; j++) {
                const point = rings[row][j];
                const at = (row * segments + j) * 3;

                positions[at] = point.x;
                positions[at + 1] = point.y;
                positions[at + 2] = point.z;
            }
        }

        for (let row = 0; row < rows - 1; row++) {
            for (let j = 0; j < segments; j++) {
                const next = (j + 1) % segments;
                const a = row * segments + j;
                const b = row * segments + next;
                const c = (row + 1) * segments + j;
                const d = (row + 1) * segments + next;

                indices.push(a, c, d, a, d, b);
            }
        }

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setIndex(indices);
        geometry.computeVertexNormals();

        return geometry;
    },

    /* ------------------------------------------------------------------
     * لباس
     * ------------------------------------------------------------------ */
    buildGarment() {
        const ctx = contextFor(this.$root);
        const garment = this.payload.garment || {};
        const fabric = this.payload.fabric || {};
        const physics = fabric.physics || {};
        const lengths = garment.lengths || {};
        const ease = garment.ease || {};
        const level = ctx.level;
        const r = ctx.radii;

        ctx.fabricMaterial = this.fabricMaterial(fabric, false);
        ctx.zoneMaterial = this.fabricMaterial(fabric, true);
        ctx.disposables.push(ctx.fabricMaterial, ctx.zoneMaterial);
        ctx.garmentMeshes = [];
        ctx.garmentPatches = [];

        const drape = this.drapeOf(fabric, physics);

        /*
         * کدام قطعه‌ها در الگو هست؟ اگر لیست قطعه‌ها نیامده باشد فقط از قدها
         * تصمیم می‌گیریم. این باعث می‌شود دامنِ تنها، بالاتنه‌ی الکی نداشته باشد.
         */
        const pieces = Array.isArray(garment.pieces) ? garment.pieces : [];
        const roles = new Set(pieces.map((piece) => piece.role));
        const hasList = pieces.length > 0;
        const hasBodice = hasList ? roles.has('torso') || roles.has('collar') : true;
        const hasSkirtPiece = roles.has('skirt') || roles.has('leg');
        const skirtLength = (lengths.skirt || 0) / 100;
        const sleeveLength = (lengths.sleeve || 0) / 100;

        /* آزادی هر ناحیه به فاصله‌ی شعاعی تبدیل می‌شود (فاصله‌ی پارچه از بدن) */
        const easeTable = [
            [level.ankle, gapFor(ease.hem ?? 8)],
            [level.hip, gapFor(ease.hip ?? 6)],
            [level.waist, gapFor(ease.waist ?? 4)],
            [level.bust, gapFor(ease.bust ?? 6)],
            [level.armhole, gapFor(ease.armhole ?? 4) * 0.7],
            [level.shoulder, gapFor(ease.shoulder ?? 1)],
            [level.neck, gapFor(ease.shoulder ?? 1)],
        ];
        const easeAt = (y) => sample(easeTable, y)[0];

        /*
         * فرم لباس: ضریب گشادی لبه‌ی پایین نسبت به باسن. پارچه‌ی نرم‌تر همان فرم
         * را بیشتر باز می‌کند، پس کلوشِ حریر از کلوشِ گاباردین گشادتر می‌افتد.
         */
        const silhouette = garment.silhouette || 'straight';
        const spread = { fitted: 0.94, straight: 1, a_line: 1.32, flared: 1.8 }[silhouette] ?? 1;
        const spreadSoft = 1 + (spread - 1) * (0.7 + drape.drape * 0.6);
        // اختلاف آزادی لبه با باسن؛ منفی باشد لباس مدادی (تنگ‌شونده) می‌شود
        const hemShift = gapFor((ease.hem ?? 0) - (ease.hip ?? 0));
        const hipRx = this.bodyAt(level.hip).rx + easeAt(level.hip);

        /*
         * پهنای سرشانه‌ی لباس. لباس روی سرشانه تا نوک شانه می‌رسد و از آنجا آستین
         * شروع می‌شود؛ پس پوسته باید از خط سینه به بالا یکنواخت پهن شود. اگر این
         * کار را نکنیم بین حلقهٔ آستین و سرشانه یک طاقچهٔ افقی می‌ماند که مثل
         * بالشتک شانه بیرون می‌زند.
         */
        const shoulderRx = r.shoulder * 0.95 + easeAt(level.shoulder);
        const bustRx = this.bodyAt(level.bust).rx + easeAt(level.bust);

        /*
         * مقطع پوسته‌ی لباس در هر ارتفاع.
         *   سینه تا سرشانه → آرام‌آرام تا نوک شانه پهن می‌شود
         *   سینه تا باسن   → مقطع بدن + آزادی همان ناحیه (لباس بدن را دنبال می‌کند)
         *   پایین‌تر از باسن → از خط باسن به سمت گشادی لبه باز (یا تنگ) می‌شود
         * در هر حال پارچه دست‌کم به اندازه‌ی SKIN_GAP بیرون از بدن می‌ماند.
         */
        const shellAt = (y, hemY) => {
            const body = this.bodyAt(y);
            let rx = body.rx + easeAt(y);
            let rz = body.rz + easeAt(y) * 0.85;

            if (y > level.bust && y <= level.shoulder) {
                const t = smooth(clamp((y - level.bust) / Math.max(0.02, level.shoulder - level.bust), 0, 1));

                rx = Math.max(rx, lerp(bustRx, shoulderRx, t));
            }

            if (y < level.hip && hemY < level.hip) {
                const t = clamp((level.hip - y) / Math.max(0.08, level.hip - hemY), 0, 1);
                const hemRx = hipRx * spreadSoft + hemShift;
                // خط A و کلوش بیشترِ گشادی را نزدیک لبه باز می‌کنند
                const curve = spread > 1.05 ? Math.pow(t, 1.35) : t;

                rx = lerp(hipRx, hemRx, curve);
                rz = rx * 0.84;
            }

            return {
                rx: Math.max(body.rx + SKIN_GAP, rx),
                rz: Math.max(body.rz + SKIN_GAP, rz),
                bodyRx: body.rx,
                bodyRz: body.rz,
            };
        };

        /* ---- بالاتنه: از لبه‌ی پایین تا سرشانه، بسته روی شانه، باز روی یقه ---- */
        let bodiceHemY = level.waist;

        if (hasBodice) {
            const torsoLength = (lengths.bodice || 42) / 100;

            bodiceHemY = level.shoulder - torsoLength;

            // اگر دامنِ جدا داریم، بالاتنه سر خط کمر تمام می‌شود
            if (hasSkirtPiece && skirtLength > 0.05) {
                bodiceHemY = Math.max(bodiceHemY, level.waist - 0.03);
            }

            bodiceHemY = clamp(bodiceHemY, level.ankle + 0.05, level.underBust);

            const rings = [];
            const steps = 28;
            // پارچه از سرشانه آویزان است؛ پایین‌تر از کمر رهاست و چین می‌خورد
            const support = level.waist;

            for (let i = 0; i <= steps; i++) {
                const y = lerp(bodiceHemY, level.shoulder, i / steps);

                rings.push({
                    y,
                    ...shellAt(y, bodiceHemY),
                    free: clamp((support - y) / Math.max(0.12, support - bodiceHemY), 0, 1),
                    /*
                     * «دوخته» یعنی این حلقه با بدن حرکت می‌کند و حل‌کننده تکانش
                     * نمی‌دهد. سرشانه و کارور بالای حلقه‌ی آستین روی بدن سوارند؛
                     * از آنجا به پایین پارچه آزاد است و خودش می‌افتد.
                     */
                    pin: y >= level.armhole,
                });
            }

            /*
             * سرشانه تا یقه: چند حلقه که از خط سرشانه به دهانه‌ی یقه می‌رسند.
             * همین سطح، بالاتنه را روی شانه‌ها می‌بندد و فقط یقه را باز می‌گذارد.
             */
            const shoulderRing = rings[rings.length - 1];
            const neckRx = Math.max(r.neck * 1.45, this.bodyAt(level.neck).rx + 0.012);
            const neckRz = Math.max(r.neck * 1.4, this.bodyAt(level.neck).rz + 0.012);
            const capSteps = 5;

            for (let i = 1; i <= capSteps; i++) {
                const t = smooth(i / capSteps);
                const y = lerp(level.shoulder, level.neck - 0.03, i / capSteps);
                const body = this.bodyAt(y);

                rings.push({
                    y,
                    rx: Math.max(body.rx + SKIN_GAP, lerp(shoulderRing.rx, neckRx, t)),
                    rz: Math.max(body.rz + SKIN_GAP, lerp(shoulderRing.rz, neckRz, t)),
                    bodyRx: body.rx,
                    bodyRz: body.rz,
                    free: 0,
                    pin: true,
                });
            }

            // لبه‌ی برگشته‌ی یقه: دهانه به‌جای یک سوراخ، یقه‌ی تمام‌شده دیده شود
            rings.push({
                y: level.neck - 0.012,
                rx: neckRx * 1.13,
                rz: neckRz * 1.13,
                bodyRx: r.neck,
                bodyRz: r.neck,
                free: 0,
                pin: true,
            });

            this.addGarmentMesh(this.settle(rings, drape), drape, 72, 'body');
        }

        /* ---- دامن: از خط کمر (یا لبه‌ی بالاتنه) به پایین ---- */
        if (skirtLength > 0.05) {
            const topY = hasBodice ? Math.min(level.waist + 0.01, bodiceHemY + 0.02) : level.waist + 0.02;
            const hemY = clamp(topY - skirtLength, level.ankle + 0.03, topY - 0.08);

            const rings = [];
            const steps = 26;

            for (let i = 0; i <= steps; i++) {
                const y = lerp(hemY, topY, i / steps);

                rings.push({
                    y,
                    ...shellAt(y, hemY),
                    free: clamp((topY - y) / Math.max(0.1, topY - hemY), 0, 1),
                    // دامن فقط از خط کمر آویزان است؛ بقیه‌اش دست حل‌کننده
                    pin: i === steps,
                });
            }

            // کمربندِ دامنِ تنها: دو حلقه‌ی صاف روی خط کمر
            if (! hasBodice) {
                const top = rings[rings.length - 1];

                [0.012, 0.026].forEach((offset) => {
                    rings.push({
                        y: topY + offset,
                        rx: top.rx * 1.004,
                        rz: top.rz * 1.004,
                        bodyRx: top.bodyRx,
                        bodyRz: top.bodyRz,
                        free: 0,
                        pin: true,
                    });
                });
            }

            this.addGarmentMesh(this.settle(rings, drape), drape, 72, 'body');
        }

        /* ---- آستین‌ها: لوله‌ای روی دست، فرزند گروه دست تا با حالت بدن بچرخند ---- */
        if (sleeveLength > 0.05 && (! hasList || roles.has('sleeve'))) {
            // چین‌های آستین ریزتر از تن است (محیط کوچک‌تر، تعداد قطاع کمتر)
            const sleeveDrape = {
                ...drape,
                lobes: Math.max(4, Math.round(drape.lobes * 0.5)),
                fold: drape.fold * 0.7,
                sag: drape.sag * 0.5,
            };

            const armLength = ctx.armLength;
            const tip = Math.min(sleeveLength, armLength * 0.99);
            const capRadius = r.bicep * 1.02 * 1.16 + gapFor(Math.max(0, ease.armhole ?? 4)) * 0.6;
            const sleeveEase = gapFor(Math.max(0, ease.bicep ?? 5));

            const rings = [];
            const steps = 18;

            // بدنه‌ی آستین: از لبه (پایین) تا حلقه‌ی آستین
            for (let i = 0; i <= steps; i++) {
                const t = i / steps;
                const y = lerp(-tip, -0.012, t);
                const arm = this.armAt(y);
                const rx = arm * lerp(1.07, 1.18, t) + sleeveEase * lerp(0.55, 1, t);

                rings.push({
                    y,
                    rx,
                    rz: rx * 0.95,
                    bodyRx: arm,
                    bodyRz: arm * 0.95,
                    free: (1 - t) * 0.85,
                    // فقط بالاترین حلقه‌ی بدنه‌ی آستین به حلقه‌ی آستین دوخته است
                    pin: i === steps,
                    /*
                     * آستین باید روی بازو بماند: نزدیک سرشانه محکم می‌چسبد و هرچه
                     * به مچ نزدیک‌تر می‌شویم آزادتر می‌شود تا بتواند تاب بخورد.
                     */
                    follow: 0.3 + 0.7 * t,
                });
            }

            /*
             * سرِ آستین: یک گنبد کوتاه که روی مفصل شانه می‌نشیند و درز حلقه‌ی
             * آستین را می‌پوشاند، پس آستین دیگر مثل ورقه‌ی افقی بیرون نمی‌زند.
             */
            [
                [0.006, 0.99],
                [0.02, 0.88],
                [0.032, 0.66],
                [0.04, 0.34],
                [0.045, 0.08],
            ].forEach(([offset, scale]) => {
                rings.push({
                    y: offset,
                    rx: capRadius * scale,
                    rz: capRadius * scale * 0.95,
                    bodyRx: 0,
                    bodyRz: 0,
                    free: 0,
                    pin: true,
                    follow: 1,
                });
            });

            const settled = this.settle(rings, sleeveDrape);

            [ctx.armL, ctx.armR].forEach((group) => {
                this.addGarmentMesh(settled, sleeveDrape, 32, 'sleeve', group);
            });
        }

        this.applyZoneMaterial();
    },

    /*
     * حلقه‌های نشسته را به مش تبدیل می‌کند، رنگ نواحی را می‌زند و به صحنه می‌افزاید.
     *
     * مش موقتاً فرزند همان گروه بدن می‌شود تا مختصاتش با بقیه‌ی مدل هم‌خوان بماند؛
     * buildCloth بعداً آن را به فضای جهانی می‌برد و به حل‌کننده می‌سپارد.
     */
    addGarmentMesh(rings, drape, segments, kind, parent = null) {
        const ctx = contextFor(this.$root);
        const geometry = this.ringSurface(rings.map((spec) => this.ringPoints(spec, segments, drape)));

        this.paintZones(geometry, kind);

        const mesh = new THREE.Mesh(geometry, ctx.fabricMaterial);
        const group = parent || ctx.torso;

        group.add(mesh);
        ctx.disposables.push(geometry);
        ctx.garmentMeshes.push(mesh);

        // نقشه‌ی دوخت: کدام رأس روی بدن سوار است و کدام آزاد
        const pinned = new Uint8Array(rings.length * segments);
        const sticky = rings.some((spec) => spec.follow);
        const follow = sticky ? new Float32Array(rings.length * segments) : null;

        rings.forEach((spec, row) => {
            if (spec.pin) {
                pinned.fill(1, row * segments, (row + 1) * segments);
            }

            if (follow && spec.follow) {
                follow.fill(spec.follow, row * segments, (row + 1) * segments);
            }
        });

        ctx.garmentPatches.push({ mesh, geometry, group, rows: rings.length, segments, pinned, follow });

        return mesh;
    },

    /* جنس پارچه: رنگ، زبری، براقی و شفافیت از شناسنامه‌ی پارچه می‌آید */
    fabricMaterial(fabric, withZones) {
        const sheen = clamp(fabric.sheen ?? 0.15, 0, 1);
        const transparency = clamp(fabric.transparency ?? 0.05, 0, 1);
        // پارچه‌های تقریباً نافذ نباید شفاف شوند، وگرنه مانکن از پشت لباس دیده می‌شود
        const seeThrough = transparency > 0.18;

        return new THREE.MeshStandardMaterial({
            color: withZones ? '#ffffff' : fabric.color || '#b9a48c',
            roughness: clamp(1 - sheen * 0.8, 0.12, 0.95),
            metalness: clamp(sheen * 0.32, 0, 0.35),
            transparent: seeThrough,
            opacity: seeThrough ? clamp(1 - transparency * 0.55, 0.45, 1) : 1,
            side: THREE.DoubleSide,
            vertexColors: !!withZones,
        });
    },

    /* ------------------------------------------------------------------
     * حل‌کننده‌ی پارچه
     * ------------------------------------------------------------------
     * اینجا لباسِ ساخته‌شده به یک سیستم ذره‌ای تبدیل می‌شود:
     *   • هر رأس یک ذره است؛ رأس‌های سرشانه/کمربند/سرِ آستین جرمِ بی‌نهایت دارند
     *     (w = 0) و با بدن حرکت می‌کنند.
     *   • طول استراحت قیدها از همان هندسه‌ی دوخته‌شده گرفته می‌شود، پس فرم الگو
     *     نقطه‌ی تعادل شبیه‌سازی است و لباس از ریخت نمی‌افتد.
     *   • بدن به چند «برخوردگر» تبدیل می‌شود که مستقیم از جدول مقطع بدن و
     *     شعاع اندام‌ها می‌آیند؛ پس ران هیچ‌وقت از دامن بیرون نمی‌زند.
     */
    buildCloth() {
        const ctx = contextFor(this.$root);

        ctx.cloth = [];
        ctx.colliders = [];

        // اول دوختِ واقعی؛ اگر نشد، پوستهٔ پارامتری همان‌جا که هست می‌ماند
        if (this.buildDrapeCloth()) {
            return;
        }

        if (! CLOTH || ! (ctx.garmentPatches || []).length) {
            return;
        }

        // ماتریس‌ها باید تازه باشند تا تبدیل به فضای جهانی درست دربیاید
        ctx.root.updateMatrixWorld(true);
        ctx.tmpMatrix = new THREE.Matrix4();

        ctx.world = new CLOTH.ClothWorld({
            fabric: this.payload.fabric || {},
            skin: SKIN_GAP,
        });

        ctx.garmentPatches.forEach((item) => {
            const positions = item.geometry.attributes.position.array;
            const point = new THREE.Vector3();

            // بردن رأس‌ها از فضای گروه بدن به فضای جهانی و جدا کردن مش از بدن
            for (let i = 0; i < positions.length; i += 3) {
                point.set(positions[i], positions[i + 1], positions[i + 2]).applyMatrix4(item.group.matrixWorld);
                positions[i] = point.x;
                positions[i + 1] = point.y;
                positions[i + 2] = point.z;
            }

            ctx.scene.add(item.mesh);
            item.mesh.matrixAutoUpdate = false;
            item.mesh.matrix.identity();
            item.mesh.matrixWorld.identity();
            // پارچه مدام جابه‌جا می‌شود؛ کره‌ی مرزیِ کهنه نباید باعث حذف آن شود
            item.mesh.frustumCulled = false;

            const patch = ctx.world.addPatch(
                new CLOTH.ClothPatch({
                    positions,
                    rows: item.rows,
                    segments: item.segments,
                    pinned: item.pinned,
                    follow: item.follow,
                    fabric: ctx.world.law,
                }),
            );

            ctx.tmpMatrix.copy(item.group.matrixWorld).invert();
            patch.capturePins(ctx.tmpMatrix.elements);

            item.geometry.attributes.position.needsUpdate = true;
            ctx.cloth.push({ patch, group: item.group, geometry: item.geometry });
        });

        this.buildColliders();
        ctx.world.setColliders(ctx.colliders.map((entry) => entry.collider));
        this.refreshColliders();

        // چند گام پیش از اولین رندر تا صفحه با لباسِ نشسته باز شود
        ctx.world.presettle();

        ctx.cloth.forEach((item) => {
            item.geometry.attributes.position.needsUpdate = true;
            item.geometry.computeVertexNormals();
        });
    },

    /* ------------------------------------------------------------------
     * دوختِ واقعی
     * ------------------------------------------------------------------
     * تفاوتش با پوستهٔ پارامتری در یک جمله: آن‌جا شکلِ لباس از مقطع بدن و
     * آزادی هر ناحیه ساخته می‌شد، این‌جا از خودِ قطعه‌های الگو. هر قطعه
     * مثلث‌بندی می‌شود، دور بدن چیده می‌شود، و درزها و ساسون‌ها آن را جمع
     * می‌کنند. طول استراحتِ قیدها از الگوی تخت می‌آید، پس نقطهٔ تعادل حل‌کننده
     * همان چیزی است که از پارچه بریده می‌شود.
     *
     * اگر بستهٔ الگو نیامده باشد یا مثلث‌بندی شکست بخورد، false برمی‌گرداند و
     * نمای دیروز سر جایش می‌ماند. نمای سه‌بعدی هیچ‌وقت نباید خالی بماند.
     */
    buildDrapeCloth() {
        const ctx = contextFor(this.$root);
        const payload = this.payload.drape;

        if (! CLOTH || ! DRAPE || ! this.trueSeams || ! (payload?.pieces || []).length) {
            return false;
        }

        let drape = null;

        try {
            drape = DRAPE.buildDrape(
                payload,
                {
                    level: ctx.level,
                    radii: ctx.radii,
                    profile: ctx.profile,
                    armTable: ctx.armTable,
                    armLength: ctx.armLength,
                },
                { fabric: this.payload.fabric || {} },
            );
        } catch (error) {
            ctx.drapeError = error?.message || String(error);

            return false;
        }

        if (! drape || ! (drape.patches || []).length) {
            return false;
        }

        // پوستهٔ پارامتری برداشته می‌شود؛ دو لباس روی هم دیده نمی‌شود
        (ctx.garmentMeshes || []).forEach((mesh) => mesh.parent && mesh.parent.remove(mesh));
        ctx.garmentMeshes = [];
        ctx.garmentPatches = [];

        ctx.root.updateMatrixWorld(true);
        ctx.tmpMatrix = new THREE.Matrix4();

        ctx.world = new CLOTH.ClothWorld({
            fabric: this.payload.fabric || {},
            skin: SKIN_GAP,
        });

        if (drape.stats?.solver) {
            ctx.world.substeps = drape.stats.solver.substeps ?? ctx.world.substeps;
            ctx.world.iterations = drape.stats.solver.iterations ?? ctx.world.iterations;
        }

        drape.patches.forEach((item) => {
            const geometry = new THREE.BufferGeometry();

            // بافرِ موقعیت همان بافرِ حل‌کننده است؛ کپی نمی‌شود
            geometry.setAttribute('position', new THREE.BufferAttribute(item.mesh.positions, 3));

            if (item.mesh.uv) {
                geometry.setAttribute('uv', new THREE.BufferAttribute(item.mesh.uv, 2));
            }

            geometry.setIndex(new THREE.BufferAttribute(item.mesh.indices, 1));
            geometry.computeVertexNormals();

            this.paintZones(geometry, item.piece?.placement?.zone === 'sleeve' ? 'sleeve' : 'body');

            const mesh = new THREE.Mesh(geometry, ctx.fabricMaterial);

            mesh.matrixAutoUpdate = false;
            mesh.matrix.identity();
            mesh.matrixWorld.identity();
            mesh.frustumCulled = false;

            ctx.scene.add(mesh);
            ctx.disposables.push(geometry);
            ctx.garmentMeshes.push(mesh);

            ctx.world.addPatch(item.patch);
            ctx.cloth.push({ patch: item.patch, group: ctx.torso, geometry });
        });

        (drape.seams || []).forEach((seam) => ctx.world.addSeam(seam));

        this.buildColliders();
        ctx.world.setColliders(ctx.colliders.map((entry) => entry.collider));
        this.refreshColliders();

        /*
         * لباس مثل روی میز خیاطی دوخته می‌شود، بعد پوشیده.
         *
         * اگر از همان اول وزن روی پارچه باشد، قطعه‌ها پیش از بسته شدن درزها از
         * تن سُر می‌خورند و پایین می‌ریزند — سنجیده شد: با جاذبه از لحظهٔ اول،
         * پیراهن روی زمین جمع می‌شد و هیچ‌چیز روی مانکن نمی‌ماند. پس اول در
         * بی‌وزنی دوخته می‌شود، بعد سرشانه گرفته می‌شود و بعد وزن برمی‌گردد.
         */
        const gravity = ctx.world.law.gravity;
        const iterations = ctx.world.iterations;

        /*
         * هنگام دوختن، تکرارِ بیشتر می‌ارزد.
         *
         * درز فقط یک «هدف» است و هر تکرار کمی نزدیک‌ترش می‌کند؛ با سه تکرار،
         * خط کمر تا آخر بسته نمی‌شد و پارچه کنار درز مچاله می‌ماند. اندازه
         * گرفته شد: با شش تکرار خطای درز از ۷٫۸ به ۵٫۴ سانتی‌متر و بیشترین
         * کشش مثلث از ۴٫۴ به ۲٫۴ برابر می‌رسد. این هزینه فقط یک بار هنگام
         * ساخت پرداخت می‌شود، نه در هر فریم.
         */
        ctx.world.iterations = Math.max(6, iterations);
        ctx.world.law.gravity = 0;
        ctx.world.presettle(drape.stats?.presettle ?? 120);

        ctx.tmpMatrix.copy(ctx.torso.matrixWorld).invert();
        /*
         * لباس از سرشانه آویزان است، نه از هیچ‌جا.
         *
         * با کشش ملایمِ پیش‌فرض، پیراهن زیر وزن خودش از تن سُر می‌خورد و روی
         * پا جمع می‌شود (اندازه گرفته شد: لبهٔ بالا ۲۸ سانتی‌متر پایین می‌آمد).
         * نوارِ بالای هر قطعهٔ تنه محکم گرفته می‌شود — همان کاری که سرشانهٔ
         * واقعی می‌کند.
         */
        DRAPE.supportGarment(drape, { inverse: ctx.tmpMatrix.elements, band: 0.08, strength: 1 });

        // ماتریس گروه بدن باید پیش از اولین گامِ باوزن روی هر تکه باشد، وگرنه
        // «جای اصلیِ» رأس‌ها در فضای محلی خوانده می‌شود و لباس به اندازهٔ
        // جابه‌جاییِ گروه پایین کشیده می‌شود
        ctx.cloth.forEach((item) => item.patch.applyPins(item.group.matrixWorld.elements));

        ctx.world.law.gravity = gravity;
        ctx.world.presettle(40);
        ctx.world.iterations = iterations;

        ctx.cloth.forEach((item) => {
            item.geometry.attributes.position.needsUpdate = true;
            item.geometry.computeVertexNormals();
        });

        ctx.drape = drape;
        this.applyZoneMaterial();

        return true;
    },

    /*
     * برخوردگرهای بدن.
     *
     * هرکدام یک «استوانه‌ی بیضویِ کشیده» روی محور Y محلیِ گروهی است که به آن وصل
     * است؛ پس با چرخیدن ران یا بازو، خودِ برخوردگر هم می‌چرخد و لازم نیست چیزی
     * دوباره ساخته شود.
     *
     * تن از همان bodyProfile می‌آید (از فاق به بالا؛ پایین‌تر از فاق کارِ پاهاست).
     */
    buildColliders() {
        const ctx = contextFor(this.$root);
        const level = ctx.level;
        const r = ctx.radii;
        const H = (this.payload.avatar?.height || 165) / 100;
        const add = (group, sections, name, caps = {}) => {
            ctx.colliders.push({
                group,
                collider: new CLOTH.Collider({ sections, name, ...caps }),
            });
        };

        add(
            ctx.torso,
            ctx.profile.filter(([y]) => y >= level.crotch - 1e-6).map(([y, rx, rz]) => [y, rx, rz]),
            'torso',
        );

        add(
            ctx.torso,
            [
                [level.neck - 0.02, r.neck * 1.05, r.neck * 1.05],
                [level.chin, r.neck * 0.92, r.neck * 0.92],
            ],
            'neck',
        );

        // سر: کره‌ی کشیده‌ی مانکن با سه مقطع تقریب زده می‌شود
        const headR = (H - level.chin) * 0.62;
        const headY = level.chin + (H - level.chin) * 0.55;

        add(
            ctx.torso,
            [
                [headY - headR * 0.95, headR * 0.86 * 0.45, headR * 0.9 * 0.45],
                [headY, headR * 0.86, headR * 0.9],
                [headY + headR * 0.95, headR * 0.86 * 0.45, headR * 0.9 * 0.45],
            ],
            'head',
        );

        // بازوها: از مچ تا سرِ گردِ شانه (همان جدولی که خود بازو از آن ساخته شد)
        const armLength = ctx.armLength;

        [
            [ctx.armL, 'armL'],
            [ctx.armR, 'armR'],
        ].forEach(([group, name]) => {
            add(
                group,
                [
                    [-armLength - r.wrist * 0.5, r.wrist * 1.1, r.wrist * 1.1],
                    [-armLength * 0.55, r.bicep * 0.74, r.bicep * 0.74],
                    [-armLength * 0.12, r.bicep * 1.02, r.bicep * 1.02],
                    [0, r.bicep * 1.06, r.bicep * 1.06],
                    [r.bicep * 0.5, r.bicep * 0.86, r.bicep * 0.86],
                ],
                name,
            );
        });

        // ران‌ها و ساق‌ها؛ همان نقطه‌های چرخانه‌های پا با کمی گشادی
        const thighDrop = level.crotch - level.knee;
        const shinDrop = level.knee - level.ankle;

        [
            [ctx.legL, ctx.kneeL, 'L'],
            [ctx.legR, ctx.kneeR, 'R'],
        ].forEach(([leg, knee, side]) => {
            add(
                leg,
                [
                    [-thighDrop, r.knee * 1.02, r.knee * 1.02],
                    [-thighDrop * 0.5, r.thigh * 0.84, r.thigh * 0.84],
                    [-0.02, r.thigh * 1.02, r.thigh * 1.02],
                    [0, r.thigh * 1.04, r.thigh * 1.04],
                ],
                'thigh' + side,
            );

            add(
                knee,
                [
                    [-shinDrop, r.ankle * 1.1, r.ankle * 1.1],
                    [-shinDrop * 0.6, r.ankle * 1.42, r.ankle * 1.42],
                    [0, r.knee * 0.98, r.knee * 0.98],
                ],
                'shin' + side,
            );
        });
    },

    /* ماتریس جهانی هر برخوردگر و جعبه‌ی مرزی‌اش را تازه می‌کند (یک بار در فریم) */
    refreshColliders() {
        const ctx = contextFor(this.$root);

        (ctx.colliders || []).forEach(({ group, collider }) => {
            ctx.tmpMatrix.copy(group.matrixWorld).invert();
            collider.setTransform(group.matrixWorld.elements, ctx.tmpMatrix.elements, 0.03);
        });
    },

    /* ------------------------------------------------------------------
     * شکل اولیه‌ی پارچه پیش از تحویل به حل‌کننده
     * ------------------------------------------------------------------
     * حل‌کننده از یک نقطه‌ی شروع خوب سریع‌تر می‌نشیند، پس همان سه اثر قدیمی
     * به‌عنوان «فرم اولیه» می‌مانند:
     *   • چسبیدن  (cling) پارچه‌ی لخت خودش را به بدن می‌چسباند.
     *   • افتادگی (sag)   وزن پارچه لبه را پایین می‌کشد.
     *   • چین     (fold)  نرمی خمش، موج‌های عمودی می‌سازد.
     * این موج‌های ریز تقارن استوانه را هم می‌شکنند؛ بدون آن‌ها پارچه‌ی لخت
     * هیچ‌وقت تصمیم نمی‌گیرد به کدام سمت چین بخورد.
     */
    drapeOf(fabric, physics) {
        const drape = clamp(fabric.drape ?? 0.5, 0, 1);
        const weight = clamp((physics.weight ?? 0.15) * 3.5, 0, 1);
        // سفتی خمش، هم دامنه‌ی چین و هم چسبیدن را کم می‌کند
        const softness = 1 / (1 + clamp(physics.bending ?? 0.12, 0, 1) * 12);

        return {
            drape,
            softness,
            lobes: Math.round(6 + drape * 10),
            phase: 1.7,
            fold: 0.13 * drape * softness,
            cling: 0.34 * drape * softness,
            sag: 0.05 * weight * drape + 0.008,
        };
    },

    /*
     * سه اثر بالا روی حلقه‌ها اعمال می‌شود (نه روی رأس‌ها) چون در این مرحله
     * می‌دانیم مقطع بدن زیر هر حلقه چقدر است و پارچه هرگز داخل بدن نمی‌رود.
     * spec.free یعنی «چقدر این حلقه از درز نگه‌دارنده دور است»: ۰ روی سرشانه یا
     * کمربند، ۱ روی لبه‌ی پایین.
     */
    settle(rings, drape) {
        return rings.map((spec) => {
            const free = clamp(spec.free ?? 0, 0, 1);
            const pull = drape.cling * free;
            const bodyRx = spec.bodyRx ?? 0;
            const bodyRz = spec.bodyRz ?? 0;

            return {
                ...spec,
                rx: Math.max(bodyRx + SKIN_GAP, spec.rx - (spec.rx - bodyRx) * pull),
                rz: Math.max(bodyRz + SKIN_GAP, spec.rz - (spec.rz - bodyRz) * pull),
                y: spec.y - drape.sag * free * free,
                fold: drape.fold * Math.pow(free, 1.3),
            };
        });
    },

    /* ------------------------------------------------------------------
     * رنگ‌آمیزی نواحی تناسب روی لباس
     * ------------------------------------------------------------------ */
    paintZones(geometry, kind) {
        const level = contextFor(this.$root).level;
        const map = {};

        this.zones.forEach((zone) => {
            map[zone.key] = zone.color || '#16a34a';
        });

        const green = '#16a34a';

        geometry.computeBoundingBox();
        const minY = geometry.boundingBox.min.y;
        const maxY = geometry.boundingBox.max.y;

        const anchors =
            kind === 'sleeve'
                ? [
                      [maxY, map.armhole || map.bust || green],
                      [-0.02, map.armhole || map.bicep || green],
                      [minY, map.bicep || green],
                  ]
                : [
                      [minY, map.hem || map.hip || green],
                      [level.hip, map.hip || green],
                      [level.waist, map.waist || green],
                      [level.bust, map.bust || green],
                      [level.shoulder, map.shoulder || green],
                      [maxY, map.shoulder || green],
                  ];

        const sorted = anchors
            .filter(([y]) => y >= minY - 1e-6 && y <= maxY + 1e-6)
            .map(([y, color]) => ({ y, color: new THREE.Color(color) }))
            .sort((a, b) => a.y - b.y);

        if (sorted.length === 0) {
            sorted.push({ y: minY, color: new THREE.Color(map.bust || green) });
        }

        const position = geometry.attributes.position;
        const colors = new Float32Array(position.count * 3);
        const mixed = new THREE.Color();

        for (let i = 0; i < position.count; i++) {
            const y = position.getY(i);
            let lower = sorted[0];
            let upper = sorted[sorted.length - 1];

            for (let j = 0; j < sorted.length - 1; j++) {
                if (y >= sorted[j].y && y <= sorted[j + 1].y) {
                    lower = sorted[j];
                    upper = sorted[j + 1];
                    break;
                }
            }

            const range = Math.max(0.0001, upper.y - lower.y);
            const t = clamp((y - lower.y) / range, 0, 1);

            mixed.copy(lower.color).lerp(upper.color, t);
            colors[i * 3] = mixed.r;
            colors[i * 3 + 1] = mixed.g;
            colors[i * 3 + 2] = mixed.b;
        }

        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
    },

    applyZoneMaterial() {
        const ctx = contextFor(this.$root);

        (ctx.garmentMeshes || []).forEach((mesh) => {
            mesh.material = this.showZones ? ctx.zoneMaterial : ctx.fabricMaterial;
        });
    },

    toggleZones() {
        this.showZones = !this.showZones;
        this.applyZoneMaterial();
    },

    /*
     * جابه‌جایی میان دوختِ واقعی و پوستهٔ پارامتری.
     *
     * لباس از نو ساخته می‌شود چون این دو دو هندسهٔ کاملاً متفاوت‌اند: یکی
     * قطعه‌های مثلث‌بندی‌شده‌ی دوخته‌شده و دیگری چند حلقهٔ افقی دور بدن.
     */
    toggleTrueSeams() {
        if (! this.canDrape) {
            return;
        }

        this.trueSeams = ! this.trueSeams;

        const ctx = contextFor(this.$root);

        (ctx.garmentMeshes || []).forEach((mesh) => mesh.parent && mesh.parent.remove(mesh));
        ctx.garmentMeshes = [];
        ctx.garmentPatches = [];
        ctx.world = null;
        ctx.drape = null;

        this.buildGarment();
        this.buildCloth();
        this.applyPose(this.pose);
        this.applyZoneMaterial();
    },

    /* آیا برای این الگو اصلاً بستهٔ دوخت آمده است؟ */
    get canDrape() {
        return !! (this.payload.drape?.pieces || []).length;
    },

    /*
     * شبیه‌سازی زنده.
     *
     * خاموش که باشد، پارچه شکل همان لحظه را نگه می‌دارد و فقط مثل یک پوسته‌ی
     * سفت با بدن حرکت می‌کند — درست همان رفتار قبلی. روشن که شود، لباس دوباره
     * زیر وزن خودش می‌افتد و روی بدن می‌نشیند.
     */
    toggleLive() {
        this.liveSim = !this.liveSim;

        const ctx = contextFor(this.$root);

        if (! ctx.world) {
            return;
        }

        ctx.world.enabled = this.liveSim;

        if (this.liveSim) {
            ctx.world.wake();

            return;
        }

        ctx.root.updateMatrixWorld(true);

        ctx.cloth.forEach((item) => {
            ctx.tmpMatrix.copy(item.group.matrixWorld).invert();
            item.patch.freeze(ctx.tmpMatrix.elements);
        });
    },

    /* ------------------------------------------------------------------
     * حالت‌های بدن
     * ------------------------------------------------------------------
     * تغییر حالت پرشی نیست: زاویه‌ها در طول POSE_DURATION از حالت فعلی به حالت
     * تازه می‌روند. پارچه چون فقط از رأس‌های دوخته‌شده کشیده می‌شود، خودش با
     * تأخیر دنبال بدن می‌افتد و همان «دوباره نشستن» دیده می‌شود.
     */
    poseAngles(pose) {
        return {
            spin: 0,
            drop: 0,
            torso: 0,
            armL: 0,
            armR: 0,
            armLZ: -8,
            armRZ: 8,
            legL: 0,
            legR: 0,
            knee: 0,
            ...(POSES[pose] || {}),
        };
    },

    setPose(pose) {
        this.pose = pose;
        this.applyPose(pose);
    },

    applyPose(pose) {
        const ctx = contextFor(this.$root);

        if (! ctx.root) {
            return;
        }

        ctx.poseNow ??= this.poseAngles('stand');
        ctx.poseFrom = { ...ctx.poseNow };
        ctx.poseTo = this.poseAngles(pose);
        ctx.poseT = 0;

        ctx.world?.wake();
        this.advancePose(0);
    },

    /* یک قدم از گذر حالت؛ خروجی true یعنی هنوز در حال حرکت است */
    advancePose(dt) {
        const ctx = contextFor(this.$root);

        if (! ctx.poseTo || ctx.poseT >= 1) {
            return false;
        }

        ctx.poseT = Math.min(1, ctx.poseT + (POSE_DURATION > 0 ? dt / POSE_DURATION : 1));

        const t = smooth(ctx.poseT);
        const now = {};

        Object.keys(ctx.poseTo).forEach((key) => {
            now[key] = lerp(ctx.poseFrom[key] ?? 0, ctx.poseTo[key], t);
        });

        ctx.poseNow = now;
        this.writePose(now);

        return ctx.poseT < 1;
    },

    /* نوشتن زاویه‌ها روی گروه‌های بدن */
    writePose(p) {
        const ctx = contextFor(this.$root);

        ctx.root.rotation.y = p.spin * RAD;
        ctx.root.position.y = p.drop;
        ctx.spine.rotation.x = p.torso * RAD;

        ctx.armL.rotation.set(p.armL * RAD, 0, p.armLZ * RAD);
        ctx.armR.rotation.set(p.armR * RAD, 0, p.armRZ * RAD);

        ctx.legL.rotation.x = p.legL * RAD;
        ctx.legR.rotation.x = p.legR * RAD;
        ctx.kneeL.rotation.x = p.knee * RAD;
        ctx.kneeR.rotation.x = p.knee * RAD;
    },

    /* ------------------------------------------------------------------
     * دوربین: نمای جلو/پهلو/پشت، چرخش خودکار و چرخش دستی
     * ------------------------------------------------------------------ */
    setView(view) {
        const angles = { front: 0, side: 90, back: 180 };

        contextFor(this.$root).orbit.yaw = (angles[view] ?? 0) * RAD;
        this.updateCamera();
    },

    toggleRotate() {
        this.autoRotate = !this.autoRotate;
    },

    updateCamera() {
        const ctx = contextFor(this.$root);

        if (!ctx.camera) {
            return;
        }

        const { orbit } = ctx;
        const x = orbit.distance * Math.sin(orbit.yaw) * Math.cos(orbit.pitch);
        const y = orbit.target.y + orbit.distance * Math.sin(orbit.pitch);
        const z = orbit.distance * Math.cos(orbit.yaw) * Math.cos(orbit.pitch);

        ctx.camera.position.set(x, y, z);
        ctx.camera.lookAt(orbit.target);
    },

    /* چرخش با ماوس و لمس، و بزرگ‌نمایی با غلتک — دست‌نویس و کوتاه */
    bindPointer() {
        const ctx = contextFor(this.$root);
        const canvas = ctx.renderer.domElement;
        let dragging = false;
        let lastX = 0;
        let lastY = 0;

        const down = (event) => {
            dragging = true;
            lastX = event.clientX;
            lastY = event.clientY;
            canvas.setPointerCapture?.(event.pointerId);
        };

        const move = (event) => {
            if (!dragging) {
                return;
            }

            ctx.orbit.yaw -= (event.clientX - lastX) * 0.008;
            ctx.orbit.pitch = Math.max(-0.9, Math.min(1.2, ctx.orbit.pitch + (event.clientY - lastY) * 0.005));
            lastX = event.clientX;
            lastY = event.clientY;
            this.updateCamera();
        };

        const up = () => {
            dragging = false;
        };

        const wheel = (event) => {
            event.preventDefault();
            ctx.orbit.distance = Math.max(1.2, Math.min(7, ctx.orbit.distance + event.deltaY * 0.0022));
            this.updateCamera();
        };

        canvas.addEventListener('pointerdown', down);
        canvas.addEventListener('pointermove', move);
        canvas.addEventListener('pointerup', up);
        canvas.addEventListener('pointerleave', up);
        canvas.addEventListener('wheel', wheel, { passive: false });

        ctx.unbindPointer = () => {
            canvas.removeEventListener('pointerdown', down);
            canvas.removeEventListener('pointermove', move);
            canvas.removeEventListener('pointerup', up);
            canvas.removeEventListener('pointerleave', up);
            canvas.removeEventListener('wheel', wheel);
        };
    },

    observeResize() {
        const ctx = contextFor(this.$root);
        const stage = this.$refs.stage;

        const resize = () => {
            const width = stage.clientWidth || 480;
            const height = stage.clientHeight || 360;

            ctx.camera.aspect = width / height;
            ctx.camera.updateProjectionMatrix();
            ctx.renderer.setSize(width, height);
        };

        if (window.ResizeObserver) {
            ctx.resizeObserver = new ResizeObserver(resize);
            ctx.resizeObserver.observe(stage);
        } else {
            ctx.onResize = resize;
            window.addEventListener('resize', resize);
        }
    },

    /*
     * حلقه‌ی نمایش.
     *
     * ترتیب کارها مهم است: اول بدن به حالت تازه‌اش می‌رود، بعد برخوردگرها و
     * رأس‌های دوخته‌شده با همان ماتریس‌ها به‌روز می‌شوند و آخر پارچه یک گام جلو
     * می‌رود. اگر پارچه خوابیده باشد و حالتی هم عوض نشده باشد، هیچ‌کدام از این‌ها
     * اجرا نمی‌شود و فریم فقط یک render ساده است.
     */
    loop() {
        const ctx = contextFor(this.$root);
        const clock = () => (typeof performance !== 'undefined' ? performance.now() : Date.now());
        let last = clock();

        const frame = () => {
            if (!ctx.renderer) {
                return;
            }

            const time = clock();
            const dt = Math.min(0.05, (time - last) / 1000);

            last = time;

            if (this.autoRotate) {
                ctx.orbit.yaw += dt * 0.3;
                this.updateCamera();
            }

            const moving = this.advancePose(dt);

            if (ctx.world && ! this.liveSim) {
                // شبیه‌سازی خاموش: پارچه فقط با بدن جابه‌جا می‌شود
                if (moving) {
                    ctx.root.updateMatrixWorld(true);

                    ctx.cloth.forEach((item) => {
                        item.patch.applyFrozen(item.group.matrixWorld.elements);
                        item.geometry.attributes.position.needsUpdate = true;
                        item.geometry.computeVertexNormals();
                    });
                }
            } else if (ctx.world && (moving || ! ctx.world.sleeping)) {
                if (moving) {
                    ctx.world.wake();
                }

                ctx.root.updateMatrixWorld(true);
                this.refreshColliders();

                ctx.cloth.forEach((item) => item.patch.applyPins(item.group.matrixWorld.elements));

                if (ctx.world.update(dt) > 0) {
                    ctx.cloth.forEach((item) => {
                        item.geometry.attributes.position.needsUpdate = true;
                        item.geometry.computeVertexNormals();
                    });
                }

                this.report(ctx, time);
            }

            ctx.renderer.render(ctx.scene, ctx.camera);
            ctx.frame = requestAnimationFrame(frame);
        };

        frame();
    },

    /*
     * قلاب اشکال‌زدایی.
     *
     * چند عدد فقط‌خواندنی روی window می‌گذارد تا اگر کاربری از کندی صفحه گزارش
     * داد، بشود بدون ابزار خاصی پرسید window.dokhtCloth چه می‌گوید: هزینه‌ی هر
     * گام، تعداد ذره‌ها، سطح کیفیت و اینکه پارچه خوابیده یا نه. هیچ چیزی از این
     * شیء خوانده نمی‌شود؛ فقط نوشته می‌شود.
     */
    report(ctx, time) {
        if (typeof window === 'undefined' || ! ctx.world) {
            return;
        }

        const stats = (window.dokhtCloth ??= {});

        stats.solverMs = Math.round(ctx.world.cost * 100) / 100;
        stats.frameMs = Math.round((time - (stats.at || time)) * 100) / 100;
        stats.at = time;
        stats.particles = ctx.world.particles;
        stats.constraints = ctx.world.constraints;
        stats.quality = ctx.world.quality;
        stats.sleeping = ctx.world.sleeping;
        stats.energy = ctx.world.energy;
        stats.drift = ctx.world.drift;
        stats.pieces = ctx.cloth.length;
        stats.meshes = (ctx.garmentMeshes || []).length;
        stats.trueSeams = !! ctx.drape;

        if (ctx.drape) {
            stats.seams = ctx.drape.stats?.seams ?? 0;
            stats.darts = ctx.drape.stats?.darts ?? 0;
            stats.stitches = ctx.drape.stats?.stitches ?? 0;
            stats.seamError = ctx.world.seamError ? ctx.world.seamError() : null;
            stats.drapeWarnings = ctx.drape.stats?.warnings ?? [];
        }

        if (ctx.drapeError) {
            stats.drapeError = ctx.drapeError;
        }
    },

    /* ------------------------------------------------------------------
     * پاک‌سازی: هندسه‌ها و جنس‌ها آزاد و شنونده‌ها برداشته می‌شوند
     * ------------------------------------------------------------------ */
    teardown() {
        const ctx = contexts.get(this.$root);

        if (!ctx) {
            return;
        }

        cancelAnimationFrame(ctx.frame);
        ctx.unbindPointer?.();
        ctx.resizeObserver?.disconnect();

        if (ctx.onResize) {
            window.removeEventListener('resize', ctx.onResize);
        }

        (ctx.disposables || []).forEach((item) => item?.dispose?.());

        // آرایه‌های بزرگ حل‌کننده هم رها می‌شوند تا حافظه پس از خروج آزاد شود
        ctx.world = null;
        ctx.cloth = null;
        ctx.colliders = null;
        ctx.garmentPatches = null;
        ctx.garmentMeshes = null;

        ctx.renderer?.dispose();
        ctx.renderer?.domElement?.remove();

        contexts.delete(this.$root);
    },

    /* نقطه‌های کلیدی را به یک خط نرم تبدیل می‌کند (ورودی چرخانه‌های دست و پا) */
    spline(points) {
        const vectors = points.map(([x, y]) => new THREE.Vector2(Math.max(0.005, x), y));

        if (vectors.length < 3) {
            return vectors;
        }

        return new THREE.SplineCurve(vectors).getPoints(Math.max(18, vectors.length * 5));
    },
});
