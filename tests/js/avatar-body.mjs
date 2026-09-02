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
export const bodyFromAvatar = (glb, posed, meshes = null) => {
    const { json } = glb;
    const nodes = json.nodes;
    const byName = new Map(nodes.map((node, i) => [node.name, i]));
    const joint = (name) => posed.world[byName.get(name)].t;

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
        const parts = list.map((index) => skinMesh(glb, index, posed.world));
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
    const groupOf = (name) => {
        if (/^Left(Arm|ForeArm|Hand)/.test(name)) return 1;
        if (/^Right(Arm|ForeArm|Hand)/.test(name)) return 2;
        if (/^Left(UpLeg|Leg|Foot|Toe)/.test(name)) return 3;
        if (/^Right(UpLeg|Leg|Foot|Toe)/.test(name)) return 4;
        if (/^(Head|Neck)/.test(name)) return 5;

        return 0;
    };

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
    const slice = (y, groups, band = 0.012, reach = Math.abs(shoulderJoint[0]) + 0.01) => {
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
        const fronts = [];
        const backs = [];

        for (const [x, z] of points) {
            const across = Math.min(0.8, Math.abs(x) / Math.max(1e-6, rx));
            const need = Math.abs(z) / Math.sqrt(1 - across * across);

            (z >= 0 ? fronts : backs).push(need);
        }

        return {
            rx: rx * 100,
            front: percentile(fronts, 0.97) * 100,
            back: percentile(backs, 0.97) * 100,
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

        const filled = sectors.filter((r) => r > 0);

        return filled.length ? (filled.reduce((a, b) => a + b, 0) / filled.length) * 100 : 0;
    };
    /* حلقه‌های تنه، از گردن تا فاق */
    const torsoRing = (y) => {
        const s = slice(y, [0, 5], 0.015) || slice(y, [0, 5], 0.03) || { rx: 5, front: 5, back: 5 };

        return { y: down(y), rx: s.rx, front: s.front, back: s.back };
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
        const s = slice(y, [0, 1, 2, 5], 0.02, wide) || slice(y, [0, 1, 2, 5], 0.04, wide) || { rx: 5, front: 5, back: 5 };

        return { y: down(y), rx: s.rx, front: s.front, back: s.back };
    };
    const neckRing = torsoRing(levelY.neck);
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

if (process.argv[1] && process.argv[1].endsWith('avatar-body.mjs')) {
    const glb = readGlb(process.argv[2]);
    let posed = poseArms(glb.json);
    let body = bodyFromAvatar(glb, posed);
    const tilt = clearingTilt(body);

    if (tilt > ARM_TILT + 1e-6) {
        posed = poseArms(glb.json, tilt);
        body = bodyFromAvatar(glb, posed);
    }

    body.armTilt = tilt;
    // گودیِ زیربغلِ برخوردگر بیش از این زیرِ پوستِ آواتار نرود؛ ببینید drapeBody
    body.carveCap = 0.5;

    /*
     * یک سانتی‌متر پوستِ اضافه روی تنه.
     *
     * بیضی هرگز مقطعِ واقعی را دقیق نمی‌پوشاند و سنجش نشان داد پوستِ آواتار
     * تا ۱٫۶ سانتی‌متر بیرونِ برخوردگر می‌ماند (پهلوی سینه، x=۱۳) — همان
     * لکه‌های سفیدِ زیرِ پارچه. پارچهٔ واقعی هم روی پوست می‌نشیند نه درونش؛
     * یک سانتی‌متر برای همهٔ حلقه‌های تنه، بهای ناچیزی برای پوستی که بیرون نزند.
     */
    for (const ring of body.torso) {
        ring.rx += 1;
        ring.front += 1;
        ring.back += 1;
    }
    process.stdout.write(JSON.stringify(body));
}
