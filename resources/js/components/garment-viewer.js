/*
 * نمای سه‌بعدی لباس روی مانکن.
 *
 * این فایل چهار کار انجام می‌دهد:
 *   ۱) از اندازه‌های بدن یک مانکن پارامتری می‌سازد (تن، گردن، سر، دست‌ها، پاها).
 *   ۲) روی همان مانکن لباسی می‌پوشاند که فرم و قدش از الگو می‌آید.
 *   ۳) با یک پاس ساده پارچه را «می‌نشاند» تا افتادگی و چین آن دیده شود.
 *   ۴) نواحی تنگ و گشاد را با رنگ روی لباس نشان می‌دهد.
 *
 * نکتهٔ کلیدی شکل‌گیری: بدن و لباس هر دو از یک جدول مشترک «مقطع بدن» ساخته
 * می‌شوند (bodyProfile). هر مقطع یک بیضی افقی است با نیم‌پهنا (rx) و نیم‌عمق (rz)
 * جدا از هم؛ چون بدن از پهلو گشاد و از جلو به عقب باریک است و سرشانه پهن ولی
 * کم‌عمق است. لباس همان جدول را با «آزادی» هر ناحیه می‌خواند، پس هیچ‌وقت از بدن
 * جدا نمی‌افتد و هیچ‌وقت هم داخل بدن فرو نمی‌رود.
 *
 * هیچ کتابخانه‌ی کمکی لازم نیست؛ چرخش با ماوس هم دستی نوشته شده است.
 */

const RAD = Math.PI / 180;

/* کمترین فاصلهٔ پارچه از پوست (متر)؛ جلوی فرورفتن لباس در بدن را می‌گیرد */
const SKIN_GAP = 0.006;

/*
 * کتابخانه سه‌بعدی فقط زمانی دانلود می‌شود که کاربر به صفحه نمای سه‌بعدی برسد؛
 * بقیه صفحه‌ها نباید هزینه این حجم را بپردازند.
 */
let THREE = null;

/*
 * اشیای three بیرون از حالت واکنشی آلپاین نگه داشته می‌شوند.
 *
 * اگر یک شیء three از داخل دادهٔ آلپاین خوانده شود، در یک Proxy واکنشی پیچیده
 * می‌شود و three روی ویژگی‌های فقط‌خواندنی مثل modelViewMatrix خطا می‌دهد و صحنه
 * سیاه می‌ماند. WeakMap زیر همین مسیر را دور می‌زند.
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

        const ctx = contextFor(this.$el);
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
        this.applyPose(this.pose);
        this.updateCamera();
        this.bindPointer();
        this.observeResize();
        this.loop();
    },

    /* نور: یک نور اصلی نرم، یک نور پرکننده و کمی نور محیط */
    addLights() {
        const ctx = contextFor(this.$el);

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
        const ctx = contextFor(this.$el);
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
        const ctx = contextFor(this.$el);
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
        const [rx, rz] = sample(contextFor(this.$el).profile, y);

        return { rx, rz };
    },

    /* شعاع بازو در ارتفاع محلیِ گروه دست (۰ روی سرشانه، منفی به سمت مچ) */
    armAt(y) {
        return sample(contextFor(this.$el).armTable, y)[0];
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

        for (let j = 0; j < segments; j++) {
            const angle = (j / segments) * Math.PI * 2;
            // فاز با ارتفاع کمی می‌چرخد تا چین‌ها مثل پارچه‌ی واقعی مورب بیفتند
            const wave = Math.sin(angle * lobes + spec.y * 6 + phase);
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
        const ctx = contextFor(this.$el);
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
         * مقطع پوسته‌ی لباس در هر ارتفاع.
         *   بالاتر از باسن → مقطع بدن + آزادی همان ناحیه (لباس بدن را دنبال می‌کند)
         *   پایین‌تر از باسن → از خط باسن به سمت گشادی لبه باز (یا تنگ) می‌شود
         */
        /*
         * پهنای سرشانه‌ی لباس. لباس روی سرشانه تا نوک شانه می‌رسد و از آنجا آستین
         * شروع می‌شود؛ پس پوسته باید از خط سینه به بالا یکنواخت پهن شود. اگر این
         * کار را نکنیم بین حلقهٔ آستین و سرشانه یک طاقچهٔ افقی می‌ماند که مثل
         * بالشتک شانه بیرون می‌زند.
         */
        const shoulderRx = r.shoulder * 0.95 + easeAt(level.shoulder);
        const bustRx = this.bodyAt(level.bust).rx + easeAt(level.bust);

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
                });
            });

            const settled = this.settle(rings, sleeveDrape);

            [ctx.armL, ctx.armR].forEach((group) => {
                this.addGarmentMesh(settled, sleeveDrape, 32, 'sleeve', group);
            });
        }

        this.applyZoneMaterial();
    },

    /* حلقه‌های نشسته را به مش تبدیل می‌کند، رنگ نواحی را می‌زند و به صحنه می‌افزاید */
    addGarmentMesh(rings, drape, segments, kind, parent = null) {
        const ctx = contextFor(this.$el);
        const geometry = this.ringSurface(rings.map((spec) => this.ringPoints(spec, segments, drape)));

        this.paintZones(geometry, kind);

        const mesh = new THREE.Mesh(geometry, ctx.fabricMaterial);

        (parent || ctx.torso).add(mesh);
        ctx.disposables.push(geometry);
        ctx.garmentMeshes.push(mesh);

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
     * نشستن پارچه
     * ------------------------------------------------------------------
     * حل‌کننده‌ی واقعی نداریم؛ سه اثر از شناسنامه‌ی فیزیکی پارچه ساخته می‌شود:
     *   • چسبیدن  (cling) پارچه‌ی لخت خودش را به بدن می‌چسباند.
     *   • افتادگی (sag)   وزن پارچه لبه را پایین می‌کشد.
     *   • چین     (fold)  نرمی خمش، موج‌های عمودی می‌سازد.
     * پس حریر موج می‌خورد و به بدن می‌نشیند ولی گاباردین صاف و ایستا می‌ماند.
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
        const level = contextFor(this.$el).level;
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
        const ctx = contextFor(this.$el);

        (ctx.garmentMeshes || []).forEach((mesh) => {
            mesh.material = this.showZones ? ctx.zoneMaterial : ctx.fabricMaterial;
        });
    },

    toggleZones() {
        this.showZones = !this.showZones;
        this.applyZoneMaterial();
    },

    /* ------------------------------------------------------------------
     * حالت‌های بدن
     * ------------------------------------------------------------------ */
    setPose(pose) {
        this.pose = pose;
        this.applyPose(pose);
    },

    applyPose(pose) {
        const ctx = contextFor(this.$el);

        if (!ctx.root) {
            return;
        }

        const p = POSES[pose] || {};

        ctx.root.rotation.y = (p.spin || 0) * RAD;
        ctx.root.position.y = p.drop || 0;
        ctx.spine.rotation.x = (p.torso || 0) * RAD;

        ctx.armL.rotation.set((p.armL || 0) * RAD, 0, (p.armLZ || -8) * RAD);
        ctx.armR.rotation.set((p.armR || 0) * RAD, 0, (p.armRZ || 8) * RAD);

        ctx.legL.rotation.x = (p.legL || 0) * RAD;
        ctx.legR.rotation.x = (p.legR || 0) * RAD;
        ctx.kneeL.rotation.x = (p.knee || 0) * RAD;
        ctx.kneeR.rotation.x = (p.knee || 0) * RAD;
    },

    /* ------------------------------------------------------------------
     * دوربین: نمای جلو/پهلو/پشت، چرخش خودکار و چرخش دستی
     * ------------------------------------------------------------------ */
    setView(view) {
        const angles = { front: 0, side: 90, back: 180 };

        contextFor(this.$el).orbit.yaw = (angles[view] ?? 0) * RAD;
        this.updateCamera();
    },

    toggleRotate() {
        this.autoRotate = !this.autoRotate;
    },

    updateCamera() {
        const ctx = contextFor(this.$el);

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
        const ctx = contextFor(this.$el);
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
        const ctx = contextFor(this.$el);
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

    loop() {
        const ctx = contextFor(this.$el);

        const frame = () => {
            if (!ctx.renderer) {
                return;
            }

            if (this.autoRotate) {
                ctx.orbit.yaw += 0.005;
                this.updateCamera();
            }

            ctx.renderer.render(ctx.scene, ctx.camera);
            ctx.frame = requestAnimationFrame(frame);
        };

        frame();
    },

    /* ------------------------------------------------------------------
     * پاک‌سازی: هندسه‌ها و جنس‌ها آزاد و شنونده‌ها برداشته می‌شوند
     * ------------------------------------------------------------------ */
    teardown() {
        const ctx = contexts.get(this.$el);

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

        ctx.renderer?.dispose();
        ctx.renderer?.domElement?.remove();

        contexts.delete(this.$el);
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
