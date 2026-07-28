/*
 * نمای سه‌بعدی لباس روی مانکن.
 *
 * این فایل چهار کار انجام می‌دهد:
 *   ۱) از اندازه‌های بدن یک مانکن پارامتری می‌سازد (تن، گردن، سر، دست‌ها، پاها).
 *   ۲) روی همان مانکن لباسی می‌پوشاند که فرم و قدش از الگو می‌آید.
 *   ۳) با چند تکرار ساده پارچه را «می‌نشاند» تا افتادگی و چین آن دیده شود.
 *   ۴) نواحی تنگ و گشاد را با رنگ روی لباس نشان می‌دهد.
 *
 * هیچ کتابخانه‌ی کمکی لازم نیست؛ چرخش با ماوس هم دستی نوشته شده است.
 */

const RAD = Math.PI / 180;

/*
 * کتابخانه سه‌بعدی فقط زمانی دانلود می‌شود که کاربر به صفحه نمای سه‌بعدی برسد؛
 * بقیه صفحه‌ها نباید هزینه این حجم را بپردازند.
 */
let THREE = null;

const loadThree = async () => {
    THREE ??= await import('three');

    return THREE;
};

/* تبدیل دور (سانتی‌متر) به شعاع در مقیاس صحنه (متر) */
const radius = (girthCm) => Math.max(0.02, girthCm / (2 * Math.PI) / 100);

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

    /* ظرف نگه‌داری اشیای three؛ روی خود المان می‌نشیند تا آلپاین آن را رهگیری نکند */
    get ctx() {
        if (!this.$el._garmentViewer) {
            this.$el._garmentViewer = {};
        }

        return this.$el._garmentViewer;
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

        const ctx = this.ctx;
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
        const ctx = this.ctx;

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
        const ctx = this.ctx;
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
            bicep: radius(avatar.bicep || 28),
            wrist: radius(avatar.wrist || 16),
            thigh: radius(avatar.thigh || 56),
            knee: radius(avatar.knee || 37),
            ankle: radius(avatar.ankle || 23),
            shoulder: (avatar.shoulder_width || 39) / 100 / 2,
        };

        ctx.level = level;
        ctx.radii = r;

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

        ctx.scene.add(ctx.root);
        ctx.root.add(ctx.torso, ctx.legL, ctx.legR);
        ctx.torso.add(ctx.armL, ctx.armR);

        // ---- تن: یک چرخانه از خط باسن تا گردن ----
        const torsoMesh = new THREE.Mesh(
            new THREE.LatheGeometry(
                this.spline([
                    [r.hip * 0.92, level.crotch],
                    [r.hip, level.hip],
                    [r.highHip, level.highHip],
                    [r.waist, level.waist],
                    [r.underBust, level.underBust],
                    [r.bust, level.bust],
                    [r.bust * 0.94, level.armhole],
                    [r.shoulder * 0.92, level.shoulder],
                    [r.neck * 1.1, level.neck],
                ]),
                40,
            ),
            skin,
        );
        torsoMesh.scale.z = 0.74; // بدن از جلو به عقب باریک‌تر از پهلو است
        ctx.torso.add(torsoMesh);
        ctx.disposables.push(torsoMesh.geometry);

        // ---- گردن و سر ----
        const neck = new THREE.Mesh(
            new THREE.CylinderGeometry(r.neck * 0.9, r.neck * 1.05, Math.max(0.03, level.chin - level.neck), 20),
            skin,
        );
        neck.position.y = (level.neck + level.chin) / 2;
        ctx.torso.add(neck);
        ctx.disposables.push(neck.geometry);

        const head = new THREE.Mesh(new THREE.SphereGeometry((H - level.chin) * 0.62, 28, 20), skin);
        head.position.y = level.chin + (H - level.chin) * 0.55;
        head.scale.set(0.86, 1.05, 0.9);
        ctx.torso.add(head);
        ctx.disposables.push(head.geometry);

        // ---- دست‌ها: هر دست یک گروه با لولا روی سرشانه ----
        const armLength = (avatar.arm_length || 58) / 100;

        [
            [ctx.armL, 1],
            [ctx.armR, -1],
        ].forEach(([group, side]) => {
            group.position.set(side * r.shoulder * 0.94, level.shoulder - 0.03, 0);

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
     * لباس
     * ------------------------------------------------------------------ */
    buildGarment() {
        const ctx = this.ctx;
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

        // آزادی هر ناحیه به شعاع تبدیل می‌شود (فاصله‌ی پارچه از بدن)
        const gap = (key, fallback) => radius(Math.max(0, ease[key] ?? fallback) + 0.8) - radius(0.8);

        const silhouette = garment.silhouette || 'straight';
        const flare = { fitted: 0, straight: 0.35, a_line: 0.8, flared: 1.6 }[silhouette] ?? 0.35;
        const bodiceBottom = Math.max(level.hip * 0.5, level.shoulder - (lengths.bodice || 42) / 100);

        // ---- بالاتنه: همان فرم بدن با آزادی، از پایین بالاتنه تا سرشانه ----
        const bodice = new THREE.Mesh(
            new THREE.LatheGeometry(
                this.spline(
                    [
                        [r.bust + gap('bust', 6) + flare * 0.035, bodiceBottom],
                        [r.waist + gap('waist', 4) + flare * 0.02, level.waist],
                        [r.underBust + gap('bust', 6) * 0.9, level.underBust],
                        [r.bust + gap('bust', 6), level.bust],
                        [r.bust * 0.95 + gap('armhole', 4) * 0.6, level.armhole],
                        [r.shoulder * 0.95 + gap('shoulder', 1), level.shoulder],
                        [r.neck * 1.35, level.neck - 0.02],
                    ].filter(([, y], index) => index === 0 || y > bodiceBottom),
                ),
                44,
            ),
            ctx.fabricMaterial,
        );
        bodice.scale.z = 0.78;
        this.settle(bodice.geometry, physics, fabric, bodiceBottom, level.shoulder);
        this.paintZones(bodice.geometry, 'body');
        ctx.torso.add(bodice);
        ctx.disposables.push(bodice.geometry);
        ctx.garmentMeshes.push(bodice);

        // ---- دامن: مخروط گشاد؛ گشادی از فرم لباس و نرمی از لختی پارچه ----
        const skirtLength = (lengths.skirt || 0) / 100;

        if (skirtLength > 0.05) {
            const topY = bodiceBottom;
            const bottomY = Math.max(0.06, topY - skirtLength);
            const softness = 0.4 + (fabric.drape ?? 0.5) * 0.8;
            const topR = r.hip + gap('hip', 6);
            const bottomR = topR * (1 + flare * softness) + gap('hem', 8);

            const skirt = new THREE.Mesh(
                new THREE.LatheGeometry(
                    this.spline([
                        [bottomR, bottomY],
                        [topR + (bottomR - topR) * 0.45, bottomY + skirtLength * 0.45],
                        [topR, topY],
                    ]),
                    48,
                ),
                ctx.fabricMaterial,
            );
            skirt.scale.z = 0.86;
            this.settle(skirt.geometry, physics, fabric, bottomY, topY);
            this.paintZones(skirt.geometry, 'body');
            ctx.torso.add(skirt);
            ctx.disposables.push(skirt.geometry);
            ctx.garmentMeshes.push(skirt);
        }

        // ---- آستین‌ها: فرزند گروه دست تا با حالت‌های بدن حرکت کنند ----
        const sleeveLength = (lengths.sleeve || 0) / 100;

        if (sleeveLength > 0.05) {
            [ctx.armL, ctx.armR].forEach((group) => {
                const sleeve = new THREE.Mesh(
                    new THREE.LatheGeometry(
                        this.spline([
                            [r.wrist * 1.25 + gap('bicep', 5) * 0.4, -sleeveLength],
                            [r.bicep * 0.9 + gap('bicep', 5) * 0.7, -sleeveLength * 0.5],
                            [r.bicep + gap('bicep', 5), -0.05],
                            [r.bust * 0.42 + gap('armhole', 4), 0.02],
                        ]),
                        24,
                    ),
                    ctx.fabricMaterial,
                );
                this.settle(sleeve.geometry, physics, fabric, -sleeveLength, 0.02);
                this.paintZones(sleeve.geometry, 'sleeve');
                group.add(sleeve);
                ctx.disposables.push(sleeve.geometry);
                ctx.garmentMeshes.push(sleeve);
            });
        }

        this.applyZoneMaterial();
    },

    /* جنس پارچه: رنگ، زبری، براقی و شفافیت از شناسنامه‌ی پارچه می‌آید */
    fabricMaterial(fabric, withZones) {
        const sheen = fabric.sheen ?? 0.15;
        const transparency = fabric.transparency ?? 0.05;

        return new THREE.MeshStandardMaterial({
            color: withZones ? '#ffffff' : fabric.color || '#b9a48c',
            roughness: Math.max(0.08, 1 - sheen * 0.85),
            metalness: Math.min(0.45, sheen * 0.5),
            transparent: transparency > 0.02,
            opacity: Math.max(0.35, 1 - transparency * 0.75),
            side: THREE.DoubleSide,
            vertexColors: !!withZones,
        });
    },

    /* ------------------------------------------------------------------
     * نشستن پارچه
     * ------------------------------------------------------------------
     * حل‌کننده‌ی واقعی نداریم؛ چند تکرار ثابت اجرا می‌شود: وزن پارچه نقاط را پایین
     * می‌کشد، لختی آن‌ها را به بدن می‌چسباند و سفتی جلوی هر دو را می‌گیرد. نتیجه این
     * است که ابریشم موج می‌خورد و به بدن می‌چسبد ولی جین صاف و ایستا می‌ماند.
     */
    settle(geometry, physics, fabric, minY, maxY) {
        const position = geometry.attributes.position;
        const count = position.count;
        const span = Math.max(0.05, maxY - minY);

        const weight = Math.min(1, (physics.weight ?? 0.15) * 4); // کیلوگرم بر متر مربع → ۰..۱
        const drape = fabric.drape ?? 0.5;
        const softness = 1 - Math.min(0.95, (physics.bending ?? 0.1) * 2.6); // میرایی سفتی
        const lobes = Math.round(4 + drape * 8);
        const iterations = 5;

        const amplitude = 0.012 * drape * softness + 0.003;
        const sag = 0.05 * weight * drape;
        const cling = 0.09 * drape * softness;

        for (let iteration = 0; iteration < iterations; iteration++) {
            const damping = 1 - iteration / iterations;

            for (let i = 0; i < count; i++) {
                const x = position.getX(i);
                const y = position.getY(i);
                const z = position.getZ(i);

                const t = Math.min(1, Math.max(0, (maxY - y) / span)); // پایین‌تر، افتادگی بیشتر
                const angle = Math.atan2(z, x);
                const noise = hash(i + iteration * 977) - 0.5;
                const ripple = Math.sin(angle * lobes + t * 5.5) * amplitude * t * damping;
                const shrink = 1 - cling * t * damping + ripple / Math.max(0.03, Math.hypot(x, z));

                position.setX(i, x * shrink + noise * amplitude * 0.35 * damping);
                position.setZ(i, z * shrink + noise * amplitude * 0.35 * damping);
                position.setY(i, y - sag * t * t * damping * 0.35);
            }
        }

        position.needsUpdate = true;
        geometry.computeVertexNormals();
    },

    /* ------------------------------------------------------------------
     * رنگ‌آمیزی نواحی تناسب روی لباس
     * ------------------------------------------------------------------ */
    paintZones(geometry, kind) {
        const level = this.ctx.level;
        const map = {};

        this.zones.forEach((zone) => {
            map[zone.key] = zone.color || '#16a34a';
        });

        const green = '#16a34a';

        const anchors =
            kind === 'sleeve'
                ? [
                      [0.02, map.armhole || map.bust || green],
                      [-0.2, map.bicep || map.bust || green],
                      [-1, map.bicep || green],
                  ]
                : [
                      [level.shoulder, map.shoulder || green],
                      [level.bust, map.bust || green],
                      [level.waist, map.waist || green],
                      [level.hip, map.hip || green],
                      [0, map.hem || map.hip || green],
                  ];

        const sorted = anchors
            .map(([y, color]) => ({ y, color: new THREE.Color(color) }))
            .sort((a, b) => a.y - b.y);

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
            const t = Math.min(1, Math.max(0, (y - lower.y) / range));

            mixed.copy(lower.color).lerp(upper.color, t);
            colors[i * 3] = mixed.r;
            colors[i * 3 + 1] = mixed.g;
            colors[i * 3 + 2] = mixed.b;
        }

        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
    },

    applyZoneMaterial() {
        const ctx = this.ctx;

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
        const ctx = this.ctx;

        if (!ctx.root) {
            return;
        }

        const p = POSES[pose] || {};

        ctx.root.rotation.y = (p.spin || 0) * RAD;
        ctx.root.position.y = p.drop || 0;
        ctx.torso.rotation.x = (p.torso || 0) * RAD;

        ctx.armL.rotation.set((p.armL || 0) * RAD, 0, (p.armLZ || -6) * RAD);
        ctx.armR.rotation.set((p.armR || 0) * RAD, 0, (p.armRZ || 6) * RAD);

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

        this.ctx.orbit.yaw = (angles[view] ?? 0) * RAD;
        this.updateCamera();
    },

    toggleRotate() {
        this.autoRotate = !this.autoRotate;
    },

    updateCamera() {
        const ctx = this.ctx;

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
        const ctx = this.ctx;
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
        const ctx = this.ctx;
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
        const ctx = this.ctx;

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
        const ctx = this.$el._garmentViewer;

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

        delete this.$el._garmentViewer;
    },

    /* نقطه‌های کلیدی را به یک خط نرم تبدیل می‌کند (ورودی چرخانه‌ها) */
    spline(points) {
        const vectors = points.map(([x, y]) => new THREE.Vector2(Math.max(0.005, x), y));

        if (vectors.length < 3) {
            return vectors;
        }

        return new THREE.SplineCurve(vectors).getPoints(Math.max(18, vectors.length * 5));
    },
});
