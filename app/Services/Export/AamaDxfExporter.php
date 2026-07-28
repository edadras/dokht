<?php

namespace App\Services\Export;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\GradingService;
use App\Services\Pattern\SeamAllowanceService;

/**
 * خروجی DXF صنعتی الگو در دو گویش «AAMA» و «ASTM».
 *
 * این کلاس با App\Services\Pattern\DxfExporter فرق دارد: آن یکی DXF ساده و
 * خوانا برای هر CAD عمومی می‌سازد و لایه‌ها را با نام قطعه نام‌گذاری می‌کند؛
 * این یکی قرارداد تبادل الگوی صنعت پوشاک را دنبال می‌کند، یعنی لایه‌های
 * **شماره‌دار** با معنای از پیش تعریف‌شده، یک BLOCK برای هر قطعه و INSERT آن
 * در فضای مدل.
 *
 * ─────────────────────────────── نقشه لایه‌ها ───────────────────────────────
 * قرارداد پایه همان جدول ASTM D6673 (برخاسته از مشخصه تبادل الگوی AAMA) است
 * که در نرم‌افزارهای Gerber، Lectra، Optitex و Valentina هم به همین شکل
 * پیاده شده است:
 *
 *   لایه ۱   خط برش قطعه (piece boundary / cut line)      POLYLINE بسته
 *   لایه ۲   نقاط گوشه روی خط برش (turn points)           POINT
 *   لایه ۳   نقاط منحنی روی خط برش (curve points)         POINT
 *   لایه ۴   علامت‌های جفت‌شدن (notches)                   POINT و LINE
 *   لایه ۵   نقطه مرجع سایزبندی (grade reference point)   POINT
 *   لایه ۶   خط تا / قرینه (mirror line)                   LINE
 *   لایه ۷   راستای پارچه (grain line)                     LINE
 *   لایه ۸   خط مرجع راه‌راه (stripe reference line)       LINE
 *   لایه ۹   خط مرجع چهارخانه (plaid reference line)      LINE
 *   لایه ۱۰  خطوط داخلی: ساسون، پیلی، خط نشانه            LINE
 *   لایه ۱۱  بریدگی داخلی (internal cutout)                POLYLINE
 *   لایه ۱۲  سوراخ نشانه (drill hole)                      POINT
 *   لایه ۱۳  متن یادداشت (annotation text)                 TEXT
 *   لایه ۱۴  خط دوخت (sew line / net line)                 POLYLINE بسته
 *   لایه ۱۵  خط دوخت جایگزین و یادداشت سایزبندی            TEXT
 *
 * گویش ASTM افزون بر همین‌ها، هر قلم شناسنامه قطعه را روی لایه شماره‌دار خودش
 * می‌گذارد و همان را به شکل «صفت» (ATTDEF داخل BLOCK و ATTRIB پس از INSERT)
 * هم می‌نویسد تا نرم‌افزار مقصد بتواند آن را داده‌ای بخواند نه متنی:
 *
 *   لایه ۸۰  نام قطعه       (تگ PIECE_NAME)
 *   لایه ۸۱  سایز           (تگ SIZE)
 *   لایه ۸۲  رده/لایه پارچه (تگ CATEGORY)
 *   لایه ۸۳  تعداد برش      (تگ QUANTITY)
 *   لایه ۸۴  یادداشت آزاد   (تگ ANNOTATION)
 *   لایه ۸۵  کد قطعه        (تگ PIECE_CODE)
 *
 * ─────────────────────────── داوری‌های ما (judgement calls) ──────────────────
 * ۱) «piece boundary» در لایه ۱ را خط **برش** گرفته‌ایم (خط دوخت + جای دوخت) و
 *    خط دوخت را در لایه ۱۴ گذاشته‌ایم؛ چون در کارگاه چیزی که کاتر می‌برد همان
 *    خط بیرونی است. اگر قطعه‌ای جای دوخت نداشته باشد، هر دو لایه یکی می‌شوند.
 * ۲) علامت جفت‌شدن در AAMA فقط یک POINT است. در ASTM افزون بر POINT یک پاره‌خط
 *    کوتاه رو به درون قطعه هم کشیده می‌شود که ژرفا و جهت «شکاف» را نشان می‌دهد.
 * ۳) لایه‌های ۸ و ۹ (راه‌راه و چهارخانه) فقط وقتی نوشته می‌شوند که الگو در
 *    meta.stripe یا meta.plaid خط مرجعی داده باشد؛ وگرنه لایه خالی می‌ماند.
 * ۴) نقطه مرجع سایزبندی (لایه ۵) را گوشه بالا-چپ کادر قطعه گرفته‌ایم، چون همان
 *    مبدأ هندسه هر قطعه در این سامانه است و سایزبندی نسبت به آن انجام می‌شود.
 * ۵) متن DXF نسخه R12 فقط اَسکی است؛ نام فارسی قطعه به شکل «کد لاتین» نوشته
 *    می‌شود و نام فارسی الگو در یادداشت ۹۹۹ سرآیند با گریز یونیکد استاندارد
 *    DXF («\U+06AF») می‌آید تا کل فایل اَسکی محض بماند.
 *
 * واحد فایل میلی‌متر است ($INSUNITS = 4) و محور y در CAD به بالاست، پس y الگو
 * قرینه می‌شود. سایزهای سایزبندی‌شده هرکدام BLOCK جداگانه‌ای می‌گیرند.
 */
class AamaDxfExporter
{
    public const FLAVOUR_AAMA = 'aama';

    public const FLAVOUR_ASTM = 'astm';

    /** لایه‌های هندسی مشترک هر دو گویش. */
    public const LAYER_BOUNDARY = '1';

    public const LAYER_TURN_POINTS = '2';

    public const LAYER_CURVE_POINTS = '3';

    public const LAYER_NOTCH = '4';

    public const LAYER_GRADE_REFERENCE = '5';

    public const LAYER_MIRROR = '6';

    public const LAYER_GRAIN = '7';

    public const LAYER_STRIPE = '8';

    public const LAYER_PLAID = '9';

    public const LAYER_INTERNAL_LINE = '10';

    public const LAYER_INTERNAL_CUTOUT = '11';

    public const LAYER_DRILL = '12';

    public const LAYER_ANNOTATION = '13';

    public const LAYER_SEW_LINE = '14';

    public const LAYER_ALTERNATE = '15';

    /** لایه‌های شناسنامه قطعه، ویژه گویش ASTM. */
    public const ASTM_LAYERS = [
        'PIECE_NAME' => '80',
        'SIZE' => '81',
        'CATEGORY' => '82',
        'QUANTITY' => '83',
        'ANNOTATION' => '84',
        'PIECE_CODE' => '85',
    ];

    /** ترتیب لایه‌ها در جدول LAYER. */
    public const GEOMETRY_LAYERS = [
        self::LAYER_BOUNDARY, self::LAYER_TURN_POINTS, self::LAYER_CURVE_POINTS, self::LAYER_NOTCH,
        self::LAYER_GRADE_REFERENCE, self::LAYER_MIRROR, self::LAYER_GRAIN, self::LAYER_STRIPE,
        self::LAYER_PLAID, self::LAYER_INTERNAL_LINE, self::LAYER_INTERNAL_CUTOUT, self::LAYER_DRILL,
        self::LAYER_ANNOTATION, self::LAYER_SEW_LINE, self::LAYER_ALTERNATE,
    ];

    /** بلندای متن یادداشت‌ها (میلی‌متر). */
    protected const TEXT_HEIGHT = 8.0;

    /** فاصله قطعه‌ها در فضای مدل (میلی‌متر). */
    protected const GAP = 50.0;

    public function __construct(
        protected SeamAllowanceService $seams = new SeamAllowanceService,
        protected GradingService $grading = new GradingService,
    ) {}

    /** گویش AAMA. */
    public function aama(Pattern $pattern, array $options = []): string
    {
        return $this->export($pattern, array_merge($options, ['flavour' => self::FLAVOUR_AAMA]));
    }

    /** گویش ASTM (D6673). */
    public function astm(Pattern $pattern, array $options = []): string
    {
        return $this->export($pattern, array_merge($options, ['flavour' => self::FLAVOUR_ASTM]));
    }

    /**
     * تولید فایل.
     *
     * گزینه‌ها:
     *   flavour  aama|astm
     *   sizes    آرایه سایزها؛ برای هر سایز یک BLOCK جداگانه ساخته می‌شود
     */
    public function export(Pattern $pattern, array $options = []): string
    {
        $flavour = ($options['flavour'] ?? self::FLAVOUR_AAMA) === self::FLAVOUR_ASTM
            ? self::FLAVOUR_ASTM
            : self::FLAVOUR_AAMA;

        $sets = $this->pieceSets($pattern, $options['sizes'] ?? []);

        $blocks = '';
        $entities = '';
        $offsetX = 0.0;
        $extents = ['min_x' => 0.0, 'min_y' => 0.0, 'max_x' => 0.0, 'max_y' => 0.0];

        foreach ($sets as $set) {
            $size = $set['size'];

            foreach ($set['pieces'] as $piece) {
                $name = $this->blockName($piece, $size, $sets);
                [$minX, $minY, $maxX, $maxY] = $this->box($piece);

                $blocks .= $this->block($name, $piece, $pattern, $size, $flavour, $minX, $minY);
                $entities .= $this->insert($name, $piece, $pattern, $size, $flavour, $offsetX, 0.0);

                $width = ($maxX - $minX) * 10;
                $height = ($maxY - $minY) * 10;

                $extents['max_x'] = max($extents['max_x'], $offsetX + $width);
                $extents['min_y'] = min($extents['min_y'], -$height - (self::TEXT_HEIGHT * 6));
                $offsetX += $width + self::GAP;
            }
        }

        $layers = self::GEOMETRY_LAYERS;

        if ($flavour === self::FLAVOUR_ASTM) {
            $layers = array_merge($layers, array_values(self::ASTM_LAYERS));
        }

        return $this->comments($pattern, $flavour, $sets)
            .$this->header($flavour, $extents)
            .$this->tables($layers)
            .$this->section('BLOCKS', $blocks)
            .$this->section('ENTITIES', $entities)
            .$this->pair(0, 'EOF');
    }

    /**
     * قطعه‌های هر سایز.
     *
     * @return array<int, array{size: string, pieces: array<int, PatternPiece>}>
     */
    protected function pieceSets(Pattern $pattern, array $sizes): array
    {
        $base = ['size' => (string) $pattern->base_size, 'pieces' => $pattern->pieces->all()];

        $sizes = array_values(array_filter(
            array_map('strval', $sizes),
            fn (string $size) => $size !== '' && $size !== (string) $pattern->base_size,
        ));

        if ($sizes === []) {
            return [$base];
        }

        $sets = [$base];

        foreach ($this->grading->grade($pattern, $sizes) as $size => $pieces) {
            $models = [];

            foreach ($pieces as $attributes) {
                $model = new PatternPiece;
                $model->forceFill($attributes);
                $models[] = $model;
            }

            $sets[] = ['size' => (string) $size, 'pieces' => $models];
        }

        return $sets;
    }

    // -------------------------------------------------------------- BLOCK

    /** یک BLOCK کامل برای یک قطعه. */
    protected function block(
        string $name,
        PatternPiece $piece,
        Pattern $pattern,
        string $size,
        string $flavour,
        float $minX,
        float $minY,
    ): string {
        // هندسه نسبت به گوشه بالا-چپ قطعه نوشته می‌شود؛ جابه‌جایی کار INSERT است
        $dx = -$minX;
        $dy = -$minY;

        $body = $this->pair(0, 'BLOCK')
            .$this->pair(8, self::LAYER_BOUNDARY)
            .$this->pair(2, $name)
            .$this->pair(70, 0)
            .$this->pair(10, 0).$this->pair(20, 0).$this->pair(30, 0)
            .$this->pair(3, $name)
            .$this->pair(1, '');

        $body .= $this->boundary($piece, $dx, $dy);
        $body .= $this->sewLine($piece, $dx, $dy);
        $body .= $this->boundaryPoints($piece, $dx, $dy);
        $body .= $this->gradeReference($dx, $dy, $piece);
        $body .= $this->notches($piece, $dx, $dy, $flavour);
        $body .= $this->internalLines($piece, $dx, $dy);
        $body .= $this->drills($piece, $dx, $dy);
        $body .= $this->grain($piece, $dx, $dy);
        $body .= $this->mirror($piece, $dx, $dy);
        $body .= $this->referenceLines($piece, $dx, $dy);
        $body .= $this->annotation($piece, $pattern, $size, $flavour, $dx, $dy);

        return $body.$this->pair(0, 'ENDBLK').$this->pair(8, self::LAYER_BOUNDARY);
    }

    /** لایه ۱: خط برش قطعه. */
    protected function boundary(PatternPiece $piece, float $dx, float $dy): string
    {
        $points = $this->seams->cuttingLine($piece);

        if (count($points) < 3) {
            $points = Geometry::flatten($piece->outline ?? []);
        }

        return $this->polyline($this->toDxf($points, $dx, $dy), self::LAYER_BOUNDARY);
    }

    /** لایه ۱۴: خط دوخت. */
    protected function sewLine(PatternPiece $piece, float $dx, float $dy): string
    {
        return $this->polyline(
            $this->toDxf(Geometry::flatten($piece->outline ?? []), $dx, $dy),
            self::LAYER_SEW_LINE,
        );
    }

    /**
     * لایه ۲ و ۳: نقاط گوشه و نقاط منحنی روی خط دوخت.
     *
     * نقطه‌ای که در outline با curve=true آمده «نقطه منحنی» است و بقیه «گوشه».
     */
    protected function boundaryPoints(PatternPiece $piece, float $dx, float $dy): string
    {
        $body = '';

        foreach (array_values($piece->outline ?? []) as $point) {
            $layer = Geometry::isCurve($point) ? self::LAYER_CURVE_POINTS : self::LAYER_TURN_POINTS;
            $body .= $this->point($this->toDxfPoint($point, $dx, $dy), $layer);
        }

        return $body;
    }

    /** لایه ۵: نقطه مرجع سایزبندی. */
    protected function gradeReference(float $dx, float $dy, PatternPiece $piece): string
    {
        [$minX, $minY] = $this->box($piece);

        return $this->point(
            $this->toDxfPoint(['x' => $minX, 'y' => $minY], $dx, $dy),
            self::LAYER_GRADE_REFERENCE,
        );
    }

    /** لایه ۴: علامت‌های جفت‌شدن. */
    protected function notches(PatternPiece $piece, float $dx, float $dy, string $flavour): string
    {
        $notches = $piece->notches ?? [];

        if ($notches === []) {
            return '';
        }

        $centre = $this->centroid($piece);
        $body = '';

        foreach ($notches as $notch) {
            if (! isset($notch['x'], $notch['y'])) {
                continue;
            }

            $body .= $this->point($this->toDxfPoint($notch, $dx, $dy), self::LAYER_NOTCH);

            if ($flavour !== self::FLAVOUR_ASTM) {
                continue;
            }

            // ASTM: پاره‌خط شکاف، ۰٫۵ سانتی‌متر رو به درون قطعه
            $vx = $centre['x'] - (float) $notch['x'];
            $vy = $centre['y'] - (float) $notch['y'];
            $length = sqrt(($vx * $vx) + ($vy * $vy));

            if ($length < 1e-6) {
                continue;
            }

            $body .= $this->line(
                $this->toDxfPoint($notch, $dx, $dy),
                $this->toDxfPoint([
                    'x' => ((float) $notch['x']) + (($vx / $length) * 0.5),
                    'y' => ((float) $notch['y']) + (($vy / $length) * 0.5),
                ], $dx, $dy),
                self::LAYER_NOTCH,
            );
        }

        return $body;
    }

    /** لایه ۱۰: ساسون، پیلی و خطوط نشانه. */
    protected function internalLines(PatternPiece $piece, float $dx, float $dy): string
    {
        $body = '';

        foreach ($piece->darts ?? [] as $dart) {
            $legs = $dart['legs'] ?? [];

            if (count($legs) < 2 || ! isset($dart['apex']['x'])) {
                continue;
            }

            foreach ($legs as $leg) {
                $body .= $this->line(
                    $this->toDxfPoint($leg, $dx, $dy),
                    $this->toDxfPoint($dart['apex'], $dx, $dy),
                    self::LAYER_INTERNAL_LINE,
                );
            }

            if (isset($dart['apex_lower']['x'])) {
                foreach ($legs as $leg) {
                    $body .= $this->line(
                        $this->toDxfPoint($leg, $dx, $dy),
                        $this->toDxfPoint($dart['apex_lower'], $dx, $dy),
                        self::LAYER_INTERNAL_LINE,
                    );
                }
            }
        }

        foreach (array_merge($piece->pleats ?? [], $piece->markers ?? []) as $item) {
            if (! isset($item['from']['x'], $item['to']['x'])) {
                continue;
            }

            $body .= $this->line(
                $this->toDxfPoint($item['from'], $dx, $dy),
                $this->toDxfPoint($item['to'], $dx, $dy),
                self::LAYER_INTERNAL_LINE,
            );
        }

        return $body;
    }

    /** لایه ۱۲: سوراخ‌های نشانه. */
    protected function drills(PatternPiece $piece, float $dx, float $dy): string
    {
        $body = '';

        foreach ($piece->drills ?? [] as $drill) {
            if (! isset($drill['x'], $drill['y'])) {
                continue;
            }

            $body .= $this->point($this->toDxfPoint($drill, $dx, $dy), self::LAYER_DRILL);
        }

        return $body;
    }

    /** لایه ۷: راستای پارچه. */
    protected function grain(PatternPiece $piece, float $dx, float $dy): string
    {
        $grainline = $piece->grainline ?? null;

        if (! isset($grainline['from']['x'], $grainline['to']['x'])) {
            return '';
        }

        return $this->line(
            $this->toDxfPoint($grainline['from'], $dx, $dy),
            $this->toDxfPoint($grainline['to'], $dx, $dy),
            self::LAYER_GRAIN,
        );
    }

    /** لایه ۶: خط تا / قرینه. */
    protected function mirror(PatternPiece $piece, float $dx, float $dy): string
    {
        if (! $piece->on_fold) {
            return '';
        }

        $points = $piece->points();
        $count = count($points);

        if ($count < 2) {
            return '';
        }

        $body = '';

        foreach ($this->seams->foldEdges($piece) as $index) {
            $a = $points[$index % $count] ?? null;
            $b = $points[($index + 1) % $count] ?? null;

            if ($a === null || $b === null) {
                continue;
            }

            $body .= $this->line($this->toDxfPoint($a, $dx, $dy), $this->toDxfPoint($b, $dx, $dy), self::LAYER_MIRROR);
        }

        return $body;
    }

    /** لایه ۸ و ۹: خط‌های مرجع راه‌راه و چهارخانه، اگر الگو داده باشد. */
    protected function referenceLines(PatternPiece $piece, float $dx, float $dy): string
    {
        $body = '';

        foreach (['stripe' => self::LAYER_STRIPE, 'plaid' => self::LAYER_PLAID] as $key => $layer) {
            $reference = $piece->meta[$key] ?? null;

            if (! isset($reference['from']['x'], $reference['to']['x'])) {
                continue;
            }

            $body .= $this->line(
                $this->toDxfPoint($reference['from'], $dx, $dy),
                $this->toDxfPoint($reference['to'], $dx, $dy),
                $layer,
            );
        }

        return $body;
    }

    /**
     * لایه ۱۳ (و در ASTM لایه‌های ۸۰ تا ۸۵): شناسنامه قطعه.
     *
     * در گویش ASTM هر قلم افزون بر TEXT به شکل ATTDEF هم می‌آید تا در نرم‌افزار
     * مقصد یک «صفت» خوانا باشد.
     */
    protected function annotation(
        PatternPiece $piece,
        Pattern $pattern,
        string $size,
        string $flavour,
        float $dx,
        float $dy,
    ): string {
        [, , , $maxY] = $this->box($piece);

        $x = 0.0;
        $y = -(($maxY + $dy) * 10) - (self::TEXT_HEIGHT * 2);
        $body = '';
        $line = 0;

        foreach ($this->metadata($piece, $pattern, $size) as $tag => $value) {
            $offsetY = $y - ($line * self::TEXT_HEIGHT * 1.5);

            $layer = $flavour === self::FLAVOUR_ASTM
                ? (self::ASTM_LAYERS[$tag] ?? self::LAYER_ANNOTATION)
                : self::LAYER_ANNOTATION;

            $body .= $this->text($tag.': '.$value, $x, $offsetY, $layer);

            if ($flavour === self::FLAVOUR_ASTM) {
                $body .= $this->attdef($tag, $value, $x + 200, $offsetY, $layer);
            }

            $line++;
        }

        return $body;
    }

    /**
     * شناسنامه قطعه؛ کلید همان «تگ» صفت در ASTM است.
     *
     * @return array<string, string>
     */
    protected function metadata(PatternPiece $piece, Pattern $pattern, string $size): array
    {
        return [
            'PIECE_NAME' => $this->ascii($piece->code ?: 'PIECE'),
            'PIECE_CODE' => $this->ascii($piece->code ?: 'PIECE'),
            'SIZE' => $this->ascii($size),
            'CATEGORY' => $this->ascii((string) ($piece->layer ?: 'outer')),
            'QUANTITY' => (string) ((int) $piece->cut_quantity),
            'ANNOTATION' => $this->ascii(sprintf(
                'pattern %d rev %d / %s x %s cm / %s',
                (int) $pattern->id,
                (int) $pattern->version,
                (string) round($piece->width(), 1),
                (string) round($piece->height(), 1),
                $piece->on_fold ? 'cut on fold' : 'open cut',
            )),
        ];
    }

    // ------------------------------------------------------------- INSERT

    /** INSERT بلوک در فضای مدل، در گویش ASTM با صفت‌ها. */
    protected function insert(
        string $name,
        PatternPiece $piece,
        Pattern $pattern,
        string $size,
        string $flavour,
        float $x,
        float $y,
    ): string {
        $withAttributes = $flavour === self::FLAVOUR_ASTM;

        $body = $this->pair(0, 'INSERT')
            .$this->pair(8, self::LAYER_BOUNDARY);

        if ($withAttributes) {
            $body .= $this->pair(66, 1); // پس از این INSERT، ATTRIB می‌آید
        }

        $body .= $this->pair(2, $name)
            .$this->pair(10, $x).$this->pair(20, $y).$this->pair(30, 0)
            .$this->pair(41, 1).$this->pair(42, 1).$this->pair(43, 1)
            .$this->pair(50, 0);

        if (! $withAttributes) {
            return $body;
        }

        $line = 0;
        $baseY = $y - ($this->height($piece) * 10) - (self::TEXT_HEIGHT * 2);

        foreach ($this->metadata($piece, $pattern, $size) as $tag => $value) {
            $body .= $this->attrib(
                $tag,
                $value,
                $x + 200,
                $baseY - ($line * self::TEXT_HEIGHT * 1.5),
                self::ASTM_LAYERS[$tag] ?? self::LAYER_ANNOTATION,
            );
            $line++;
        }

        return $body.$this->pair(0, 'SEQEND').$this->pair(8, self::LAYER_BOUNDARY);
    }

    // -------------------------------------------------------- بخش‌های فایل

    /** یادداشت‌های ۹۹۹ در آغاز فایل: نام نرم‌افزار، الگو و گویش. */
    protected function comments(Pattern $pattern, string $flavour, array $sets): string
    {
        $sizes = implode(', ', array_map(fn (array $set) => $set['size'], $sets));

        $lines = [
            'Generated by Dokht pattern studio (dokht) - pattern CAD interchange export',
            'Convention: '.($flavour === self::FLAVOUR_ASTM
                ? 'ASTM D6673 numbered layers with piece attributes (ATTDEF/ATTRIB)'
                : 'AAMA / ASTM D6673 numbered layers, annotation as TEXT'),
            'Pattern: '.$this->asciiName($pattern).' (id '.(int) $pattern->id.')',
            'Pattern name (DXF unicode escapes): '.$this->unicodeEscape((string) $pattern->name),
            'Sizes: '.$this->ascii($sizes),
            'Units: millimetre, $INSUNITS = 4, CAD y axis points up',
            'Layers: 1 boundary/cut, 2 turn points, 3 curve points, 4 notch, 5 grade reference, '
                .'6 mirror, 7 grain, 8 stripe, 9 plaid, 10 internal lines, 11 internal cutout, '
                .'12 drill, 13 annotation, 14 sew line, 15 alternate',
        ];

        $body = '';

        foreach ($lines as $line) {
            $body .= $this->pair(999, $line);
        }

        return $body;
    }

    protected function header(string $flavour, array $extents): string
    {
        return $this->section('HEADER',
            $this->pair(9, '$ACADVER').$this->pair(1, 'AC1009')
            .$this->pair(9, '$INSUNITS').$this->pair(70, 4) // ۴ = میلی‌متر
            .$this->pair(9, '$MEASUREMENT').$this->pair(70, 1)
            .$this->pair(9, '$LUNITS').$this->pair(70, 2)
            .$this->pair(9, '$AUNITS').$this->pair(70, 0)
            .$this->pair(9, '$LIMCHECK').$this->pair(70, 0)
            .$this->pair(9, '$EXTMIN').$this->pair(10, $extents['min_x'] - 20)
                .$this->pair(20, $extents['min_y'] - 20).$this->pair(30, 0)
            .$this->pair(9, '$EXTMAX').$this->pair(10, $extents['max_x'] + 20)
                .$this->pair(20, $extents['max_y'] + 20).$this->pair(30, 0)
            .$this->pair(9, '$TEXTSIZE').$this->pair(40, self::TEXT_HEIGHT)
        );
    }

    /** جدول‌های LTYPE و LAYER. */
    protected function tables(array $layers): string
    {
        $lineTypes = $this->pair(0, 'TABLE').$this->pair(2, 'LTYPE').$this->pair(70, 1)
            .$this->pair(0, 'LTYPE').$this->pair(2, 'CONTINUOUS').$this->pair(70, 0)
            .$this->pair(3, 'Solid line').$this->pair(72, 65).$this->pair(73, 0).$this->pair(40, 0)
            .$this->pair(0, 'ENDTAB');

        $body = $this->pair(0, 'TABLE').$this->pair(2, 'LAYER').$this->pair(70, count($layers) + 1)
            .$this->layer('0', 7);

        $colour = 1;

        foreach ($layers as $layer) {
            $body .= $this->layer($layer, ($colour % 7) + 1);
            $colour++;
        }

        $body .= $this->pair(0, 'ENDTAB');

        return $this->section('TABLES', $lineTypes.$body);
    }

    // ------------------------------------------------------------- نهادها

    /**
     * چندضلعی بسته: POLYLINE + VERTEX + SEQEND (سازگار با R12).
     *
     * @param  array<int, array{x: float, y: float}>  $points
     */
    protected function polyline(array $points, string $layer): string
    {
        if (count($points) < 2) {
            return '';
        }

        $body = $this->pair(0, 'POLYLINE')
            .$this->pair(8, $layer)
            .$this->pair(66, 1)
            .$this->pair(70, 1) // بسته
            .$this->pair(10, 0).$this->pair(20, 0).$this->pair(30, 0);

        foreach ($points as $point) {
            $body .= $this->pair(0, 'VERTEX')
                .$this->pair(8, $layer)
                .$this->pair(10, $point['x'])
                .$this->pair(20, $point['y'])
                .$this->pair(30, 0);
        }

        return $body.$this->pair(0, 'SEQEND').$this->pair(8, $layer);
    }

    protected function line(array $from, array $to, string $layer): string
    {
        return $this->pair(0, 'LINE')
            .$this->pair(8, $layer)
            .$this->pair(10, $from['x']).$this->pair(20, $from['y']).$this->pair(30, 0)
            .$this->pair(11, $to['x']).$this->pair(21, $to['y']).$this->pair(31, 0);
    }

    protected function point(array $point, string $layer): string
    {
        return $this->pair(0, 'POINT')
            .$this->pair(8, $layer)
            .$this->pair(10, $point['x']).$this->pair(20, $point['y']).$this->pair(30, 0);
    }

    protected function text(string $value, float $x, float $y, string $layer): string
    {
        return $this->pair(0, 'TEXT')
            .$this->pair(8, $layer)
            .$this->pair(10, $x).$this->pair(20, $y).$this->pair(30, 0)
            .$this->pair(40, self::TEXT_HEIGHT)
            .$this->pair(1, $this->ascii($value));
    }

    /** تعریف صفت داخل BLOCK (فقط ASTM). */
    protected function attdef(string $tag, string $value, float $x, float $y, string $layer): string
    {
        return $this->pair(0, 'ATTDEF')
            .$this->pair(8, $layer)
            .$this->pair(10, $x).$this->pair(20, $y).$this->pair(30, 0)
            .$this->pair(40, self::TEXT_HEIGHT)
            .$this->pair(1, $this->ascii($value))
            .$this->pair(3, $tag)
            .$this->pair(2, $tag)
            .$this->pair(70, 0);
    }

    /** مقدار صفت پس از INSERT (فقط ASTM). */
    protected function attrib(string $tag, string $value, float $x, float $y, string $layer): string
    {
        return $this->pair(0, 'ATTRIB')
            .$this->pair(8, $layer)
            .$this->pair(10, $x).$this->pair(20, $y).$this->pair(30, 0)
            .$this->pair(40, self::TEXT_HEIGHT)
            .$this->pair(1, $this->ascii($value))
            .$this->pair(2, $tag)
            .$this->pair(70, 0);
    }

    protected function layer(string $name, int $colour): string
    {
        return $this->pair(0, 'LAYER')
            .$this->pair(2, $name)
            .$this->pair(70, 0)
            .$this->pair(62, $colour)
            .$this->pair(6, 'CONTINUOUS');
    }

    protected function section(string $name, string $body): string
    {
        return $this->pair(0, 'SECTION').$this->pair(2, $name).$body.$this->pair(0, 'ENDSEC');
    }

    // ------------------------------------------------------------- ابزارها

    /** نام BLOCK: حروف بزرگ لاتین، و اگر چند سایز داریم پسوند سایز. */
    protected function blockName(PatternPiece $piece, string $size, array $sets): string
    {
        $name = strtoupper((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', $piece->code ?: 'PIECE'));
        $name = trim($name, '_-');

        if ($name === '') {
            $name = 'PIECE';
        }

        if (count($sets) > 1) {
            $name .= '-'.strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $size));
        }

        return substr($name, 0, 31);
    }

    /** کادر قطعه با در نظر گرفتن خط برش. */
    protected function box(PatternPiece $piece): array
    {
        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($piece->outline ?? []);

        foreach ($this->seams->cuttingLine($piece) as $point) {
            $minX = min($minX, $point['x']);
            $minY = min($minY, $point['y']);
            $maxX = max($maxX, $point['x']);
            $maxY = max($maxY, $point['y']);
        }

        return [$minX, $minY, $maxX, $maxY];
    }

    protected function height(PatternPiece $piece): float
    {
        [, $minY, , $maxY] = $this->box($piece);

        return $maxY - $minY;
    }

    protected function centroid(PatternPiece $piece): array
    {
        $points = Geometry::flatten($piece->outline ?? []);

        if ($points === []) {
            return ['x' => 0.0, 'y' => 0.0];
        }

        return [
            'x' => array_sum(array_column($points, 'x')) / count($points),
            'y' => array_sum(array_column($points, 'y')) / count($points),
        ];
    }

    /**
     * نقطه الگو (سانتی‌متر، y به پایین) ⇒ نقطه DXF (میلی‌متر، y به بالا).
     *
     * @return array{x: float, y: float}
     */
    protected function toDxfPoint(array $point, float $dx, float $dy): array
    {
        return [
            'x' => round((((float) ($point['x'] ?? 0)) + $dx) * 10, 3),
            'y' => round(-((((float) ($point['y'] ?? 0)) + $dy) * 10), 3),
        ];
    }

    /** @return array<int, array{x: float, y: float}> */
    protected function toDxf(array $points, float $dx, float $dy): array
    {
        return array_map(fn (array $point) => $this->toDxfPoint($point, $dx, $dy), $points);
    }

    /** نام لاتین الگو برای یادداشت سرآیند؛ نام فارسی جدا در یادداشت UTF-8 می‌آید. */
    protected function asciiName(Pattern $pattern): string
    {
        $name = $this->ascii((string) $pattern->name);

        return $name === '-' ? 'pattern-'.(int) $pattern->id : $name;
    }

    /**
     * نام فارسی به شکل گریز یونیکد استاندارد DXF: «\U+06AF».
     *
     * کل فایل این‌گونه اَسکی محض می‌ماند (چیزی که DXF نسخه R12 می‌خواهد) و
     * نام فارسی هم بدون از دست رفتن در فایل هست. اگر بایت‌های خام UTF-8 را
     * می‌نوشتیم، بایت 0x85 که در «م» هست، در برخی خواننده‌ها «سر خط» شمرده
     * می‌شود و ساختار زوجی فایل را می‌شکند.
     */
    protected function unicodeEscape(string $value): string
    {
        $shaper = new ArabicShaper;
        $out = '';

        foreach ($shaper->codepoints($value) as $codepoint) {
            $out .= $codepoint >= 0x20 && $codepoint <= 0x7E
                ? chr($codepoint)
                : sprintf('\\U+%04X', $codepoint);
        }

        return $out;
    }

    /** متن DXF نسخه R12 فقط اَسکی است. */
    protected function ascii(string $value): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\x20-\x7E]/', '', $value)));

        return $clean === '' ? '-' : $clean;
    }

    /** هر مقدار DXF دو خط است: کد گروه و مقدار. */
    protected function pair(int $code, float|int|string $value): string
    {
        if (is_float($value) || is_int($value)) {
            $value = is_int($value)
                ? (string) $value
                : rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');

            if ($value === '' || $value === '-') {
                $value = '0';
            }
        }

        return $code."\n".$value."\n";
    }
}
