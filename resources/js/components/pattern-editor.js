/**
 * ویرایشگر دوبعدی الگو.
 *
 * قرارداد نقطه‌ها همان قرارداد سمت سرور است: واحد سانتی‌متر، x به راست و y به پایین،
 * مبدأ گوشه بالا-چپ هر قطعه و نقطه‌ای که curve دارد با منحنی درجه‌دو و نقطه کنترل
 * (cx, cy) به آن می‌رسیم. قطعه‌ها در یک شبکه چیده می‌شوند و offset هر قطعه فقط برای
 * نمایش است؛ مختصات ذخیره‌شده همیشه محلی همان قطعه است.
 */
export default (config = {}) => ({
    saveUrl: config.saveUrl || '',
    version: config.version || 1,
    defaultAllowance: config.defaultAllowance ?? 1,
    tags: config.tags || {},

    pieces: [],
    offsets: [],
    selected: 0,
    selectedPoint: null,
    hoverPoint: null,

    zoom: 1,
    panX: 0,
    panY: 0,
    canvas: { width: 100, height: 100 },

    show: { seam: true, grainline: true, notches: true, darts: true, markers: true },

    history: [],
    future: [],
    limit: 40,

    dragging: null,
    panning: null,
    saving: false,
    dirty: false,
    toast: null,
    toastType: 'success',

    init() {
        this.pieces = JSON.parse(JSON.stringify(config.pieces || []));
        this.layout();
        this.fit();
    },

    /* ---------------------------------------------------------------- چیدمان */

    layout() {
        const columns = Math.max(1, Math.ceil(Math.sqrt(this.pieces.length || 1)));
        const gap = 6;
        const boxes = this.pieces.map((piece) => this.boundsOf(piece));

        this.offsets = [];
        let x = gap;
        let y = gap;
        let rowHeight = 0;

        this.pieces.forEach((piece, index) => {
            if (index > 0 && index % columns === 0) {
                x = gap;
                y += rowHeight + gap;
                rowHeight = 0;
            }

            const box = boxes[index];
            this.offsets[index] = { x: x - box.minX, y: y - box.minY };
            x += box.width + gap;
            rowHeight = Math.max(rowHeight, box.height);
        });

        const right = this.offsets.reduce((max, offset, index) => Math.max(max, offset.x + boxes[index].maxX), 0);
        const bottom = this.offsets.reduce((max, offset, index) => Math.max(max, offset.y + boxes[index].maxY), 0);

        this.canvas = { width: right + gap, height: bottom + gap };
    },

    boundsOf(piece) {
        const points = this.flatten(piece.outline || []);

        if (!points.length) {
            return { minX: 0, minY: 0, maxX: 1, maxY: 1, width: 1, height: 1 };
        }

        const xs = points.map((point) => point.x);
        const ys = points.map((point) => point.y);
        const minX = Math.min(...xs);
        const minY = Math.min(...ys);
        const maxX = Math.max(...xs);
        const maxY = Math.max(...ys);

        return { minX, minY, maxX, maxY, width: Math.max(1, maxX - minX), height: Math.max(1, maxY - minY) };
    },

    fit() {
        this.zoom = 1;
        this.panX = 0;
        this.panY = 0;
    },

    viewBox() {
        const width = this.canvas.width / this.zoom;
        const height = this.canvas.height / this.zoom;

        return `${this.panX} ${this.panY} ${width} ${height}`;
    },

    /* ------------------------------------------------------------- هندسه SVG */

    flatten(outline, segments = 10) {
        const points = [];

        outline.forEach((point, index) => {
            if (index === 0) {
                points.push({ x: point.x, y: point.y });

                return;
            }

            const from = points[points.length - 1];

            if (point.curve) {
                for (let step = 1; step <= segments; step++) {
                    const t = step / segments;
                    const mt = 1 - t;
                    points.push({
                        x: mt * mt * from.x + 2 * mt * t * point.cx + t * t * point.x,
                        y: mt * mt * from.y + 2 * mt * t * point.cy + t * t * point.y,
                    });
                }

                return;
            }

            points.push({ x: point.x, y: point.y });
        });

        return points;
    },

    path(piece) {
        const outline = piece.outline || [];

        if (!outline.length) {
            return '';
        }

        let d = `M ${this.n(outline[0].x)} ${this.n(outline[0].y)}`;

        for (let i = 1; i < outline.length; i++) {
            const point = outline[i];
            d += point.curve
                ? ` Q ${this.n(point.cx)} ${this.n(point.cy)} ${this.n(point.x)} ${this.n(point.y)}`
                : ` L ${this.n(point.x)} ${this.n(point.y)}`;
        }

        if (outline[0].curve) {
            d += ` Q ${this.n(outline[0].cx)} ${this.n(outline[0].cy)} ${this.n(outline[0].x)} ${this.n(outline[0].y)}`;
        }

        return `${d} Z`;
    },

    /** خط برش: آفست بیرونی با جای دوخت هر لبه (همان روش سمت سرور). */
    seamPath(piece) {
        const outline = piece.outline || [];

        if (outline.length < 3) {
            return '';
        }

        const points = [];
        const allowances = [];

        for (let i = 0; i < outline.length; i++) {
            const from = outline[i];
            const to = outline[(i + 1) % outline.length];
            const allowance = this.allowanceOf(piece, i);
            const flat = this.flatten([{ x: from.x, y: from.y }, to]);
            flat.pop();
            flat.forEach((point) => {
                points.push(point);
                allowances.push(allowance);
            });
        }

        const area = this.signedArea(points);
        const sign = area > 0 ? 1 : -1;
        const lines = points.map((point, index) => {
            const next = points[(index + 1) % points.length];
            const dx = next.x - point.x;
            const dy = next.y - point.y;
            const length = Math.hypot(dx, dy);

            if (length < 1e-9) {
                return null;
            }

            const normal = { x: (dy / length) * sign, y: (-dx / length) * sign };
            const allowance = allowances[index];

            return {
                a: { x: point.x + normal.x * allowance, y: point.y + normal.y * allowance },
                b: { x: next.x + normal.x * allowance, y: next.y + normal.y * allowance },
                normal,
                allowance,
            };
        });

        const result = [];

        for (let i = 0; i < points.length; i++) {
            const previous = lines[(i - 1 + points.length) % points.length];
            const current = lines[i];

            if (!previous || !current) {
                result.push(points[i]);
                continue;
            }

            const cap = Math.max(previous.allowance, current.allowance) * 3;
            let corner = this.intersect(previous.a, previous.b, current.a, current.b);

            if (!corner || Math.hypot(corner.x - points[i].x, corner.y - points[i].y) > cap) {
                const bx = previous.normal.x + current.normal.x;
                const by = previous.normal.y + current.normal.y;
                const norm = Math.hypot(bx, by);
                const allowance = Math.max(previous.allowance, current.allowance);

                corner = norm < 1e-9
                    ? previous.b
                    : { x: points[i].x + (bx / norm) * allowance * 1.4, y: points[i].y + (by / norm) * allowance * 1.4 };
            }

            result.push(corner);
        }

        return `M ${result.map((point) => `${this.n(point.x)} ${this.n(point.y)}`).join(' L ')} Z`;
    },

    intersect(a1, a2, b1, b2) {
        const d1x = a2.x - a1.x;
        const d1y = a2.y - a1.y;
        const d2x = b2.x - b1.x;
        const d2y = b2.y - b1.y;
        const denominator = d1x * d2y - d1y * d2x;

        if (Math.abs(denominator) < 1e-9) {
            return null;
        }

        const t = ((b1.x - a1.x) * d2y - (b1.y - a1.y) * d2x) / denominator;

        return { x: a1.x + d1x * t, y: a1.y + d1y * t };
    },

    signedArea(points) {
        let sum = 0;

        for (let i = 0; i < points.length; i++) {
            const a = points[i];
            const b = points[(i + 1) % points.length];
            sum += a.x * b.y - b.x * a.y;
        }

        return sum / 2;
    },

    /* --------------------------------------------------------------- انتخاب */

    get piece() {
        return this.pieces[this.selected] || null;
    },

    selectPiece(index) {
        this.selected = index;
        this.selectedPoint = null;
    },

    selectPoint(index) {
        this.selectedPoint = index;
    },

    allowanceOf(piece, edge) {
        const allowances = piece.edge_allowances || {};
        const value = allowances[edge] ?? allowances[String(edge)] ?? allowances.default ?? this.defaultAllowance;

        return Number(value) || 0;
    },

    setAllowance(edge, value) {
        const piece = this.piece;

        if (!piece) {
            return;
        }

        this.remember();
        piece.edge_allowances = { ...(piece.edge_allowances || {}) };
        piece.edge_allowances[edge] = Math.max(0, Math.min(10, Number(value) || 0));
        this.dirty = true;
    },

    edgeLabel(piece, edge) {
        const tag = (piece.edges || [])[edge] || 'default';

        return this.tags[tag] || tag;
    },

    edgeCount(piece) {
        return (piece.outline || []).length;
    },

    isFoldEdge(piece, edge) {
        return (piece.fold_edges || []).includes(edge);
    },

    /** عمق ساسون را عوض می‌کند و پاهای آن را دوباره می‌چیند. */
    setDartIntake(dartIndex, value) {
        const piece = this.piece;
        const dart = piece && (piece.darts || [])[dartIndex];

        if (!dart || !dart.center) {
            return;
        }

        this.remember();
        const intake = Math.max(0, Math.min(20, Number(value) || 0));
        const half = intake / 2;
        dart.intake = Math.round(intake * 100) / 100;

        dart.legs = dart.axis === 'y'
            ? [
                { x: dart.center.x, y: this.round(dart.center.y - half) },
                { x: dart.center.x, y: this.round(dart.center.y + half) },
            ]
            : [
                { x: this.round(dart.center.x - half), y: dart.center.y },
                { x: this.round(dart.center.x + half), y: dart.center.y },
            ];

        this.dirty = true;
    },

    /* ------------------------------------------------------- کشیدن و بزرگ‌نمایی */

    toCm(event) {
        const svg = this.$refs.canvas;

        if (!svg || !svg.getScreenCTM) {
            return { x: 0, y: 0 };
        }

        const point = svg.createSVGPoint();
        point.x = event.clientX;
        point.y = event.clientY;

        const transformed = point.matrixTransform(svg.getScreenCTM().inverse());

        return { x: transformed.x, y: transformed.y };
    },

    startDrag(event, pieceIndex, pointIndex, kind = 'point') {
        event.preventDefault();
        event.stopPropagation();

        this.selected = pieceIndex;
        this.selectedPoint = pointIndex;
        this.remember();

        this.dragging = { pieceIndex, pointIndex, kind };
    },

    startPan(event) {
        if (this.dragging) {
            return;
        }

        const start = this.toCm(event);
        this.panning = { x: start.x, y: start.y };
    },

    onMove(event) {
        if (this.dragging) {
            const { pieceIndex, pointIndex, kind } = this.dragging;
            const offset = this.offsets[pieceIndex] || { x: 0, y: 0 };
            const cursor = this.toCm(event);
            const x = this.snap(cursor.x - offset.x);
            const y = this.snap(cursor.y - offset.y);
            const point = (this.pieces[pieceIndex].outline || [])[pointIndex];

            if (!point) {
                return;
            }

            if (kind === 'control') {
                point.cx = x;
                point.cy = y;
            } else {
                point.x = x;
                point.y = y;
            }

            this.dirty = true;

            return;
        }

        if (this.panning) {
            const cursor = this.toCm(event);
            this.panX -= cursor.x - this.panning.x;
            this.panY -= cursor.y - this.panning.y;
        }
    },

    endDrag() {
        if (this.dragging) {
            this.layout();
        }

        this.dragging = null;
        this.panning = null;
    },

    onWheel(event) {
        event.preventDefault();
        const factor = event.deltaY < 0 ? 1.15 : 1 / 1.15;
        this.zoom = Math.max(0.3, Math.min(8, this.zoom * factor));
    },

    zoomIn() {
        this.zoom = Math.min(8, this.zoom * 1.2);
    },

    zoomOut() {
        this.zoom = Math.max(0.3, this.zoom / 1.2);
    },

    snap(value) {
        return Math.round(value * 2) / 2;
    },

    round(value) {
        return Math.round(value * 100) / 100;
    },

    n(value) {
        return Math.round((Number(value) || 0) * 1000) / 1000;
    },

    /* --------------------------------------------------------- تاریخچه و ذخیره */

    remember() {
        this.history.push(JSON.stringify(this.pieces));

        if (this.history.length > this.limit) {
            this.history.shift();
        }

        this.future = [];
    },

    undo() {
        if (!this.history.length) {
            return;
        }

        this.future.push(JSON.stringify(this.pieces));
        this.pieces = JSON.parse(this.history.pop());
        this.layout();
        this.dirty = true;
    },

    redo() {
        if (!this.future.length) {
            return;
        }

        this.history.push(JSON.stringify(this.pieces));
        this.pieces = JSON.parse(this.future.pop());
        this.layout();
        this.dirty = true;
    },

    get canUndo() {
        return this.history.length > 0;
    },

    get canRedo() {
        return this.future.length > 0;
    },

    /** اندازه‌های زنده قطعه انتخاب‌شده. */
    get info() {
        const piece = this.piece;

        if (!piece) {
            return null;
        }

        const box = this.boundsOf(piece);
        const points = this.flatten(piece.outline || []);
        const area = Math.abs(this.signedArea(points));

        return {
            width: this.round(box.width),
            height: this.round(box.height),
            area: Math.round(area),
            points: (piece.outline || []).length,
        };
    },

    async save() {
        if (this.saving || !this.saveUrl) {
            return;
        }

        this.saving = true;
        this.toast = null;

        try {
            const response = await fetch(this.saveUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    pieces: this.pieces.map((piece) => ({
                        id: piece.id,
                        outline: piece.outline,
                        darts: piece.darts,
                        notches: piece.notches,
                        grainline: piece.grainline,
                        edge_allowances: piece.edge_allowances,
                    })),
                }),
            });

            if (!response.ok) {
                throw new Error('bad response');
            }

            const data = await response.json();
            this.version = data.version || this.version;
            this.dirty = false;
            this.notify('تغییرها ذخیره شد؛ نسخه ' + this.version + ' ساخته شد.', 'success');
        } catch (error) {
            this.notify('ذخیره نشد. اتصال یا مقادیر را بررسی کنید.', 'error');
        } finally {
            this.saving = false;
        }
    },

    notify(message, type = 'success') {
        this.toast = message;
        this.toastType = type;

        setTimeout(() => {
            this.toast = null;
        }, 4000);
    },
});
