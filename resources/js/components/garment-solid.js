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
 * سه چیز باعث می‌شود این «لولهٔ رنگی» نباشد بلکه لباس دیده شود:
 *
 *   ۱) مانکن یک آدمِ کامل است، نه چند بیضیِ روی هم: سر و گردن و سرشانهٔ شیب‌دار،
 *      دو دستِ باریک‌شونده و دو پای جدا — همه از اندازه‌های همان مشتری.
 *
 *   ۲) پارچه چین می‌خورد، و چینش ساختگی نیست: هر جا لباس از بدن گشادتر است،
 *      آن پارچهٔ اضافه باید جایی برود. دامنهٔ چین از همان اختلاف درمی‌آید.
 *      روی سرشانه صفر است (آن‌جا لباس آویزان و کشیده است) و رو به پایین باز
 *      می‌شود، همان‌طور که پارچه می‌افتد.
 *
 *   ۳) نورپردازی و سایهٔ زمین، تا حجم دیده شود. بدون این هر چیزی تخت است.
 */

import { buildBody, perimeter, sampleRing } from '../lib/mannequin';

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

/* چند ضلعی در هر حلقه */
const SIDES = 64;

const clamp = (value, low, high) => Math.max(low, Math.min(high, value));

const track = (ctx, item) => {
    ctx.disposables.push(item);

    return item;
};

/**
 * یک سطحِ لوله‌ای از روی حلقه‌ها.
 *
 * هر حلقه بیضی است و می‌تواند شعاعش را با یک تابع تغییر دهد — همان‌جاست که چینِ
 * پارچه وارد می‌شود. نرمال‌ها از خودِ مثلث‌ها حساب می‌شوند تا چین‌ها سایه بگیرند؛
 * نرمالِ بیضیِ ساده روی سطحِ چین‌خورده غلط است و همه‌چیز تخت دیده می‌شود.
 */
const tube = (rings, options = {}) => {
    const rows = rings.filter((ring) => ring.rx > 0.02 && ring.rz > 0.02);

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
                (ring.rx + push) * CM * Math.cos(angle),
                -(ring.y + yOffset) * CM,
                (ring.rz + push) * CM * Math.sin(angle),
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

        positions.push(0, -(rows[row].y + yOffset) * CM, 0);

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
    message: '',
    spin: true,

    async boot() {
        if (! this.payload || ! this.payload.ok) {
            this.failed = true;
            this.message = (this.payload && this.payload.notes && this.payload.notes[0])
                || 'این مدل روی مانکن نشانده نشد.';

            return;
        }

        try {
            THREE ??= await import('three');
            this.build();
            this.ready = true;
        } catch (error) {
            this.failed = true;
            this.message = 'نمای سه‌بعدی در این مرورگر بالا نیامد.';
        }
    },

    build() {
        const ctx = contextFor(this.$root);
        const canvas = this.$refs.canvas;
        const shell = this.payload;
        const body = buildBody(shell.measurements || {});
        ctx.body = body;

        const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
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
        camera.position.set(tall * 0.40, -tall * 0.45, tall * 1.9);
        camera.lookAt(0, -tall * 0.47, 0);
        ctx.camera = camera;

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
        group.rotation.y = -0.42;
        scene.add(group);
        ctx.group = group;

        this.addBody(group, body);
        this.addGarment(group, shell, body);

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

    /** مانکن: سر، گردن، تنه، دو دست و دو پا — همه از اندازه‌های مشتری. */
    addBody(group, body) {
        const ctx = contextFor(this.$root);

        const skin = track(ctx, new THREE.MeshStandardMaterial({
            color: 0xcdc2b8,
            roughness: 0.96,
            metalness: 0,
        }));
        ctx.skin = skin;

        const add = (geometry, x = 0, tilt = 0) => {
            if (! geometry) {
                return;
            }

            track(ctx, geometry);
            const mesh = new THREE.Mesh(geometry, skin);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            mesh.position.x = x * CM;
            mesh.rotation.z = tilt;
            group.add(mesh);
        };

        add(tube(body.torso));

        // سر: بیضی‌گون، نه کره — کرهٔ کامل شبیه توپ می‌شود
        const head = track(ctx, new THREE.SphereGeometry(body.head.radius * CM, 32, 24));
        const headMesh = new THREE.Mesh(head, skin);
        headMesh.scale.set(0.82, 1.12, 0.9);
        headMesh.position.y = -body.head.centre * CM;
        headMesh.castShadow = true;
        group.add(headMesh);

        const shoulder = body.shoulderRing;
        const armX = shoulder.rx - body.arm[0].r * 0.8;
        const armTop = shoulder.y + body.arm[0].r * 0.5;

        [-1, 1].forEach((side) => {
            const rings = body.arm.map((row) => ({ y: row.y, rx: row.r, rz: row.r }));
            add(tube(rings, { yOffset: armTop }), side * armX, side * 0.085);
        });

        [-1, 1].forEach((side) => {
            const rings = body.leg.map((row) => ({ y: row.y, rx: row.r, rz: row.r }));
            add(tube(rings, { yOffset: body.level.crotch }), side * body.legGap, side * -0.012);
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

        // لباس از سرشانه شروع می‌شود، یا از کمر اگر بالاتنه نداشته باشد
        const top = shell.shoulder > 1 ? body.level.shoulder : body.level.waist;

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

        let rings = shell.rings.map((ring) => {
            if (bustRel <= 1 || ring.y >= bustRel) {
                return ring;
            }

            const skin = sampleRing(body.torso, ring.y + top);
            const t = clamp(ring.y / bustRel, 0, 1);

            return {
                y: ring.y,
                rx: (skin.rx + skinGap) + (ring.rx - (skin.rx + skinGap)) * t,
                rz: (skin.rz + skinGap) + (ring.rz - (skin.rz + skinGap)) * t,
            };
        });

        if (shell.open_top && bustRel > 1) {
            // دهانهٔ یقه: یک حلقهٔ کوچک دور گردن، بالاتر از سرشانه
            const neckDrop = body.level.shoulder - body.level.neck;
            const neck = body.neckRadius + 1.4;

            rings = [{ y: -neckDrop * 0.55, rx: neck, rz: neck * 0.92 }, ...rings];
        }

        const excessAt = (ring) => {
            const skinRing = sampleRing(body.torso, ring.y + top);
            const bodyGirth = perimeter(skinRing.rx, skinRing.rz);
            const cloth = perimeter(ring.rx, ring.rz);

            return bodyGirth <= 1 ? 0 : clamp(cloth / bodyGirth - 1, 0, 1.6);
        };

        const tail = rings.length ? rings[rings.length - 1] : null;
        const folds = Math.round(clamp(6 + (tail ? excessAt(tail) : 0) * 7, 6, 15));

        const wave = (angle, t, ring) => {
            const excess = excessAt(ring);

            if (excess < 0.05) {
                return 0;
            }

            const fall = Math.pow(clamp(t, 0, 1), 1.3);
            const depth = Math.min(ring.rx * 0.2, excess * ring.rx * 0.55) * fall;

            return depth * Math.cos(folds * angle + t * 0.8);
        };

        const geometry = tube(rings, {
            yOffset: top,
            wave,
            capTop: ! shell.open_top,
            capBottom: true,
        });

        if (geometry) {
            track(ctx, geometry);
            const mesh = new THREE.Mesh(geometry, material);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            group.add(mesh);
        }

        this.addSleeves(group, shell, body, material);
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
        const cuff = Math.max(radius(sleeve.cuff), body.arm[3].r * 1.06);
        const armX = shoulder.rx - body.arm[0].r * 0.8;
        const armTop = shoulder.y + body.arm[0].r * 0.5;

        [-1, 1].forEach((side) => {
            const mid = bicep + (cuff - bicep) * 0.45;

            /*
             * سرِ آستین چند سانت بالاتر از مفصل شروع می‌شود و کمی گشادتر است،
             * تا زیر پوستهٔ تنه برود. وگرنه میان تنه و آستین یک شکاف می‌ماند و
             * آستین مثل لولهٔ جدا آویزان دیده می‌شود.
             */
            const geometry = tube([
                { y: body.arm[0].r * 0.15, rx: bicep * 1.1, rz: bicep * 1.1 },
                { y: body.arm[0].r * 0.9, rx: bicep * 1.02, rz: bicep * 1.02 },
                { y: sleeve.length * 0.45, rx: mid, rz: mid },
                { y: sleeve.length * 0.8, rx: cuff * 1.05, rz: cuff * 1.05 },
                { y: sleeve.length, rx: cuff, rz: cuff },
            ], { yOffset: armTop, capTop: false, capBottom: false });

            if (! geometry) {
                return;
            }

            track(ctx, geometry);
            const mesh = new THREE.Mesh(geometry, material);
            mesh.castShadow = true;
            mesh.position.x = side * armX * CM;
            mesh.rotation.z = side * 0.085;
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
    },

    toggleSpin() {
        this.spin = ! this.spin;
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
