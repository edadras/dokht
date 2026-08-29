/*
 * لباسِ دوخته‌شده روی مانکن.
 *
 * این نما با «دوخت مجازی» فرق دارد و عمداً هم فرق دارد. آن‌جا پارچه شبیه‌سازی
 * می‌شود: قطعه‌ها را می‌دوزد، زیر وزن خودش می‌افتد و به بدن برخورد می‌کند. این‌جا
 * چیزی شبیه‌سازی نمی‌شود؛ همان چیزی نشان داده می‌شود که در نمای دوبعدی هم دیده
 * شد، فقط این بار دور بدن.
 *
 * چرا این‌طور: پوستهٔ لباس از سرور می‌آید به شکل حلقه‌های بیضی — در هر ارتفاع،
 * نیم‌پهنا و نیم‌ضخامتِ لباسِ دوخته‌شده. آن حلقه‌ها از خودِ الگو اندازه گرفته
 * شده‌اند (پس از بستن ساسون‌ها)، دقیقاً همان اعدادی که نمای جلو و پشت و پهلو را
 * ساختند. پس آنچه روی مانکن دیده می‌شود نمی‌تواند با نمای دوبعدی نخواند: یک
 * عدد است با دو نمایش.
 *
 * مانکن هم از همان اندازه‌های مشتری ساخته می‌شود، با همان مدلِ مقطع. پس فاصلهٔ
 * پارچه از پوست همان آزادیِ واقعیِ الگوست، نه یک عددِ ظاهری.
 *
 * تنها چیزی که این‌جا اندازه‌گیری نیست، ضخامتِ مقطع است — کاغذِ الگو تخت است و
 * ضخامت ندارد. سرور آن را از دورِ دوخته‌شده و نسبتِ مقطعِ تنه حساب می‌کند و
 * همین در صفحه هم نوشته می‌شود.
 */

let THREE = null;

/* اشیای three از دسترسِ واکنشیِ آلپاین دور نگه داشته می‌شوند */
const contexts = new WeakMap();

const contextFor = (element) => {
    if (! contexts.has(element)) {
        contexts.set(element, { disposables: [] });
    }

    return contexts.get(element);
};

/* سانتی‌متر به متر؛ صحنه با متر کار می‌کند تا نور و دوربین طبیعی بمانند */
const CM = 0.01;

/* چند ضلعی در هر حلقه: کمتر از این، بیضی گوشه‌دار دیده می‌شود */
const SIDES = 48;

/**
 * یک لولهٔ بسته از روی حلقه‌ها.
 *
 * هر حلقه یک بیضی افقی است (rx و rz جدا)، و حلقه‌ها از بالا به پایین روی هم
 * چیده می‌شوند. سر و ته با یک بادبزن ساده بسته می‌شود تا از داخل تهی دیده نشود.
 */
const loft = (rings, options = {}) => {
    const rows = rings.filter((ring) => ring.rx > 0.05 && ring.rz > 0.05);

    if (rows.length < 2) {
        return null;
    }

    const yOffset = options.yOffset || 0;
    const grow = options.grow || 0;
    const positions = [];
    const normals = [];
    const indexes = [];

    rows.forEach((ring) => {
        const rx = (ring.rx + grow) * CM;
        const rz = (ring.rz + grow) * CM;
        const y = -(ring.y + yOffset) * CM;

        for (let i = 0; i <= SIDES; i++) {
            const angle = (i / SIDES) * Math.PI * 2;
            const cos = Math.cos(angle);
            const sin = Math.sin(angle);

            positions.push(rx * cos, y, rz * sin);

            // نرمالِ بیضی: مشتقِ مماس، چرخیده — نه خودِ شعاع، وگرنه نور روی
            // بیضی‌های کشیده کج می‌افتد
            const nx = rz * cos;
            const nz = rx * sin;
            const len = Math.hypot(nx, nz) || 1;
            normals.push(nx / len, 0, nz / len);
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

    const caps = options.cap === false ? [] : (options.cap === 'bottom' ? [rows.length - 1] : [0, rows.length - 1]);

    caps.forEach((row) => {
        const which = row === 0 ? 0 : 1;

        {
            const ring = rows[row];
            const centre = positions.length / 3;

            positions.push(0, -(ring.y + yOffset) * CM, 0);
            normals.push(0, which === 0 ? 1 : -1, 0);

            for (let i = 0; i < SIDES; i++) {
                const a = row * perRow + i;
                const b = a + 1;

                which === 0 ? indexes.push(centre, b, a) : indexes.push(centre, a, b);
            }
        }
    });

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geometry.setAttribute('normal', new THREE.Float32BufferAttribute(normals, 3));
    geometry.setIndex(indexes);

    return geometry;
};

/** نیم‌پهنا و نیم‌ضخامتِ حلقه‌ها در ارتفاع دلخواه (میان‌یابی خطی). */
const at = (rings, y) => {
    if (rings.length === 0) {
        return { rx: 0, rz: 0 };
    }

    if (y <= rings[0].y) {
        return rings[0];
    }

    for (let i = 1; i < rings.length; i++) {
        if (y <= rings[i].y) {
            const span = rings[i].y - rings[i - 1].y || 1;
            const t = (y - rings[i - 1].y) / span;

            return {
                rx: rings[i - 1].rx + (rings[i].rx - rings[i - 1].rx) * t,
                rz: rings[i - 1].rz + (rings[i].rz - rings[i - 1].rz) * t,
            };
        }
    }

    return rings[rings.length - 1];
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

        const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
        ctx.renderer = renderer;

        const scene = new THREE.Scene();
        ctx.scene = scene;

        /*
         * دوربین از قدِ خودِ لباس تنظیم می‌شود، نه از عددی ثابت: یک تاپِ کوتاه و
         * یک لباس شبِ صدوشصت سانتی هر دو باید کامل در قاب بیفتند.
         */
        const tall = Math.max(shell.height, 60) * CM;
        const camera = new THREE.PerspectiveCamera(32, canvas.clientWidth / canvas.clientHeight, 0.05, 40);
        camera.position.set(0, -tall * 0.45, tall * 2.6);
        camera.lookAt(0, -tall * 0.45, 0);
        ctx.camera = camera;

        scene.add(new THREE.HemisphereLight(0xffffff, 0x8a7f76, 1.05));

        const key = new THREE.DirectionalLight(0xffffff, 1.15);
        key.position.set(1.2, 1.6, 2.2);
        scene.add(key);

        const rim = new THREE.DirectionalLight(0xffffff, 0.35);
        rim.position.set(-1.6, 0.4, -1.8);
        scene.add(rim);

        const group = new THREE.Group();
        scene.add(group);
        ctx.group = group;

        this.addBody(group, shell);
        this.addGarment(group, shell);

        // لباس روی مانکن نشسته؛ حالا هر دو با هم می‌چرخند
        let last = performance.now();

        const tick = (now) => {
            const delta = Math.min((now - last) / 1000, 0.1);
            last = now;

            if (this.spin) {
                group.rotation.y += delta * 0.5;
            }

            renderer.render(scene, camera);
            ctx.frame = requestAnimationFrame(tick);
        };

        ctx.frame = requestAnimationFrame(tick);

        this.$watch('spin', () => {});
    },

    /** مانکن: تنه از حلقه‌های بدن، به‌علاوهٔ گردن و سر و دست‌ها. */
    addBody(group, shell) {
        const ctx = contextFor(this.$root);
        const rings = shell.body || [];

        if (rings.length < 2) {
            return;
        }

        const material = new THREE.MeshStandardMaterial({
            color: 0xd9cfc6,
            roughness: 0.92,
            metalness: 0,
        });
        ctx.disposables.push(material);

        const torso = loft(rings);

        if (torso) {
            ctx.disposables.push(torso);
            group.add(new THREE.Mesh(torso, material));
        }

        // سر و گردن: مانکن است، پس صورت ندارد — فقط حجمی که نسبت را بفهماند
        const neck = at(rings, 0);
        const headR = Math.max(neck.rx * 1.5, 8) * CM;

        const head = new THREE.SphereGeometry(headR, 24, 18);
        ctx.disposables.push(head);
        const headMesh = new THREE.Mesh(head, material);
        headMesh.position.set(0, headR * 1.15, 0);
        headMesh.scale.set(0.86, 1.12, 0.94);
        group.add(headMesh);

        // بازوها: از نوکِ سرشانه آویزان، کمی باز از تنه
        const shoulder = rings[1] || rings[0];
        const armR = Math.max(shoulder.rz * 0.34, 3.5);
        const armLength = Math.max(shell.height * 0.62, 45);

        [-1, 1].forEach((side) => {
            const arm = loft([
                { y: 0, rx: armR, rz: armR },
                { y: armLength * 0.55, rx: armR * 0.82, rz: armR * 0.82 },
                { y: armLength, rx: armR * 0.6, rz: armR * 0.6 },
            ]);

            if (! arm) {
                return;
            }

            ctx.disposables.push(arm);
            const mesh = new THREE.Mesh(arm, material);
            mesh.position.set(side * (shoulder.rx - armR * 0.6) * CM, -shoulder.y * CM, 0);
            mesh.rotation.z = side * 0.07;
            group.add(mesh);
        });
    },

    /** لباس: همان حلقه‌ها، روی مانکن و با رنگ و جنسِ پارچهٔ خودش. */
    addGarment(group, shell) {
        const ctx = contextFor(this.$root);
        const fabric = shell.fabric || {};

        const material = new THREE.MeshStandardMaterial({
            color: new THREE.Color(fabric.color || '#b9a48c'),
            roughness: 1 - Math.min(0.75, (fabric.sheen ?? 0.15)),
            metalness: 0,
            side: THREE.DoubleSide,
            transparent: (fabric.transparency ?? 0) > 0.05,
            opacity: 1 - Math.min(0.55, fabric.transparency ?? 0),
        });
        ctx.disposables.push(material);
        ctx.fabric = material;

        /*
         * لباس از کجای بدن شروع می‌شود؟ اگر بالاتنه دارد، از سرشانه؛ اگر فقط
         * پایین‌تنه است (دامن، شلوار) از خط کمر. همین یک عدد جای لباس را روی
         * مانکن معلوم می‌کند و بقیه‌اش از خودِ حلقه‌ها می‌آید.
         */
        const body = shell.body || [];
        const waistY = body.length >= 4 ? body[3].y : 0;
        const top = shell.shoulder > 1 ? (body[1] ? body[1].y : 0) : waistY;

        // بالای لباس باز است (خط یقه)، پس فقط ته لباس بسته می‌شود
        const skin = loft(shell.rings, { yOffset: top, grow: 0.4, cap: shell.open_top ? 'bottom' : true });

        if (skin) {
            ctx.disposables.push(skin);
            group.add(new THREE.Mesh(skin, material));
        }

        if (! shell.sleeve || shell.sleeve.length < 3) {
            return;
        }

        // آستین: لوله‌ای که از سرشانه با زاویه پایین می‌آید، با دور بازو و
        // دم‌آستینِ خودِ الگو
        const sleeve = shell.sleeve;

        /*
         * آستین باید از *خودِ لباس* بیرون بزند، نه از هوا. پس سرش را روی حلقهٔ
         * لباس در ارتفاع سرشانه می‌گذاریم و کمی تو می‌بریم تا درزِ حلقه پوشیده
         * بماند؛ وگرنه بین تنه و آستین یک شکاف دیده می‌شود.
         */
        const angle = 0.30;
        const capY = 4;
        const atCap = at(shell.rings, capY);

        /*
         * عددی که سرور می‌دهد نیم‌پهنای *تختِ* آستین است، پس دورِ آستینِ
         * دوخته‌شده دو برابر آن است و شعاعش دور تقسیم بر دوپی. یک بار همان
         * نیم‌پهنا را شعاع گرفتم و آستین دو برابر پهن درآمد.
         */
        const radius = (half) => half / Math.PI;
        const bicep = Math.max(radius(sleeve.bicep), 3);
        const cuff = Math.max(radius(sleeve.cuff), 2);

        [-1, 1].forEach((side) => {
            const tube = loft([
                { y: -2, rx: bicep * 1.02, rz: bicep * 1.02 },
                { y: sleeve.length * 0.55, rx: (bicep + cuff) / 2, rz: (bicep + cuff) / 2 },
                { y: sleeve.length, rx: cuff, rz: cuff },
            ], { cap: 'bottom' });

            if (! tube) {
                return;
            }

            ctx.disposables.push(tube);
            const mesh = new THREE.Mesh(tube, material);

            // نقطهٔ اتصال: لبهٔ پهلوی لباس روی خط حلقه، کمی به داخل
            mesh.position.set(
                side * (atCap.rx - bicep * 0.35) * CM,
                -(top + capY) * CM,
                0,
            );
            mesh.rotation.z = side * angle;
            group.add(mesh);
        });
    },

    /*
     * عوض کردنِ پارچه فقط رنگ و براقی و شفافیتِ همان جنس را عوض می‌کند؛ شکل
     * لباس از الگو می‌آید و به پارچه ربطی ندارد، پس صحنه دوباره ساخته نمی‌شود.
     */
    wear(swatch) {
        const ctx = contextFor(this.$root);

        if (! ctx.fabric || ! THREE) {
            return;
        }

        this.chosen = swatch.id;
        ctx.fabric.color = new THREE.Color(swatch.color);
        ctx.fabric.roughness = 1 - Math.min(0.75, swatch.sheen ?? 0.15);
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
