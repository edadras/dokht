/*
 * از آواتارِ GLB، همان بدنی که مانکنِ محاسباتی می‌سازد.
 *
 * مانکنِ پروژه از روی اندازه‌ها ساخته می‌شود (buildBody در mannequin.js): چند
 * حلقهٔ تنه، چند حلقهٔ بازو و پا، یک سر. حل‌کنندهٔ پارچه و چیدنِ قطعه‌ها فقط
 * همین حلقه‌ها را می‌شناسند. پس آواتارِ اسکن‌شده/ساخته‌شده هم باید به همین
 * زبان ترجمه شود — نه این‌که موتور از نو برای مشِ دلخواه نوشته شود.
 *
 * کار این ابزار:
 *   ۱. خواندنِ GLB (بی هیچ کتابخانه‌ای؛ فقط JSON و بافر)
 *   ۲. پایین آوردنِ بازوها: آواتارها T-pose می‌آیند و لباس روی بازوی آویزان
 *      دوخته می‌شود. چرخشِ استخوانِ بازو با همان وزن‌های پوست اعمال می‌شود
 *      (linear blend skinning) تا مشِ نهایی دقیقاً همانی باشد که مرورگر با
 *      همان چرخش می‌کشد.
 *   ۳. برش‌زدنِ مشِ ژست‌گرفته: در هر تراز، نیم‌پهنا و جلو و پشتِ تنه؛ در طولِ
 *      بازو، شعاع؛ در طولِ پا، شعاع و جای مرکز.
 *
 * خروجی، همان شیءِ buildBody است به‌اضافهٔ ژستِ استخوان‌ها، تا مرورگر GLB را
 * با همان ژست بکشد و حل‌کننده همان بدن را ببیند.
 *
 *   node tests/js/avatar-body.mjs model.glb > avatar.json
 */

import { readFileSync } from 'node:fs';
import { ARM_TILT } from '../../resources/js/lib/mannequin.js';

/* ---- GLB ---- */

export const readGlb = (file) => {
    const buf = readFileSync(file);
    const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);
    const length = view.getUint32(8, true);
    let offset = 12;
    let json = null;
    let bin = null;

    while (offset < length) {
        const size = view.getUint32(offset, true);
        const type = view.getUint32(offset + 4, true);

        offset += 8;

        if (type === 0x4E4F534A) {
            json = JSON.parse(buf.subarray(offset, offset + size).toString('utf8'));
        } else if (type === 0x004E4942) {
            bin = buf.subarray(offset, offset + size);
        }

        offset += size;
    }

    const accessor = (index) => {
        const a = json.accessors[index];
        const bv = json.bufferViews[a.bufferView];
        const Type = { 5126: Float32Array, 5123: Uint16Array, 5125: Uint32Array, 5121: Uint8Array }[a.componentType];
        const n = { SCALAR: 1, VEC2: 2, VEC3: 3, VEC4: 4, MAT4: 16 }[a.type];
        const start = bin.byteOffset + (bv.byteOffset || 0) + (a.byteOffset || 0);
        const bytes = a.count * n * Type.BYTES_PER_ELEMENT;

        return { data: new Type(bin.buffer.slice(start, start + bytes)), n, count: a.count };
    };

    return { json, bin, accessor };
};

/* ---- ریاضیِ کوچک ---- */

const quatMul = (a, b) => [
    a[3] * b[0] + a[0] * b[3] + a[1] * b[2] - a[2] * b[1],
    a[3] * b[1] - a[0] * b[2] + a[1] * b[3] + a[2] * b[0],
    a[3] * b[2] + a[0] * b[1] - a[1] * b[0] + a[2] * b[3],
    a[3] * b[3] - a[0] * b[0] - a[1] * b[1] - a[2] * b[2],
];
const quatInv = (q) => [-q[0], -q[1], -q[2], q[3]];
const quatFromTo = (from, to) => {
    const cross = [from[1] * to[2] - from[2] * to[1], from[2] * to[0] - from[0] * to[2], from[0] * to[1] - from[1] * to[0]];
    const dot = from[0] * to[0] + from[1] * to[1] + from[2] * to[2];
    const w = 1 + dot;

    if (w < 1e-9) {
        return [1, 0, 0, 0];
    }

    const norm = Math.hypot(cross[0], cross[1], cross[2], w);

    return [cross[0] / norm, cross[1] / norm, cross[2] / norm, w / norm];
};
const rotate = (q, v) => {
    const [x, y, z, w] = q;
    const ix = w * v[0] + y * v[2] - z * v[1];
    const iy = w * v[1] + z * v[0] - x * v[2];
    const iz = w * v[2] + x * v[1] - y * v[0];
    const iw = -x * v[0] - y * v[1] - z * v[2];

    return [
        ix * w + iw * -x + iy * -z - iz * -y,
        iy * w + iw * -y + iz * -x - ix * -z,
        iz * w + iw * -z + ix * -y - iy * -x,
    ];
};
const normalize = (v) => {
    const l = Math.hypot(v[0], v[1], v[2]) || 1;

    return [v[0] / l, v[1] / l, v[2] / l];
};

/* ماتریسِ ۴×۴ ستون‌محور از (چرخش، جابه‌جایی، مقیاس) */
const compose = (t, q, s) => {
    const [x, y, z, w] = q;
    const [sx, sy, sz] = s;

    return [
        (1 - 2 * (y * y + z * z)) * sx, 2 * (x * y + z * w) * sx, 2 * (x * z - y * w) * sx, 0,
        2 * (x * y - z * w) * sy, (1 - 2 * (x * x + z * z)) * sy, 2 * (y * z + x * w) * sy, 0,
        2 * (x * z + y * w) * sz, 2 * (y * z - x * w) * sz, (1 - 2 * (x * x + y * y)) * sz, 0,
        t[0], t[1], t[2], 1,
    ];
};
const mul = (a, b) => {
    const out = new Array(16).fill(0);

    for (let col = 0; col < 4; col++) {
        for (let row = 0; row < 4; row++) {
            let sum = 0;

            for (let k = 0; k < 4; k++) {
                sum += a[k * 4 + row] * b[col * 4 + k];
            }

            out[col * 4 + row] = sum;
        }
    }

    return out;
};
const apply = (m, v) => [
    m[0] * v[0] + m[4] * v[1] + m[8] * v[2] + m[12],
    m[1] * v[0] + m[5] * v[1] + m[9] * v[2] + m[13],
    m[2] * v[0] + m[6] * v[1] + m[10] * v[2] + m[14],
];

/* ---- اسکلت و ژست ---- */

/**
 * ژستِ استخوان‌ها: بازوها آویزان، با همان کجیِ مانکنِ محاسباتی (ARM_TILT).
 *
 * چرخش در دستگاهِ جهانی حساب می‌شود — جهتِ کنونیِ بازو (مفصلِ شانه تا آرنج) به
 * جهتِ خواسته — و بعد به دستگاهِ محلیِ همان استخوان برگردانده می‌شود، تا به
 * قراردادِ محورهای ریگ وابسته نباشد. ساعد و دست با پدرشان می‌آیند.
 *
 * @returns {{ pose: Object<string, number[]>, world: Array<{ t: number[], q: number[] }> }}
 */
export const poseArms = (json, tilt = ARM_TILT) => {
    const nodes = json.nodes;
    const parent = new Array(nodes.length).fill(-1);

    nodes.forEach((node, i) => (node.children || []).forEach((c) => (parent[c] = i)));

    const local = nodes.map((node) => ({
        t: node.translation || [0, 0, 0],
        q: node.rotation || [0, 0, 0, 1],
        s: node.scale || [1, 1, 1],
    }));
    const byName = new Map(nodes.map((node, i) => [node.name, i]));

    const worldOf = () => {
        const world = new Array(nodes.length);
        const order = [];
        const visit = (i) => {
            order.push(i);
            (nodes[i].children || []).forEach(visit);
        };

        (json.scenes[json.scene || 0].nodes || []).forEach(visit);

        for (const i of order) {
            const p = parent[i];
            const q = p < 0 ? local[i].q : quatMul(world[p].q, local[i].q);
            const t = p < 0 ? local[i].t : (() => {
                const r = rotate(world[p].q, [local[i].t[0] * world[p].s[0], local[i].t[1] * world[p].s[1], local[i].t[2] * world[p].s[2]]);

                return [world[p].t[0] + r[0], world[p].t[1] + r[1], world[p].t[2] + r[2]];
            })();
            const s = p < 0 ? local[i].s : [world[p].s[0] * local[i].s[0], world[p].s[1] * local[i].s[1], world[p].s[2] * local[i].s[2]];

            world[i] = { t, q, s };
        }

        return world;
    };

    const pose = {};

    /*
     * اول ساعد صاف می‌شود: در T-pose آرنج کمی خم است و اگر فقط بازو تراز شود،
     * ساعد و دست از استوانهٔ آستین بیرون می‌زنند — در عکس از آرنج به پایین
     * لخت دیده می‌شد با آن‌که سنجه پوششِ ۳۶۰ درجه می‌داد (سنجه دورِ محورِ
     * صافِ برخوردگر نمونه می‌گیرد). پس آرنج→مچ روی همان جهتِ شانه→آرنج
     * می‌نشیند، بعد کلِ بازو به کجیِ برخوردگر می‌رود.
     */
    const align = (node, tip, direction) => {
        const world = worldOf();
        const from = normalize([
            world[tip].t[0] - world[node].t[0],
            world[tip].t[1] - world[node].t[1],
            world[tip].t[2] - world[node].t[2],
        ]);
        const turn = quatFromTo(from, direction);
        const parentQ = parent[node] < 0 ? [0, 0, 0, 1] : world[parent[node]].q;

        local[node].q = quatMul(quatInv(parentQ), quatMul(turn, world[node].q));
        pose[nodes[node].name] = local[node].q.map((v) => Number(v.toFixed(6)));
    };

    for (const side of ['Left', 'Right']) {
        const upper = byName.get(`${side}Arm`);
        const elbow = byName.get(`${side}ForeArm`);
        const wrist = byName.get(`${side}Hand`);

        if (upper !== undefined && elbow !== undefined && wrist !== undefined) {
            const world = worldOf();
            const along = normalize([
                world[elbow].t[0] - world[upper].t[0],
                world[elbow].t[1] - world[upper].t[1],
                world[elbow].t[2] - world[upper].t[2],
            ]);

            align(elbow, wrist, along);
        }
    }

    for (const side of ['Left', 'Right']) {
        const arm = byName.get(`${side}Arm`);
        /*
         * سرِ دورِ محور، مچ است نه آرنج: ساعد در T-pose کمی خم است و اگر فقط
         * بازو تراز شود، محورِ شانه‌تا‌مچ ۸٫۲ درجه کج می‌افتد در حالی که
         * برخوردگر ۴٫۹ درجه (ARM_TILT) فرض می‌کند — سه سانتی‌متر اختلاف سرِ مچ.
         */
        const far = byName.get(`${side}Hand`) ?? byName.get(`${side}ForeArm`);

        if (arm === undefined || far === undefined) {
            continue;
        }

        const world = worldOf();
        const from = normalize([
            world[far].t[0] - world[arm].t[0],
            world[far].t[1] - world[arm].t[1],
            world[far].t[2] - world[arm].t[2],
        ]);
        const outward = side === 'Left' ? 1 : -1;
        const to = normalize([outward * Math.sin(tilt), -Math.cos(tilt), 0]);
        const turn = quatFromTo(from, to);
        const parentQ = parent[arm] < 0 ? [0, 0, 0, 1] : world[parent[arm]].q;
        const newWorld = quatMul(turn, world[arm].q);

        local[arm].q = quatMul(quatInv(parentQ), newWorld);
        pose[nodes[arm].name] = local[arm].q.map((v) => Number(v.toFixed(6)));
    }

    return { pose, world: worldOf(), local };
};

/** رأس‌های ژست‌گرفتهٔ یک مش، در دستگاهِ جهانی (متر). */
export const skinMesh = (glb, meshIndex, world, skinIndex = 0) => {
    const { json, accessor } = glb;
    const skin = json.skins[skinIndex];
    const ibm = accessor(skin.inverseBindMatrices).data;
    const jointMatrix = skin.joints.map((node, j) => {
        const w = world[node];

        return mul(compose(w.t, w.q, w.s), Array.from(ibm.subarray(j * 16, j * 16 + 16)));
    });
    const prim = json.meshes[meshIndex].primitives[0];
    const pos = accessor(prim.attributes.POSITION);
    const joints = accessor(prim.attributes.JOINTS_0);
    const weights = accessor(prim.attributes.WEIGHTS_0);
    const indices = accessor(prim.indices).data;
    const out = new Float32Array(pos.count * 3);
    const dominant = new Uint16Array(pos.count);

    for (let v = 0; v < pos.count; v++) {
        const p = [pos.data[v * 3], pos.data[v * 3 + 1], pos.data[v * 3 + 2]];
        let x = 0;
        let y = 0;
        let z = 0;
        let best = 0;

        for (let k = 0; k < 4; k++) {
            const w = weights.data[v * 4 + k];

            if (w <= 0) {
                continue;
            }

            const j = joints.data[v * 4 + k];
            const q = apply(jointMatrix[j], p);

            x += q[0] * w;
            y += q[1] * w;
            z += q[2] * w;

            if (w > best) {
                best = w;
                dominant[v] = skin.joints[j];
            }
        }

        out[v * 3] = x;
        out[v * 3 + 1] = y;
        out[v * 3 + 2] = z;
    }

    return { positions: out, indices, dominant, count: pos.count };
};

/* ---- برش‌زدن ---- */

const percentile = (list, share) => {
    if (! list.length) {
        return 0;
    }

    const sorted = [...list].sort((a, b) => a - b);

    return sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * share))];
};

/**
 * حلقه‌های بدن از مشِ ژست‌گرفته.
 *
 * @param {object} glb
 * @param {{ world: Array, pose: object }} posed
 * @param {{ body: number, head: number }} meshes شمارهٔ مشِ بدن و سر
 */
export const groupOfBone = (name) => {
    if (/^Left(Arm|ForeArm|Hand)/.test(name)) return 1;
    if (/^Right(Arm|ForeArm|Hand)/.test(name)) return 2;
    if (/^Left(UpLeg|Leg|Foot|Toe)/.test(name)) return 3;
    if (/^Right(UpLeg|Leg|Foot|Toe)/.test(name)) return 4;
    if (/^(Head|Neck)/.test(name)) return 5;

    return 0;
};

export const bodyFromAvatar = (glb, posed, meshes = null, warp = null) => {
    const { json } = glb;
    const nodes = json.nodes;
    const byName = new Map(nodes.map((node, i) => [node.name, i]));
    // مفصل‌ها هم با همان تغییرِ شکلِ رأس‌ها می‌روند
    const joint = (name) => {
        const t = posed.world[byName.get(name)].t;

        return warp ? warp(t, groupOfBone(name)) : t;
    };

    /*
     * پوستِ بدن = مشِ بدن + لباسِ زیرِ آواتار.
     *
     * آواتارهای آماده (Ready Player Me و مانندش) پوستی را که زیرِ لباسِ
     * خودشان پنهان است اصلاً ندارند: مشِ «بدن» فقط دست و گردن و ساق دارد و
     * تنه و ران در مشِ «outfit» است. پس آن لباسِ تنگ همان بدنِ ماست — هم
     * برای برش‌زدن، هم در نمایش (پنهان‌کردنش تنه را خالی می‌گذارد).
     */
    const named = (pattern) => json.nodes
        .map((node, i) => ({ node, i }))
        .filter(({ node }) => node.mesh !== undefined && pattern.test(node.name))
        .map(({ node }) => node.mesh);
    const bodyMeshes = meshes?.body ?? [...named(/body/i), ...named(/outfit|cloth|top|bottom|footwear/i)];
    const headMeshes = meshes?.head ?? named(/head/i);
    const merge = (list) => {
        const parts = list.map((index) => {
            const part = skinMesh(glb, index, posed.world);

            if (warp) {
                for (let v = 0; v < part.count; v++) {
                    const moved = warp(
                        [part.positions[v * 3], part.positions[v * 3 + 1], part.positions[v * 3 + 2]],
                        groupOfBone(nodes[part.dominant[v]].name),
                    );

                    part.positions[v * 3] = moved[0];
                    part.positions[v * 3 + 1] = moved[1];
                    part.positions[v * 3 + 2] = moved[2];
                }
            }

            return part;
        });
        const count = parts.reduce((sum, part) => sum + part.count, 0);
        const positions = new Float32Array(count * 3);
        const dominant = new Uint16Array(count);
        let at = 0;

        for (const part of parts) {
            positions.set(part.positions, at * 3);
            dominant.set(part.dominant, at);
            at += part.count;
        }

        return { positions, dominant, count };
    };
    const head = merge(headMeshes.length ? headMeshes : bodyMeshes);
    // گردن در مشِ سر است؛ برای برش‌زدنِ تنه، سر هم شمرده می‌شود
    const body = merge([...bodyMeshes, ...headMeshes]);

    /* دسته‌بندیِ رأس‌ها از روی استخوانِ غالب */
    const kind = new Uint8Array(body.count); // 0 تنه، 1 بازوی چپ، 2 بازوی راست، 3 پای چپ، 4 پای راست، 5 سر/گردن
    const groupOf = groupOfBone;

    for (let v = 0; v < body.count; v++) {
        kind[v] = groupOf(nodes[body.dominant[v]].name);
    }

    let top = -Infinity;

    for (let v = 0; v < head.count; v++) {
        top = Math.max(top, head.positions[v * 3 + 1]);
    }

    for (let v = 0; v < body.count; v++) {
        top = Math.max(top, body.positions[v * 3 + 1]);
    }

    const height = top * 100; // سانتی‌متر
    const down = (y) => (top - y) * 100; // y رو به پایین، از فرقِ سر

    const shoulderJoint = joint('LeftArm');

    /*
     * برشِ افقیِ یک گروه: نیم‌پهنا، جلو، پشت — بیضی‌ای که *همهٔ* نقطه‌ها را
     * در بر بگیرد.
     *
     * برخوردگر در هر تراز یک بیضی است (نیم‌پهنا و دو نیم‌عمق). اگر نیم‌عمق
     * فقط بیشینهٔ z باشد، برآمدگی‌ای که وسط نیست از بیضی بیرون می‌زند: سینهٔ
     * آواتار در x=±۸ با z=۱۳٫۵ بود و بیضیِ rx=۱۵٫۷، f=۱۳٫۸ در همان x فقط
     * ۱۱٫۹ عمق دارد — یک‌ونیم سانتی‌متر بیرون، و همان دو لکهٔ سفیدِ سینه در
     * عکس. پس برای هر نقطه نیم‌عمقی که بیضی از رویش رد شود حساب می‌شود:
     * f ≥ z / √(1 − (x/rx)²)، و بیشینه‌اش برداشته می‌شود.
     */
    const slice = (y, groups, band = 0.012, reach = Math.abs(shoulderJoint[0]) + 0.01, keep = 0.97) => {
        const points = [];
        let rx = 0;

        for (let v = 0; v < body.count; v++) {
            if (! groups.includes(kind[v]) || Math.abs(body.positions[v * 3 + 1] - y) > band) {
                continue;
            }

            // بیرونِ مفصلِ شانه دیگر تنه نیست؛ دلتوئید و بازوست (مگر برای خودِ سرشانه)
            if (Math.abs(body.positions[v * 3]) > reach) {
                continue;
            }

            rx = Math.max(rx, Math.abs(body.positions[v * 3]));
            points.push([body.positions[v * 3], body.positions[v * 3 + 2]]);
        }

        if (! points.length) {
            return null;
        }

        /*
         * پهلوها بیضی نیستند — مقطعِ تن مستطیلِ گردگوشه است — و نقطه‌ای که
         * درست کنارِ پهلوست با هر عمقی بیضی را باد می‌کند (x/rx→۱). پس سهمِ
         * پهلو سقف دارد (۰٫۸) و به‌جای بیشینه، صدکِ ۹۷ گرفته می‌شود تا یکی‌دو
         * رأسِ پرت جلو و پشت را دو برابر نکنند.
         */
        /*
         * مقطع لزوماً دورِ z=۰ نیست (تنه در قابِ «بازو روی z=۰» چند سانتی‌متر
         * جلوتر است). بیضی دورِ مرکزِ خودِ مقطع بسته می‌شود و بعد نیم‌عمق‌ها
         * به z=۰ برگردانده می‌شوند: جلو = f′ + zc، پشت = b′ − zc. وگرنه نقطه‌های
         * پهلو با z≈zc جلو را تا ۲۸ سانتی‌متر باد می‌کردند (اندازه‌گیری شد).
         */
        let zMax = -Infinity;
        let zMin = Infinity;

        for (const [, z] of points) {
            zMax = Math.max(zMax, z);
            zMin = Math.min(zMin, z);
        }

        const zc = (zMax + zMin) / 2;
        const fronts = [];
        const backs = [];

        for (const [x, z] of points) {
            const across = Math.min(0.8, Math.abs(x) / Math.max(1e-6, rx));
            const need = Math.abs(z - zc) / Math.sqrt(1 - across * across);

            (z - zc >= 0 ? fronts : backs).push(need);
        }

        /*
         * ...و حالا مرکزِ مقطع خودش در حلقه می‌رود (`z`، سانتی‌متر، مثبت = جلو)
         * و جلو/پشت نسبت به همان مرکزند. برخوردگر و چیدن (ستونِ ششمِ نیم‌رخ در
         * drapeBody) دورِ همین مرکز می‌بندند؛ پیش از این نیم‌عمق‌ها به z=۰
         * برگردانده می‌شد و گردن — که پنج سانتی‌متر پشتِ مرکزِ سینه است — جلویش
         * دو سانتی‌متر و پشتش دوازده درمی‌آمد: یقه جلوی گردن می‌ایستاد.
         */
        return {
            rx: rx * 100,
            front: Math.max(0.005, percentile(fronts, keep)) * 100,
            back: Math.max(0.005, percentile(backs, keep)) * 100,
            z: zc * 100,
            count: points.length,
        };
    };

    /* ترازها از مفصل‌ها */
    const neckJoint = joint('Neck');
    const headJoint = joint('Head');
    const spine2 = joint('Spine2');
    const spine = joint('Spine');
    const hips = joint('Hips');
    const knee = joint('LeftLeg');
    const ankle = joint('LeftFoot');
    const wrist = joint('LeftHand');

    /* فاق: پایین‌ترین ترازی که میانِ دو پا (x≈0) پارچه‌ای از تنه هست */
    let crotchY = hips[1];

    for (let y = hips[1]; y > knee[1]; y -= 0.005) {
        let between = false;

        for (let v = 0; v < body.count; v++) {
            if (kind[v] === 0 && Math.abs(body.positions[v * 3 + 1] - y) < 0.006 && Math.abs(body.positions[v * 3]) < 0.02) {
                between = true;
                break;
            }
        }

        if (! between) {
            break;
        }

        crotchY = y;
    }

    const levelY = {
        neck: neckJoint[1] + 0.02,
        shoulder: shoulderJoint[1] + 0.01,
        bust: spine2[1] - 0.02,
        underBust: (spine2[1] + spine[1]) / 2 - 0.02,
        waist: spine[1] - 0.01,
        highHip: (spine[1] + hips[1]) / 2 - 0.01,
        hip: hips[1] - 0.03,
        crotch: crotchY,
        ankle: ankle[1] + 0.01,
    };

    /*
     * بازو: در طولِ محورِ خودش (مفصلِ شانه تا مچ)، شعاع = بیشترین فاصلهٔ
     * شعاعی، در هشت گوهٔ زاویه‌ای میانگین‌گرفته تا برآمدگیِ آرنج آن را باد نکند.
     */
    const axisFrom = shoulderJoint;
    const axis = normalize([wrist[0] - axisFrom[0], wrist[1] - axisFrom[1], wrist[2] - axisFrom[2]]);
    const armLength = Math.hypot(wrist[0] - axisFrom[0], wrist[1] - axisFrom[1], wrist[2] - axisFrom[2]) * 100;
    const armRadius = (along, band = 0.015) => {
        const sectors = new Array(8).fill(0);

        for (let v = 0; v < body.count; v++) {
            // بازو، یا دلتوئیدی که به استخوانِ ترقوه وزن خورده ولی بیرونِ مفصل است
            const outer = kind[v] === 0 && body.positions[v * 3] > axisFrom[0] + 0.01;

            if (kind[v] !== 1 && ! outer) {
                continue;
            }

            const d = [body.positions[v * 3] - axisFrom[0], body.positions[v * 3 + 1] - axisFrom[1], body.positions[v * 3 + 2] - axisFrom[2]];
            const t = d[0] * axis[0] + d[1] * axis[1] + d[2] * axis[2];

            if (Math.abs(t - along) > band) {
                continue;
            }

            const r = [d[0] - axis[0] * t, d[1] - axis[1] * t, d[2] - axis[2] * t];
            const radial = Math.hypot(r[0], r[1], r[2]);
            const angle = Math.atan2(r[2], r[0]);
            const sector = Math.floor(((angle + Math.PI) / (2 * Math.PI)) * 8) % 8;

            sectors[sector] = Math.max(sectors[sector], radial);
        }

        /*
         * میانگینِ نیمهٔ بزرگ‌ترِ گوه‌ها، نه میانگینِ همه.
         *
         * میانگینِ هشت گوه، بازو را لاغرتر از پوستش می‌داد: اندازه گرفته شد،
         * پوستِ آواتار در ترازِ بازو تا ۲٫۲ سانتی‌متر بیرونِ برخوردگر می‌ماند و
         * آستینِ تیشرتِ آواتار از میانِ آستینِ پیراهن پیدا بود. با نیمهٔ
         * بزرگ‌تر، دایرهٔ برخوردگر مقطعِ تخمِ‌مرغیِ بازو را می‌پوشاند و برآمدگیِ
         * یک‌گوه‌ایِ آرنج همچنان آن را باد نمی‌کند.
         */
        const filled = sectors.filter((r) => r > 0).sort((a, b) => b - a);
        const top = filled.slice(0, Math.max(1, Math.ceil(filled.length / 2)));

        return top.length ? (top.reduce((a, b) => a + b, 0) / top.length) * 100 : 0;
    };
    /* حلقه‌های تنه، از گردن تا فاق */
    const torsoRing = (y) => {
        const s = slice(y, [0, 5], 0.015) || slice(y, [0, 5], 0.03) || { rx: 5, front: 5, back: 5, z: 0 };

        return { y: down(y), rx: s.rx, front: s.front, back: s.back, z: s.z || 0 };
    };
    /*
     * حلقهٔ سرشانه پوستِ خودِ شانه است، تا سرِ دلتوئید.
     *
     * برشِ معمولی بیرونِ مفصل را دور می‌ریزد و بازو را نمی‌شمارد؛ در خطِ سرشانه
     * همین باعث می‌شد جلوی حلقه ۳ سانتی‌متر درآید (فقط گردنِ باریک می‌ماند) و
     * پارچهٔ سرشانه زیرِ پوستِ آواتار برود — دو لکهٔ سفیدِ سرِ شانه در عکس.
     */
    const shoulderRingAt = (y) => {
        const wide = Math.abs(shoulderJoint[0]) + 0.08;
        // سرِ تنه است؛ همهٔ پوستِ شانه را می‌پوشاند (صدکِ صد)، وگرنه پشتِ شانه از زیرِ سرآستین پیدا می‌ماند
        const s = slice(y, [0, 1, 2, 5], 0.02, wide, 1) || slice(y, [0, 1, 2, 5], 0.04, wide, 1) || { rx: 5, front: 5, back: 5, z: 0 };

        return { y: down(y), rx: s.rx, front: s.front, back: s.back, z: s.z || 0 };
    };
    /*
     * حلقهٔ گردن از پوستِ خودِ گردن (مشِ سر)، نه از برشِ تنه در آن تراز.
     *
     * در ترازِ گردن، برشِ تنه ذوزنقه و ترقوه را هم می‌گیرد و دورِ «گردن» ۴۳
     * تا ۴۹ سانتی‌متر درمی‌آید؛ شعاعِ گردنِ برخوردگر و جای یقه از همین حلقه
     * می‌آید و یقه دورتر از گردن چیده می‌شد. فقط رأس‌های گردن/سر، تا ۸ سانتی‌متر
     * از محور.
     */
    const neckOnly = slice(levelY.neck, [5], 0.015, 0.08) || slice(levelY.neck, [5], 0.03, 0.08);
    const neckRing = neckOnly && neckOnly.rx > 3 ? { y: down(levelY.neck), rx: neckOnly.rx, front: neckOnly.front, back: neckOnly.back, z: neckOnly.z || 0 } : torsoRing(levelY.neck);
    /*
     * ترازِ «سرشانه» به قراردادِ armJoint(): مفصلِ بازو نیم‌شعاع زیرِ آن است،
     * پس خطِ سرشانه نیم‌شعاعِ سرِ بازو بالاتر از مفصلِ خودِ آواتار می‌نشیند و
     * حلقه‌های میانی میانِ گردن و همین خط چیده می‌شوند تا ترتیبشان به هم نخورد.
     */
    const ballR = Math.max(1.5, armRadius(0.0, 0.04)) / 100;
    const shoulderLineY = shoulderJoint[1] + ballR * 0.5;
    const torso = [
        { ...torsoRing(headJoint[1] - 0.01), y: down(headJoint[1] - 0.01) },
        neckRing,
        torsoRing((levelY.neck * 0.6) + (shoulderLineY * 0.4)),
        torsoRing((levelY.neck * 0.24) + (shoulderLineY * 0.76)),
        shoulderRingAt(shoulderLineY),
        torsoRing((shoulderLineY * 0.45) + (levelY.bust * 0.55)),
        torsoRing(levelY.bust),
        torsoRing(levelY.underBust),
        torsoRing(levelY.waist),
        torsoRing(levelY.highHip),
        torsoRing(levelY.hip),
        torsoRing(levelY.crotch + 0.005),
    ];

    const arm = [0, 0.16, 0.46, 0.62, 0.88, 1, 1.1, 1.16].map((share) => ({
        y: armLength * share,
        // سرِ بازو زیرِ سرشانه پنهان است؛ برای دو حلقهٔ اول نوارِ پهن‌تر
        r: Math.max(1.5, armRadius((armLength * share) / 100, share < 0.2 ? 0.04 : 0.015)),
    }));

    /* پا: پای چپ، هر تراز مرکز و شعاعِ خودش */
    const legSpan = (levelY.crotch - levelY.ankle) * 100;
    const legRing = (share) => {
        const y = levelY.crotch - (legSpan * share) / 100;
        let minX = Infinity;
        let maxX = -Infinity;
        let minZ = Infinity;
        let maxZ = -Infinity;

        for (let v = 0; v < body.count; v++) {
            if (kind[v] !== 3 && ! (kind[v] === 0 && share < 0.05 && body.positions[v * 3] > 0)) {
                continue;
            }

            if (Math.abs(body.positions[v * 3 + 1] - y) > 0.012) {
                continue;
            }

            minX = Math.min(minX, body.positions[v * 3]);
            maxX = Math.max(maxX, body.positions[v * 3]);
            minZ = Math.min(minZ, body.positions[v * 3 + 2]);
            maxZ = Math.max(maxZ, body.positions[v * 3 + 2]);
        }

        if (! Number.isFinite(minX)) {
            return { y: legSpan * share, r: 5, x: 8 };
        }

        return {
            y: legSpan * share,
            r: (((maxX - minX) + (maxZ - minZ)) / 4) * 100,
            x: ((minX + maxX) / 2) * 100,
        };
    };
    const leg = [0, 0.22, 0.5, 0.64, 0.88, 1].map(legRing);

    /* سر */
    let headMinY = Infinity;
    let headMaxX = 0;

    for (let v = 0; v < head.count; v++) {
        headMinY = Math.min(headMinY, head.positions[v * 3 + 1]);
        headMaxX = Math.max(headMaxX, Math.abs(head.positions[v * 3]));
    }

    const headHeight = (top - headMinY) * 100;
    const chinY = headJoint[1] - 0.01;

    /*
     * حلقهٔ سرشانه، به قراردادِ armJoint(): مفصلِ بازو از همین حلقه حساب
     * می‌شود (x = rx − ۰٫۳۵·r، y = y + ۰٫۵·r)؛ پس حلقه چنان گذاشته می‌شود که
     * مفصل دقیقاً روی مفصلِ خودِ آواتار بیفتد.
     */
    const ball = arm[0].r;
    const shoulderRing = {
        y: down(shoulderLineY),
        rx: Math.abs(shoulderJoint[0]) * 100 + ball * 0.35,
        front: torso[4].front,
        back: torso[4].back,
        z: torso[4].z || 0,
    };

    torso[4] = shoulderRing;

    return {
        height,
        childish: 0,
        level: {
            neck: down(levelY.neck),
            shoulder: shoulderRing.y,
            bust: down(levelY.bust),
            underBust: down(levelY.underBust),
            waist: down(levelY.waist),
            highHip: down(levelY.highHip),
            hip: down(levelY.hip),
            crotch: down(levelY.crotch),
            ankle: down(levelY.ankle),
        },
        head: {
            radius: headMaxX * 100,
            centre: headHeight * 0.52,
            neckTop: down(chinY),
        },
        shoulderRing,
        // محورِ بازو چند سانتی‌متر جلو/عقبِ مرکزِ تن است (مثبت = جلو)
        armZ: ((joint('LeftArm')[2] + joint('LeftHand')[2]) / 2) * 100,
        neckRadius: (neckRing.front + neckRing.back) / 2,
        shoulderHalf: shoulderRing.rx,
        armLength,
        torso,
        arm,
        leg,
        /* برای مرورگر: همان ژست، و مش‌هایی که باید پنهان شوند */
        avatar: {
            pose: posed.pose,
            hide: [],
            top,
        },
    };
};

/**
 * کجیِ بازو که تنه را رد کند.
 *
 * مفصلِ شانهٔ این آواتار در x=۱۵ سانتی‌متر است و نیم‌پهنای سینه‌اش ۱۵٫۹ —
 * یعنی مفصل *درونِ* پهنای تنه است (ریگ برای T-pose ساخته شده). اگر بازو با
 * کجیِ ثابتِ مانکنِ محاسباتی (۴٫۹ درجه) آویزان شود، از میانِ پهلوی سینه رد
 * می‌شود، گودیِ زیربغلِ برخوردگر تنه را تا ۹٫۸ سانتی‌متر باریک می‌کند و
 * لباس همان‌جا زیرِ پوستِ آواتار می‌رود (تا ۴ سانتی‌متر؛ لکه‌های سفیدِ سینه در
 * عکس). پس کجی از خودِ بدن می‌آید: کمترین زاویه‌ای که محورِ بازو در همهٔ
 * ترازهای زیرِ مفصل بیرونِ تنه به‌اضافهٔ شعاعِ بازو بماند.
 */
export const clearingTilt = (body, floor = ARM_TILT) => {
    const joint = { x: body.shoulderRing.rx - body.arm[0].r * 0.35, y: body.shoulderRing.y + body.arm[0].r * 0.5 };
    let tilt = floor;

    for (const ring of body.torso) {
        const below = ring.y - joint.y; // سانتی‌متر زیرِ مفصل (y رو به پایین)

        /*
         * از آرنج به پایین سنجیده می‌شود، نه از زیربغل.
         *
         * جاروبِ کجی روی سه لباس (بازوی لختِ پیراهن/بلیزر/ترنچ):
         *   ۴٫۹°  ⇒ ۰ / ۲ / ۱۱      ۶٫۹° ⇒ ۲۳ / ۰ / ۹
         *   ۸٫۶°  ⇒ ۲۲ / ۰ / ۱۷     ۱۱٫۲° ⇒ ۲۲ / ۰ / ۱۲
         * بازوی بازتر سرِ آستینِ یک‌تکه را از سرشانه می‌اندازد؛ آنچه از تنه باید
         * رد شود آرنج است، و سرِ بازو کنارِ زیربغل می‌ماند — مثل تنِ واقعی.
         */
        if (below < 20 || below > 34) {
            continue;
        }

        const r = body.arm.reduce((best, row) => (Math.abs(row.y - below) < Math.abs(best.y - below) ? row : best), body.arm[0]).r;
        const need = Math.atan((ring.rx + r + 0.5 - joint.x) / below);

        tilt = Math.max(tilt, need);
    }

    return Math.min(tilt, 0.5);
};

/** بدنِ آماده از یک GLB: ژست، (اختیاری) اندازه کردن به تنِ مشتری، برش. */
export const prepareAvatar = (glb, want = null) => {
    let posed = poseArms(glb.json);
    let body = bodyFromAvatar(glb, posed);
    const tilt = clearingTilt(body);

    if (tilt > ARM_TILT + 1e-6) {
        posed = poseArms(glb.json, tilt);
        body = bodyFromAvatar(glb, posed);
    }

    body.armTilt = tilt;

    /*
     * محورِ بازو روی z=۰ می‌نشیند — قراردادِ موتور.
     *
     * شانهٔ آواتار ۴٫۶ سانتی‌متر عقب‌ترِ مرکزِ تنه است (طبیعی است، ولی موتور
     * حلقهٔ آستین را در z=۰ می‌بندد). با «عمقِ بازو» در جدول، آستینِ دوتکهٔ
     * کت رسمی به هم می‌ریخت: کپ به حلقه‌ای دوخته می‌شد که ۴٫۶ سانتی‌متر جلوتر
     * از بازو بود و پنلِ زیر تا کنارِ تنه فرو می‌رفت (پوشش ۱۶۵°، بازوی لخت
     * ۷۱ از ۲۸۸؛ با armZ=۰ همان لباس ۳۴۵° و صفر). پس به‌جای عمق دادن به
     * موتور، خودِ تن جابه‌جا می‌شود: کلِ مش و مفصل‌ها آن‌قدر جلو می‌آیند که بازو
     * روی z=۰ بیفتد؛ تنه با جلو/پشتِ جدای هر حلقه همچنان درست توصیف می‌شود.
     */
    const byName = new Map(glb.json.nodes.map((node, i) => [node.name, i]));
    const at = (name) => posed.world[byName.get(name)].t;
    /*
     * ...ولی نه با جابه‌جاییِ کلِ تن: تنه سرِ مرکزِ خودش می‌ماند و *بازو* جلو
     * می‌آید. کلِ تن که عقب برود، تنه ۴٫۶ سانتی‌متر جلوتر از استوانهٔ چیدن
     * می‌افتد و پنلِ جلو درونِ سینه چیده می‌شود (کت رسمی ۹۰°، بازوی لخت ۹۰).
     * پس مرکزِ مقطعِ تنه در ترازِ سینه صفر می‌شود و بازوها (رأس‌ها و مفصل‌ها)
     * آن‌قدر جلو می‌آیند که محورشان روی z=۰ بیفتد — چند سانتی‌متر آزادیِ
     * آناتومیک، همان‌طور که مانکنِ محاسباتی بازو را در میانهٔ عمقِ تنه آویزان
     * می‌کند. جابه‌جایی از سرِ شانه تا ده سانتی‌متر زیرِ مفصل نرم می‌شود تا مش
     * پاره نشود.
     */
    const chestY = at('Spine2')[1];
    const torsoMeshes = glb.json.nodes.filter((n) => n.mesh !== undefined && /body|outfit/i.test(n.name)).map((n) => n.mesh);
    let zMax = -Infinity;
    let zMin = Infinity;

    for (const index of torsoMeshes) {
        const part = skinMesh(glb, index, posed.world);

        for (let v = 0; v < part.count; v++) {
            if (Math.abs(part.positions[v * 3 + 1] - chestY) > 0.02 || Math.abs(part.positions[v * 3]) > 0.10) {
                continue;
            }

            if (groupOfBone(glb.json.nodes[part.dominant[v]].name) !== 0) {
                continue;
            }

            zMax = Math.max(zMax, part.positions[v * 3 + 2]);
            zMin = Math.min(zMin, part.positions[v * 3 + 2]);
        }
    }

    const torsoZ = Number.isFinite(zMax) ? (zMax + zMin) / 2 : 0;
    const armZ = (at('LeftArm')[2] + at('LeftHand')[2]) / 2;
    const jointY = at('LeftArm')[1];
    const armForward = torsoZ - armZ;
    const centre = (p, kind) => {
        const z = p[2] - torsoZ;

        if (kind === 1 || kind === 2) {
            const blend = Math.min(1, Math.max(0, (jointY + 0.02 - p[1]) / 0.12));

            return [p[0], p[1], z + armForward * blend];
        }

        return [p[0], p[1], z];
    };
    let warp = centre;

    if (want && Object.keys(want).length) {
        const frame = { jointX: Math.abs(at('LeftArm')[0]), jointY: at('LeftArm')[1], jointZ: at('LeftArm')[2], legX: Math.abs(at('LeftUpLeg')[0]) };
        const fit = warpFor(body, want, frame);

        warp = (p, kind) => centre(fit(p, kind), kind);
    }

    body = bodyFromAvatar(glb, posed, null, warp);
    body.armTilt = tilt;

    /*
     * گذرِ دوم: حلقه‌ها *دقیقاً* به اندازهٔ مشتری.
     *
     * نسبتِ گذرِ اول از حلقه‌های تنِ خام می‌آید و بیضیِ دربرگیرنده خطی نیست:
     * اندازه گرفته شد، پس از یک گذر دورِ سینه ۹۵٫۸ بود به‌جای ۹۲، کمر ۷۸٫۸
     * به‌جای ۷۴، بازو ۳۱٫۲ به‌جای ۲۸٫۵ — یعنی لباسی که برای ۹۲ بریده شده روی
     * تنی ۹۶ می‌نشست و روی سینه کش می‌آمد. تنِ گذرِ اول دوباره اندازه می‌شود و
     * نسبتِ باقی‌مانده روی همان تن می‌رود؛ حاشیهٔ پوستِ تنه (TORSO_MARGIN، در
     * خروجی اضافه می‌شود) از هدفِ دورهای تنه کم می‌شود تا حلقهٔ نهایی همان
     * عددِ متر باشد.
     */
    if (want && Object.keys(want).length) {
        const jointA = warp(at('LeftArm'), 1);
        const handA = warp(at('LeftHand'), 1);
        const frameB = { jointX: Math.abs(jointA[0]), jointY: jointA[1], jointZ: jointA[2], legX: Math.abs(warp(at('LeftUpLeg'), 3)[0]) };
        const wantB = { ...want };
        const slack = 2 * Math.PI * TORSO_MARGIN;

        for (const key of ['bust', 'under_bust', 'waist', 'high_hip', 'hip']) {
            if (typeof wantB[key] === 'number') {
                wantB[key] = Math.max(1, wantB[key] - slack);
            }
        }

        for (const key of ['bicep', 'elbow', 'wrist']) {
            if (typeof wantB[key] === 'number') {
                wantB[key] = Math.max(1, wantB[key] - 2 * Math.PI * ARM_MARGIN);
            }
        }

        // محورِ بازو در گذرِ اول: همان محوری که مش با آن پیچیده شده
        const axisA = normalize([handA[0] - jointA[0], handA[1] - jointA[1], handA[2] - jointA[2]]);
        const tiltA = Math.atan2(Math.abs(axisA[0]), Math.abs(axisA[1]));
        const first = warp;
        const fitB = warpFor({ ...body, armTilt: tiltA }, wantB, frameB);

        warp = (p, kind) => fitB(first(p, kind), kind);
        body = bodyFromAvatar(glb, posed, null, warp);
        body.armTilt = tilt;
    }

    return { posed, body, warp };
};

/**
 * حاشیهٔ پوست روی حلقه‌های تنه (سانتی‌متر شعاع).
 *
 * بیضی هرگز مقطعِ واقعی را دقیق نمی‌پوشاند و سنجش نشان داد پوستِ آواتار تا
 * ۱٫۶ سانتی‌متر بیرونِ برخوردگر می‌ماند (پهلوی سینه) — لکه‌های سفیدِ زیرِ پارچه.
 * چهار میلی‌متر برای همهٔ حلقه‌های تنه؛ گذرِ دومِ اندازه کردن همین را از هدف
 * کم می‌کند تا دورِ نهایی همان عددِ مشتری بماند.
 */
export const TORSO_MARGIN = 0.4;

/** همان حاشیه روی حلقه‌های بازو (سانتی‌متر شعاع)؛ مقطعِ بازو دایره نیست و پوستش از دایره بیرون می‌زد */
export const ARM_MARGIN = 0.3;


/* ---------------------------------------------------------------------------
 * اندازه کردنِ آواتار به تنِ مشتری
 * ---------------------------------------------------------------------------
 * آواتار یک تن است با اندازه‌های خودش؛ مشتری تنِ دیگری دارد. لباس باید روی تنِ
 * مشتری دوخته شود، پس آواتار به همان اندازه‌ها درمی‌آید: قد با مقیاسِ عمودی،
 * دورها با مقیاسِ شعاعی دورِ محورِ همان اندام (تنه، بازو، پا) که میانِ ترازهای
 * اندازه‌گیری خطی می‌رود، پهنای سرشانه با جابه‌جاییِ مفصلِ بازو. هر رأس و هر
 * مفصل با همین تابع می‌رود، پس مشِ پخته‌شده و حلقه‌های حل‌کننده یک تن‌اند.
 */

const girthOfRing = (ring) => {
    const a = ring.rx;
    const c = (ring.front + ring.back) / 2;

    return Math.PI * (3 * (a + c) - Math.sqrt((3 * a + c) * (a + 3 * c)));
};

/** اندازه‌های یک بدن (سانتی‌متر)، همان کلیدهایی که buildBody می‌خواند. */
export const measurementsOf = (body) => {
    const at = (y) => body.torso.reduce((best, r) => (Math.abs(r.y - y) < Math.abs(best.y - y) ? r : best), body.torso[0]);
    const level = body.level;

    return {
        height: body.height,
        bust: girthOfRing(at(level.bust)),
        under_bust: girthOfRing(at(level.underBust)),
        waist: girthOfRing(at(level.waist)),
        high_hip: girthOfRing(at(level.highHip)),
        hip: girthOfRing(at(level.hip)),
        neck: girthOfRing(at(level.neck)),
        shoulder_width: body.shoulderHalf * 2,
        arm_length: body.armLength,
        bicep: 2 * Math.PI * body.arm[1].r,
        elbow: 2 * Math.PI * body.arm[2].r,
        wrist: 2 * Math.PI * body.arm[5].r,
        thigh: 2 * Math.PI * body.leg[0].r,
        knee: 2 * Math.PI * body.leg[2].r,
        ankle: 2 * Math.PI * body.leg[5].r,
        back_length: level.waist - level.neck,
        waist_to_hip: level.hip - level.waist,
        inseam: level.ankle - level.crotch,
    };
};

/**
 * تابعِ تغییرِ شکل: از تنِ فعلی به اندازه‌های خواسته‌شده.
 *
 * @param {object} body تنِ فعلی (خروجیِ bodyFromAvatar)
 * @param {object} want اندازه‌های مشتری (سانتی‌متر)؛ هرچه نباشد، همان می‌ماند
 * @param {{ jointX: number, jointY: number, legX: number }} frame جای مفصل‌ها (متر، دستگاهِ جهانی)
 * @returns {(p: number[], kind: number) => number[]} نقطه (متر) و گروهِ اندام → نقطهٔ تازه
 */
export const warpFor = (body, want, frame) => {
    const have = measurementsOf(body);
    const ratio = (key) => (typeof want[key] === 'number' && have[key] > 1 ? want[key] / have[key] : 1);
    const H = body.height / 100;
    const sy = ratio('height');
    const up = (yDown) => H - yDown / 100; // تراز (سانتی‌متر رو به پایین) → متر از زمین

    /* مقیاسِ شعاعیِ تنه، خطی میانِ ترازها */
    const levels = [
        [up(body.level.crotch), ratio('hip')],
        [up(body.level.hip), ratio('hip')],
        [up(body.level.highHip), ratio('high_hip')],
        [up(body.level.waist), ratio('waist')],
        [up(body.level.underBust), ratio('under_bust')],
        [up(body.level.bust), ratio('bust')],
        [up(body.level.shoulder) - 0.03, ratio('bust')],
        /*
         * گردن مقیاس نمی‌شود: حلقهٔ «گردن»ِ برش‌خورده ذوزنقه/ترقوه را هم دارد
         * (دورش ۴۹ درمی‌آید در حالی که گردنِ مشتری ۳۶ است) و مقیاس‌کردنش با
         * آن نسبت، سرشانه را ۲۷٪ می‌فشرد. گردن و سر با قد می‌روند و بس.
         */
        [up(body.level.neck), 1],
    ].sort((a, b) => a[0] - b[0]);
    const torsoScale = (y) => {
        if (y <= levels[0][0]) return levels[0][1];
        if (y >= levels[levels.length - 1][0]) return levels[levels.length - 1][1];

        for (let i = 1; i < levels.length; i++) {
            if (y <= levels[i][0]) {
                const t = (y - levels[i - 1][0]) / Math.max(1e-6, levels[i][0] - levels[i - 1][0]);

                return levels[i - 1][1] + (levels[i][1] - levels[i - 1][1]) * t;
            }
        }

        return 1;
    };

    /* سرشانه: نوکِ شانه به نیم‌پهنای خواسته‌شده می‌رسد؛ اثرش دورِ ترازِ سرشانه */
    const shoulderRatio = ratio('shoulder_width');
    const shoulderY = up(body.level.shoulder);
    const shoulderBlend = (y) => Math.max(0, 1 - Math.abs(y - shoulderY) / 0.12);

    /* بازو: شعاع خطی میانِ بازو/آرنج/مچ، طول با arm_length */
    const armLen = ratio('arm_length');
    /*
     * ایستگاه‌ها روی خودِ حلقه‌های اندازه‌گیری (۰٫۱۶ بازو، ۰٫۴۶ آرنج، ۱ مچ؛
     * ببینید سهم‌های `arm` در bodyFromAvatar). پیش از این ایستگاهِ بازو روی
     * صفر بود و حلقهٔ بازو (۰٫۱۶) آمیزه‌ای از نسبتِ بازو و آرنج می‌گرفت —
     * گذرِ دوم به‌جای همگرا شدن، آرنج را ۲۲ به‌جای ۲۵٫۸ می‌داد.
     */
    const armScale = (t) => {
        const stops = [[0.16, ratio('bicep')], [0.46, ratio('elbow')], [1, ratio('wrist')]];

        if (t <= stops[0][0]) return stops[0][1];
        if (t >= 1) return stops[2][1];

        const [a, b] = t < 0.46 ? [stops[0], stops[1]] : [stops[1], stops[2]];

        return a[1] + (b[1] - a[1]) * ((t - a[0]) / (b[0] - a[0]));
    };
    const tilt = body.armTilt ?? ARM_TILT;
    const armAxis = [Math.sin(tilt), -Math.cos(tilt), 0];
    const jointDx = (want.shoulder_width ? (want.shoulder_width / 2 - 0.35 * body.arm[0].r * ratio('bicep')) / 100 : frame.jointX) - frame.jointX;

    /* پا: شعاع خطی میانِ ران/زانو/مچ */
    const legScale = (t) => {
        const stops = [[0, ratio('thigh')], [0.5, ratio('knee')], [1, ratio('ankle')]];

        if (t <= 0) return stops[0][1];
        if (t >= 1) return stops[2][1];

        const [a, b] = t < 0.5 ? [stops[0], stops[1]] : [stops[1], stops[2]];

        return a[1] + (b[1] - a[1]) * ((t - a[0]) / (b[0] - a[0]));
    };
    const crotchY = up(body.level.crotch);
    const ankleY = up(body.level.ankle);

    return (p, kind) => {
        const [x, y, z] = p;

        if (kind === 1 || kind === 2) {
            const side = kind === 1 ? 1 : -1;
            const ox = side * frame.jointX;
            const d = [x - ox, y - frame.jointY, z - frame.jointZ];
            const along = d[0] * side * armAxis[0] + d[1] * armAxis[1] + d[2] * armAxis[2];
            const t = Math.max(0, along) / Math.max(1e-6, body.armLength / 100);
            const r = [d[0] - side * armAxis[0] * along, d[1] - armAxis[1] * along, d[2] - armAxis[2] * along];
            const rs = armScale(Math.min(1, t));
            const a2 = along * armLen;
            const nx = ox + side * jointDx + side * armAxis[0] * a2 + r[0] * rs;
            const ny = frame.jointY + armAxis[1] * a2 + r[1] * rs;
            const nz = frame.jointZ + armAxis[2] * a2 + r[2] * rs;

            // قد: مفصل با تنه بالا/پایین می‌رود
            return [nx, ny + (frame.jointY * sy - frame.jointY), nz];
        }

        if (kind === 3 || kind === 4) {
            const side = kind === 3 ? 1 : -1;
            const cx = side * frame.legX;
            const t = Math.min(1, Math.max(0, (crotchY - y) / Math.max(1e-6, crotchY - ankleY)));
            const s = legScale(t);

            return [cx + (x - cx) * s, y * sy, z * s];
        }

        // تنه و سر
        const s = y > up(body.level.neck) + 0.02 ? 1 : torsoScale(y);
        const sh = 1 + (shoulderRatio - 1) * shoulderBlend(y);

        return [x * s * sh, y * sy, z * s];
    };
};

/* ---------------------------------------------------------------------------
 * پختنِ GLB: مشِ ژست‌گرفته و اندازه‌شده، بی‌اسکلت، با همان بافت‌ها
 * ---------------------------------------------------------------------------
 * مرورگر همین را می‌کشد؛ چون همان تابعِ تغییرِ شکل روی رأس‌ها و مفصل‌ها
 * رفته، چیزی که دیده می‌شود دقیقاً همان است که پارچه به آن می‌خورد.
 */
import { writeFileSync } from 'node:fs';

export const bakeGlb = (glb, posed, warp, outFile) => {
    const { json, bin, accessor } = glb;
    const nodes = json.nodes;
    const chunks = [];
    let offset = 0;
    const bufferViews = [];
    const accessors = [];
    const push = (bytes, target = null) => {
        const pad = (4 - (bytes.byteLength % 4)) % 4;
        const view = { buffer: 0, byteOffset: offset, byteLength: bytes.byteLength };

        if (target) {
            view.target = target;
        }

        chunks.push(Buffer.from(bytes.buffer, bytes.byteOffset, bytes.byteLength));

        if (pad) {
            chunks.push(Buffer.alloc(pad));
        }

        offset += bytes.byteLength + pad;
        bufferViews.push(view);

        return bufferViews.length - 1;
    };

    /* تصاویر: همان بایت‌ها، در بافرِ تازه */
    const images = (json.images || []).map((image) => {
        const bv = json.bufferViews[image.bufferView];
        const bytes = new Uint8Array(bin.buffer, bin.byteOffset + (bv.byteOffset || 0), bv.byteLength);

        return { ...image, bufferView: push(bytes) };
    });

    const outNodes = [];
    const outMeshes = [];

    for (const node of nodes) {
        if (node.mesh === undefined) {
            continue;
        }

        const part = skinMesh(glb, node.mesh, posed.world);
        const positions = new Float32Array(part.count * 3);

        for (let v = 0; v < part.count; v++) {
            let p = [part.positions[v * 3], part.positions[v * 3 + 1], part.positions[v * 3 + 2]];

            if (warp) {
                p = warp(p, groupOfBone(nodes[part.dominant[v]].name));
            }

            positions[v * 3] = p[0];
            positions[v * 3 + 1] = p[1];
            positions[v * 3 + 2] = p[2];
        }

        /* نرمال‌ها از خودِ مثلث‌ها */
        const normals = new Float32Array(part.count * 3);
        const idx = part.indices;

        for (let t = 0; t < idx.length; t += 3) {
            const a = idx[t] * 3;
            const b = idx[t + 1] * 3;
            const c = idx[t + 2] * 3;
            const ux = positions[b] - positions[a];
            const uy = positions[b + 1] - positions[a + 1];
            const uz = positions[b + 2] - positions[a + 2];
            const vx = positions[c] - positions[a];
            const vy = positions[c + 1] - positions[a + 1];
            const vz = positions[c + 2] - positions[a + 2];
            const nx = uy * vz - uz * vy;
            const ny = uz * vx - ux * vz;
            const nz = ux * vy - uy * vx;

            for (const i of [a, b, c]) {
                normals[i] += nx;
                normals[i + 1] += ny;
                normals[i + 2] += nz;
            }
        }

        for (let v = 0; v < part.count; v++) {
            const l = Math.hypot(normals[v * 3], normals[v * 3 + 1], normals[v * 3 + 2]) || 1;

            normals[v * 3] /= l;
            normals[v * 3 + 1] /= l;
            normals[v * 3 + 2] /= l;
        }

        const prim = json.meshes[node.mesh].primitives[0];
        const uv = prim.attributes.TEXCOORD_0 !== undefined ? accessor(prim.attributes.TEXCOORD_0).data : null;
        let min = [Infinity, Infinity, Infinity];
        let max = [-Infinity, -Infinity, -Infinity];

        for (let v = 0; v < part.count; v++) {
            for (let k = 0; k < 3; k++) {
                min[k] = Math.min(min[k], positions[v * 3 + k]);
                max[k] = Math.max(max[k], positions[v * 3 + k]);
            }
        }

        const attributes = {};

        accessors.push({ bufferView: push(positions, 34962), componentType: 5126, count: part.count, type: 'VEC3', min, max });
        attributes.POSITION = accessors.length - 1;
        accessors.push({ bufferView: push(normals, 34962), componentType: 5126, count: part.count, type: 'VEC3' });
        attributes.NORMAL = accessors.length - 1;

        if (uv) {
            accessors.push({ bufferView: push(new Float32Array(uv), 34962), componentType: 5126, count: part.count, type: 'VEC2' });
            attributes.TEXCOORD_0 = accessors.length - 1;
        }

        const indices = new Uint32Array(idx);

        accessors.push({ bufferView: push(indices, 34963), componentType: 5125, count: indices.length, type: 'SCALAR' });

        outMeshes.push({ name: node.name, primitives: [{ attributes, indices: accessors.length - 1, material: prim.material, mode: 4 }] });
        outNodes.push({ name: node.name, mesh: outMeshes.length - 1 });
    }

    const out = {
        asset: { version: '2.0', generator: 'dokht avatar-body' },
        scene: 0,
        scenes: [{ nodes: outNodes.map((_, i) => i) }],
        nodes: outNodes,
        meshes: outMeshes,
        materials: json.materials || [],
        textures: json.textures || [],
        images,
        samplers: json.samplers || [],
        accessors,
        bufferViews,
        buffers: [{ byteLength: offset }],
    };

    const jsonBytes = Buffer.from(JSON.stringify(out), 'utf8');
    const jsonPad = (4 - (jsonBytes.length % 4)) % 4;
    const binBytes = Buffer.concat(chunks);
    const total = 12 + 8 + jsonBytes.length + jsonPad + 8 + binBytes.length;
    const header = Buffer.alloc(12 + 8);

    header.writeUInt32LE(0x46546C67, 0);
    header.writeUInt32LE(2, 4);
    header.writeUInt32LE(total, 8);
    header.writeUInt32LE(jsonBytes.length + jsonPad, 12);
    header.writeUInt32LE(0x4E4F534A, 16);

    const binHeader = Buffer.alloc(8);

    binHeader.writeUInt32LE(binBytes.length, 0);
    binHeader.writeUInt32LE(0x004E4942, 4);

    writeFileSync(outFile, Buffer.concat([header, jsonBytes, Buffer.alloc(jsonPad, 0x20), binHeader, binBytes]));
};

if (process.argv[1] && process.argv[1].endsWith('avatar-body.mjs')) {
    const glb = readGlb(process.argv[2]);
    const args = process.argv.slice(3);
    const bakeAt = args.includes('--bake') ? args[args.indexOf('--bake') + 1] : null;
    const wantFile = args.find((a) => a.endsWith('.json'));
    const want = wantFile ? JSON.parse(readFileSync(wantFile, 'utf8')) : null;
    const { posed, body, warp } = prepareAvatar(glb, want);

    if (bakeAt) {
        bakeGlb(glb, posed, warp, bakeAt);
    }

    // گودیِ زیربغلِ برخوردگر بیش از این زیرِ پوستِ آواتار نرود؛ ببینید drapeBody
    body.carveCap = 0.5;

    // حاشیهٔ پوست روی تنه؛ گذرِ دومِ اندازه کردن همین را از هدف کم کرده است
    for (const ring of body.torso) {
        ring.rx += TORSO_MARGIN;
        ring.front += TORSO_MARGIN;
        ring.back += TORSO_MARGIN;
    }

    for (const ring of body.arm) {
        ring.r += ARM_MARGIN;
    }
    process.stdout.write(JSON.stringify(body));
}
