/*
 * سنجهٔ بینایی: سوراخِ لباس را در خودِ تصویر پیدا می‌کند.
 *
 * چرا لازم است: سنجهٔ هندسی روی چند تراز نمونه می‌گیرد و سرشانه در هیچ‌کدام
 * نیست. بارها شد که عدد می‌گفت «۱ از ۲۴۰ لخت» و عکس سوراخِ سرشانه را نشان
 * می‌داد. چیزی که دیده نشود، درست هم نمی‌شود.
 *
 * روشش رنگ است، ولی نه اشباع: پارچهٔ خاکی و پوستِ مانکن اشباعشان ۰٫۲۶ و ۰٫۱۳
 * بود و مرزشان زیر سایه جابه‌جا می‌شد. پس پیش از عکس، پارچهٔ «جین آبی نفتی»
 * پوشانده می‌شود — همان کاری که خودِ کاربر با انتخاب پارچه می‌کند. آن وقت
 * جدایی قطعی است: آبیِ غالب یعنی پارچه، قرمزِ غالب یعنی پوست.
 */
/*
 * روش کار:
 *   php artisan serve --port=8123        # با پایگاه‌دادهٔ نمونه
 *   BASE=http://127.0.0.1:8123 node tests/js/vision-holes.mjs
 *
 * playwright-core لازم است (در پروژه نصب نیست تا وابستگی سنگین نشود)؛ مسیرش
 * را می‌شود با PLAYWRIGHT_CORE داد. خروجی: چند عدد روی خط فرمان، و عکسِ هر
 * مدل با سوراخ‌های قرمزشده در OUT_DIR.
 */
const pw = await import(process.env.PLAYWRIGHT_CORE || 'playwright-core').catch(() => null);

if (! pw) {
    console.error('playwright-core پیدا نشد. نصبش کنید یا مسیرش را با PLAYWRIGHT_CORE بدهید.');
    process.exit(2);
}

const { chromium } = pw.default || pw;
const BASE = process.env.BASE || 'http://127.0.0.1:8123';
const OUT = process.env.OUT_DIR || 'storage/app/vision';
const WANT = JSON.parse(process.argv[2] || '[[9,"پیراهن"],[10,"کت"],[11,"ترنچ‌کت"],[12,"پیراهن-راپ"],[13,"کت-رسمی"]]');

const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--headless=new', '--no-sandbox', '--use-gl=swiftshader', '--enable-unsafe-swiftshader', '--disable-gpu-sandbox'],
});

const page = await browser.newPage({ viewport: { width: 1000, height: 1100 }, deviceScaleFactor: 1 });

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', 'demo@dokht.test');
await page.fill('input[type="password"]', 'password');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button[type="submit"]')]);

const rows = [];

for (const [id, name] of WANT) {
    await page.goto(`${BASE}/patterns/${id}`, { waitUntil: 'networkidle' });

    let state = 'زمان تمام شد';

    for (let i = 0; i < 90; i++) {
        state = await page.evaluate(() => {
            const el = document.querySelector('[x-data^="garmentSolid"]');
            const d = el && window.Alpine ? window.Alpine.$data(el) : null;

            if (! d) return 'بی‌آلپاین';

            return d.failed ? 'شکست' : (d.ready ? 'آماده' : 'نشستن');
        });

        if (state === 'آماده' || state === 'شکست') break;

        await page.waitForTimeout(1000);
    }

    if (state !== 'آماده') {
        rows.push({ name, state, holes: null });
        continue;
    }

    await page.evaluate(() => {
        const el = document.querySelector('[x-data^="garmentSolid"]');
        const d = el && window.Alpine ? window.Alpine.$data(el) : null;

        if (! d) return;

        if (d.spin) d.toggleSpin();

        // پارچهٔ آبی، تا پارچه از پوست جدا شود
        const denim = (d.fabrics || []).find((one) => /جین/.test(one.name)) || (d.fabrics || [])[0];

        if (denim) d.wear(denim);
    });
    await page.waitForTimeout(1400);

    /*
     * تصویر را خودِ مرورگر می‌گیرد، نه toDataURL.
     *
     * سه‌بعدی با preserveDrawingBuffer خاموش کار می‌کند، پس بومِ WebGL پس از
     * رسم خالی خوانده می‌شود: اولین بار همه چیز صفر درآمد و فکر کردم سوراخی
     * نیست، در حالی که اصلاً پیکسلی خوانده نشده بود.
     */
    const raw = await page.locator('canvas[aria-label="نمای سه‌بعدی لباس روی مانکن"]').screenshot();

    const found = await page.evaluate(async (data) => {
        const shot = new Image();

        await new Promise((done) => {
            shot.onload = done;
            shot.src = 'data:image/png;base64,' + data;
        });

        const w = shot.naturalWidth;
        const h = shot.naturalHeight;
        const flat = document.createElement('canvas');

        flat.width = w;
        flat.height = h;

        const ctx = flat.getContext('2d', { willReadFrequently: true });

        ctx.drawImage(shot, 0, 0);

        const px = ctx.getImageData(0, 0, w, h).data;

        /*
         * سه دسته: پارچه (اشباعِ بالا)، پوست (اشباعِ پایینِ روشن) و پس‌زمینه.
         * پس‌زمینهٔ نماگر تقریباً سفید و بی‌سایه است، پس با روشناییِ خیلی بالا و
         * اشباعِ نزدیک صفر شناخته می‌شود.
         */
        const CLOTH = 1;
        const SKIN = 2;
        const kind = new Uint8Array(w * h);

        for (let i = 0; i < w * h; i++) {
            const r = px[i * 4];
            const g = px[i * 4 + 1];
            const b = px[i * 4 + 2];
            const a = px[i * 4 + 3];
            const hi = Math.max(r, g, b);
            const lo = Math.min(r, g, b);
            const sat = hi === 0 ? 0 : (hi - lo) / hi;

            if (a < 40 || (hi > 240 && sat < 0.06)) {
                continue; // پس‌زمینه
            }

            // آبی غالب ⇒ پارچه؛ قرمز غالب ⇒ پوستِ مانکن
            kind[i] = b > r + 8 ? CLOTH : SKIN;
        }

        /*
         * سوراخ یعنی پوستی که *میانِ* دو لبهٔ پارچه دیده می‌شود.
         *
         * اول «محصور بودن» را سنجیدم و صفر گرفتم، در حالی که عکس سوراخ نشان
         * می‌داد: شکافِ سرشانه از راهِ یقه به بیرون راه دارد، پس محصور نیست.
         * ولی هر جا در یک سطرِ افقی، پوست میان چپ‌ترین و راست‌ترین پارچهٔ همان
         * سطر بیفتد، آن‌جا باید پارچه می‌بود.
         *
         * فقط ارتفاعی سنجیده می‌شود که خودِ لباس آن‌جا هست، و سطری که پارچه‌اش
         * کمتر از ده پیکسل است کنار می‌رود — وگرنه دنبالهٔ دامن و لبهٔ آستین
         * سوراخ شمرده می‌شوند.
         */
        const hole = new Uint8Array(w * h);
        let holeCells = 0;

        for (let y = 0; y < h; y++) {
            let left = -1;
            let right = -1;
            let cloth = 0;

            for (let x = 0; x < w; x++) {
                if (kind[y * w + x] === CLOTH) {
                    if (left < 0) left = x;
                    right = x;
                    cloth++;
                }
            }

            if (cloth < 10 || right - left < 20) {
                continue;
            }

            for (let x = left; x <= right; x++) {
                if (kind[y * w + x] === SKIN) {
                    hole[y * w + x] = 1;
                    holeCells++;
                }
            }
        }

        /* لکه‌بندی، تا بشود گفت چند سوراخ و کجا */
        const seen = new Uint8Array(w * h);
        const blobs = [];

        for (let start = 0; start < w * h; start++) {
            if (! hole[start] || seen[start]) {
                continue;
            }

            const stack = [start];
            const cells = [];

            seen[start] = 1;

            while (stack.length) {
                const at = stack.pop();
                const x = at % w;
                const y = (at - x) / w;

                cells.push(at);

                for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
                    const nx = x + dx;
                    const ny = y + dy;

                    if (nx < 0 || ny < 0 || nx >= w || ny >= h) continue;

                    const to = ny * w + nx;

                    if (hole[to] && ! seen[to]) {
                        seen[to] = 1;
                        stack.push(to);
                    }
                }
            }

            if (cells.length >= 40) {
                let sx = 0;
                let sy = 0;

                for (const at of cells) {
                    sx += at % w;
                    sy += (at - (at % w)) / w;
                }

                blobs.push({ area: cells.length, x: Math.round(sx / cells.length), y: Math.round(sy / cells.length), cells });
            }
        }

        blobs.sort((one, two) => two.area - one.area);

        // سوراخ‌ها را قرمز کن تا در عکس دیده شوند
        for (const blob of blobs) {
            for (const at of blob.cells) {
                px[at * 4] = 255;
                px[at * 4 + 1] = 32;
                px[at * 4 + 2] = 32;
            }
        }

        ctx.putImageData(new ImageData(px, w, h), 0, 0);

        return {
            width: w,
            height: h,
            skin: kind.reduce((sum, v) => sum + (v === SKIN ? 1 : 0), 0),
            cloth: kind.reduce((sum, v) => sum + (v === CLOTH ? 1 : 0), 0),
            holes: blobs.length,
            area: blobs.reduce((sum, b) => sum + b.area, 0),
            biggest: blobs.slice(0, 5).map((b) => ({ area: b.area, x: b.x, y: b.y })),
            marked: flat.toDataURL('image/png'),
        };
    }, raw.toString('base64'));

    const { writeFile, mkdir } = await import('node:fs/promises');

    await mkdir(OUT, { recursive: true });
    await writeFile(`${OUT}/${name}.png`, Buffer.from(found.marked.split(',')[1], 'base64'));

    rows.push({ name, state, holes: found.holes, area: found.area, cloth: found.cloth, skin: found.skin, biggest: found.biggest });
}

console.log('\nسوراخِ دیده‌شده در تصویر (لکهٔ پوست که پارچه دورش را گرفته):\n');

for (const row of rows) {
    if (row.holes === null) {
        console.log(`  ${row.name.padEnd(14)} ${row.state}`);

        continue;
    }

    const share = row.cloth ? (row.area / row.cloth * 100).toFixed(1) : '0.0';

    console.log(
        `  ${row.name.padEnd(14)} سوراخ=${String(row.holes).padStart(3)}  مساحت=${String(row.area).padStart(6)} پیکسل (${share}٪ پارچه)  [پارچه=${row.cloth} پوست=${row.skin}]` +
        (row.biggest.length ? `  بزرگ‌ترین‌ها: ${row.biggest.map((b) => `${b.area}@${b.x},${b.y}`).join(' ')}` : ''),
    );
}

console.log('');
await browser.close();
