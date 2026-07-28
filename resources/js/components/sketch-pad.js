/*
 * بوم طرح دستی و کارهای سمت مرورگر صفحه «از عکس یا طرح».
 *
 * تقسیم کار عمدی است: هرچه دیده می‌شود (کارت نتیجه، دلیل‌ها، اطمینان، پارامترها)
 * را سرور می‌سازد و اینجا فقط سه چیز انجام می‌شود:
 *   ۱) کشیدن روی بوم با قلم/پاک‌کن/برگشت/پاک‌کردن — با ماوس و لمس.
 *   ۲) فرستادن «نقطه‌های قلم» (نه تصویر بوم) در یک فیلد پنهان؛ مختصات دقیق را
 *      داریم و تحلیل دوباره پیکسل‌ها هیچ چیزی اضافه نمی‌کند.
 *   ۳) راحت‌کردن انتخاب عکس (کشیدن و رهاکردن).
 */

/* فاصله نقطه تا پاره‌خط — برای ساده‌سازی خط و برای پاک‌کن */
const distanceToSegment = (point, from, to) => {
    const dx = to.x - from.x;
    const dy = to.y - from.y;
    const length = dx * dx + dy * dy;

    if (length <= 0) {
        return Math.hypot(point.x - from.x, point.y - from.y);
    }

    const t = Math.max(0, Math.min(1, ((point.x - from.x) * dx + (point.y - from.y) * dy) / length));

    return Math.hypot(point.x - (from.x + t * dx), point.y - (from.y + t * dy));
};

/* ساده‌سازی داگلاس-پوکر: نقطه‌های اضافی قلم را برمی‌دارد بدون تغییر شکل */
const simplify = (points, tolerance) => {
    if (points.length < 3) {
        return points;
    }

    const keep = new Array(points.length).fill(false);
    keep[0] = true;
    keep[points.length - 1] = true;
    const stack = [[0, points.length - 1]];

    while (stack.length) {
        const [start, end] = stack.pop();

        if (end - start < 2) {
            continue;
        }

        let best = 0;
        let index = start;

        for (let i = start + 1; i < end; i++) {
            const distance = distanceToSegment(points[i], points[start], points[end]);

            if (distance > best) {
                best = distance;
                index = i;
            }
        }

        if (best > tolerance) {
            keep[index] = true;
            stack.push([start, index], [index, end]);
        }
    }

    return points.filter((point, index) => keep[index]);
};

export default (config = {}) => ({
    tab: config.tab === 'sketch' ? 'sketch' : 'photo',
    maxImageBytes: config.maxImageBytes || 6 * 1024 * 1024,

    tool: 'pen',
    strokes: [],
    redoStack: [],
    drawing: null,
    message: '',

    photoName: '',
    dragging: false,
    uploading: false,

    init() {
        /* طرح پیشین از سرور برمی‌گردد تا بعد از تحلیل، کشیده‌های کاربر سر جایشان بمانند */
        this.strokes = (config.strokes || []).map((stroke) => stroke.map((point) => ({
            x: Number(point.x),
            y: Number(point.y),
        })));

        this.$nextTick(() => this.resizeCanvas());
        window.addEventListener('resize', () => this.resizeCanvas());
    },

    /* ------------------------------------------------------------------ بوم */

    canvas() {
        return this.$refs.canvas || null;
    },

    resizeCanvas() {
        const canvas = this.canvas();

        if (!canvas) {
            return;
        }

        const rect = canvas.getBoundingClientRect();

        if (rect.width < 1 || rect.height < 1) {
            return;
        }

        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.round(rect.width * ratio);
        canvas.height = Math.round(rect.height * ratio);
        canvas.getContext('2d').setTransform(ratio, 0, 0, ratio, 0, 0);
        this.redraw();
    },

    pointFrom(event) {
        const rect = this.canvas().getBoundingClientRect();

        return {
            x: Math.round(event.clientX - rect.left),
            y: Math.round(event.clientY - rect.top),
        };
    },

    start(event) {
        if (!this.canvas()) {
            return;
        }

        event.preventDefault();
        this.message = '';
        const point = this.pointFrom(event);

        if (this.tool === 'eraser') {
            this.eraseAt(point);

            return;
        }

        this.drawing = [point];
        this.strokes.push(this.drawing);
        this.redoStack = [];

        if (event.pointerId !== undefined && this.canvas().setPointerCapture) {
            try {
                this.canvas().setPointerCapture(event.pointerId);
            } catch (error) {
                /* گرفتن اشاره‌گر اختیاری است */
            }
        }
    },

    move(event) {
        if (!this.canvas()) {
            return;
        }

        if (this.tool === 'eraser') {
            if (event.buttons) {
                this.eraseAt(this.pointFrom(event));
            }

            return;
        }

        if (!this.drawing) {
            return;
        }

        event.preventDefault();
        const point = this.pointFrom(event);
        const last = this.drawing[this.drawing.length - 1];

        if (Math.hypot(point.x - last.x, point.y - last.y) < 2) {
            return;
        }

        this.drawing.push(point);
        this.redraw();
    },

    end() {
        if (this.drawing && this.drawing.length < 3) {
            this.strokes.pop();
        }

        this.drawing = null;
        this.redraw();
    },

    eraseAt(point) {
        const before = this.strokes.length;

        this.strokes = this.strokes.filter((stroke) => !stroke.some((candidate, index) => {
            const next = stroke[index + 1] || candidate;

            return distanceToSegment(point, candidate, next) < 12;
        }));

        if (this.strokes.length !== before) {
            this.redraw();
        }
    },

    undo() {
        const stroke = this.strokes.pop();

        if (stroke) {
            this.redoStack.push(stroke);
            this.redraw();
        }
    },

    redo() {
        const stroke = this.redoStack.pop();

        if (stroke) {
            this.strokes.push(stroke);
            this.redraw();
        }
    },

    clear() {
        this.strokes = [];
        this.redoStack = [];
        this.drawing = null;
        this.redraw();
    },

    get pointCount() {
        return this.strokes.reduce((sum, stroke) => sum + stroke.length, 0);
    },

    redraw() {
        const canvas = this.canvas();

        if (!canvas) {
            return;
        }

        const context = canvas.getContext('2d');
        const ratio = window.devicePixelRatio || 1;
        const width = canvas.width / ratio;
        const height = canvas.height / ratio;

        context.clearRect(0, 0, width, height);

        /* خط راهنمای وسط: کمک می‌کند طرح قرینه کشیده شود */
        context.save();
        context.strokeStyle = '#e7e5e4';
        context.setLineDash([4, 6]);
        context.lineWidth = 1;
        context.beginPath();
        context.moveTo(width / 2, 0);
        context.lineTo(width / 2, height);
        context.stroke();
        context.restore();

        context.strokeStyle = '#1c1917';
        context.lineWidth = 2.5;
        context.lineJoin = 'round';
        context.lineCap = 'round';

        this.strokes.forEach((stroke) => {
            if (stroke.length < 2) {
                return;
            }

            context.beginPath();
            context.moveTo(stroke[0].x, stroke[0].y);
            stroke.slice(1).forEach((point) => context.lineTo(point.x, point.y));
            context.stroke();
        });
    },

    /* --------------------------------------------------------- فرستادن طرح */

    submitSketch(event) {
        const strokes = this.strokes
            .map((stroke) => simplify(stroke, 1.5))
            .filter((stroke) => stroke.length >= 3)
            .slice(0, 40);

        if (!strokes.length) {
            event.preventDefault();
            this.message = 'هنوز چیزی نکشیده‌اید. دور لباس را با یک خط بسته بکشید.';

            return;
        }

        this.$refs.strokes.value = JSON.stringify(strokes);
    },

    /* --------------------------------------------------------- فرستادن عکس */

    pickPhoto(event) {
        const file = (event.target.files || [])[0];

        if (file) {
            this.sendPhoto(file);
        }
    },

    dropPhoto(event) {
        this.dragging = false;
        const file = (event.dataTransfer?.files || [])[0];

        if (!file) {
            return;
        }

        /* فایل رهاشده را داخل خود input می‌گذاریم تا فرم معمولی بفرستدش */
        const transfer = new DataTransfer();
        transfer.items.add(file);
        this.$refs.photoInput.files = transfer.files;

        this.sendPhoto(file);
    },

    sendPhoto(file) {
        if (file.size > this.maxImageBytes) {
            this.message = 'حجم عکس بیشتر از حد مجاز است؛ عکس کوچک‌تری بفرستید.';
            this.$refs.photoInput.value = '';

            return;
        }

        this.message = '';
        this.photoName = file.name;
        this.uploading = true;
        this.$refs.photoForm.submit();
    },
});
