<?php

namespace App\Services\Export;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\SeamAllowanceService;
use App\Support\Jalali;

/**
 * خروجی PDF برداری الگو، آماده چاپ در اندازه واقعی.
 *
 * ساختار سند:
 *   - صفحه نخست: نمای کلی همه قطعه‌ها، کوچک‌شده تا در یک برگ جا شود، با نام و
 *     شمار برش هر قطعه و فهرست صفحه‌های هر قطعه.
 *   - صفحه‌های بعدی: هر قطعه به «کاشی»های A4 بریده می‌شود. هر کاشی پنجره‌ای
 *     ۱۹ × ۲۳٫۷ سانتی‌متری از الگوست و کاشی‌های همسایه یک سانتی‌متر هم‌پوشانی
 *     دارند تا چسباندن برگ‌ها آسان باشد.
 *
 * مقیاس دقیقاً یک‌به‌یک است: هیچ ماتریس مقیاسی روی جریان محتوا اعمال نمی‌شود و
 * هر سانتی‌متر الگو به ۷۲÷۲٫۵۴ واحد کاربر PDF تبدیل می‌شود. برای کنترل، روی هر
 * صفحه یک خط‌کش ۱۰ سانتی‌متری چاپ می‌شود؛ اگر با خط‌کش واقعی یکی نبود، یعنی
 * چاپگر سند را کوچک/بزرگ کرده و باید روی «اندازه واقعی / ۱۰۰٪» تنظیم شود.
 *
 * متن فارسی با جاسازی قلم Vazirmatn به شکل CIDFontType2 با کدگذاری Identity-H
 * نوشته می‌شود و پیش از نوشتن با ArabicShaper شکل‌دهی و راست‌به‌چپ می‌شود. اگر
 * فایل قلم در دسترس نباشد، به جای نام فارسی «کد لاتین قطعه + سایز + تعداد»
 * چاپ می‌شود؛ برچسب فارسیِ بدچسب از کد لاتین بدتر است.
 */
class PatternPdfExporter
{
    /** حاشیه کاغذ (سانتی‌متر). */
    public const MARGIN = 1.0;

    /** نوار بالای صفحه برای عنوان و شماره کاشی. */
    public const HEADER = 2.0;

    /** نوار پایین صفحه برای خط‌کش و شماره صفحه. */
    public const FOOTER = 2.4;

    /** هم‌پوشانی کاشی‌های همسایه. */
    public const OVERLAP = 1.0;

    /** پنجره قابل‌استفاده هر کاشی (سانتی‌متر) — A4 منهای حاشیه‌ها و دو نوار. */
    public const WINDOW_WIDTH = 21.0 - (2 * self::MARGIN);

    public const WINDOW_HEIGHT = 29.7 - (2 * self::MARGIN) - self::HEADER - self::FOOTER;

    public const PAGE_WIDTH_CM = 21.0;

    public const PAGE_HEIGHT_CM = 29.7;

    /** رنگ‌ها (قرمز، سبز، آبی در بازه ۰ تا ۱). */
    protected const INK = [0.267, 0.251, 0.235];

    protected const CUT = [0.494, 0.361, 0.855];

    protected const PAPER = [0.988, 0.984, 0.976];

    protected const MUTED = [0.659, 0.635, 0.620];

    protected const DART = [0.486, 0.302, 0.859];

    protected const NOTCH = [0.831, 0.341, 0.243];

    protected const GRAIN = [0.341, 0.325, 0.306];

    protected const MARK = [0.60, 0.58, 0.57];

    public function __construct(protected SeamAllowanceService $seams = new SeamAllowanceService) {}

    /**
     * تولید فایل PDF.
     *
     * گزینه‌ها:
     *   seam_allowance (پیش‌فرض true) — خط برش هم کشیده شود
     *   overview       (پیش‌فرض true) — صفحه نمای کلی
     *   compress       (پیش‌فرض true) — فشرده‌سازی جریان محتوا
     */
    public function export(Pattern $pattern, array $options = []): string
    {
        $withSeam = $options['seam_allowance'] ?? true;
        $withOverview = $options['overview'] ?? true;

        $pdf = new PdfWriter(PdfWriter::A4_WIDTH, PdfWriter::A4_HEIGHT, $options['compress'] ?? true);
        $pdf->useFont(TrueTypeFont::persian());
        $pdf->setInfo([
            'Title' => (string) $pattern->name,
            'Subject' => 'الگوی برش در اندازه واقعی',
            'Author' => 'دوخت',
        ]);

        $pieces = $pattern->pieces->all();
        $plan = $this->plan($pieces, $withSeam, $withOverview);
        $total = max(1, count($plan));

        foreach ($plan as $index => $page) {
            $pdf->addPage();

            if ($page['type'] === 'overview') {
                $this->drawOverview($pdf, $pattern, $pieces, $plan, $withSeam);
            } else {
                $this->drawTile($pdf, $pattern, $page, $withSeam);
            }

            $this->drawFurniture($pdf, $pattern, $page, $index + 1, $total);
        }

        if ($plan === []) {
            $pdf->addPage();
            $this->drawFurniture($pdf, $pattern, ['type' => 'empty'], 1, 1);
            $pdf->text('این الگو هنوز قطعه‌ای ندارد.', PdfWriter::cm(self::PAGE_WIDTH_CM / 2),
                PdfWriter::cm(self::PAGE_HEIGHT_CM / 2), 12, 'center');
        }

        return $pdf->render();
    }

    /**
     * نقشه صفحه‌ها؛ پیش از کشیدن ساخته می‌شود تا «صفحه n از m» درست باشد.
     *
     * @param  array<int, PatternPiece>  $pieces
     * @return array<int, array<string, mixed>>
     */
    public function plan(array $pieces, bool $withSeam = true, bool $withOverview = true): array
    {
        $plan = [];

        if ($pieces === []) {
            return $plan;
        }

        if ($withOverview) {
            $plan[] = ['type' => 'overview'];
        }

        $stepX = self::WINDOW_WIDTH - self::OVERLAP;
        $stepY = self::WINDOW_HEIGHT - self::OVERLAP;

        foreach ($pieces as $piece) {
            [$minX, $minY, $maxX, $maxY] = $this->pieceBox($piece, $withSeam);

            $columns = max(1, (int) ceil(($maxX - $minX) / $stepX));
            $rows = max(1, (int) ceil(($maxY - $minY) / $stepY));

            for ($row = 0; $row < $rows; $row++) {
                for ($column = 0; $column < $columns; $column++) {
                    $plan[] = [
                        'type' => 'tile',
                        'piece' => $piece,
                        'x' => $minX + ($column * $stepX),
                        'y' => $minY + ($row * $stepY),
                        'column' => $column + 1,
                        'columns' => $columns,
                        'row' => $row + 1,
                        'rows' => $rows,
                    ];
                }
            }
        }

        return $plan;
    }

    /** کادر قطعه با کمی فاصله آزاد و در صورت نیاز خط برش. */
    protected function pieceBox(PatternPiece $piece, bool $withSeam): array
    {
        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($piece->outline ?? []);

        if ($withSeam) {
            foreach ($this->seams->cuttingLine($piece) as $point) {
                $minX = min($minX, $point['x']);
                $minY = min($minY, $point['y']);
                $maxX = max($maxX, $point['x']);
                $maxY = max($maxY, $point['y']);
            }
        }

        return [$minX - 0.6, $minY - 0.6, $maxX + 0.6, $maxY + 0.6];
    }

    // ---------------------------------------------------------------- کاشی‌ها

    /** یک کاشی از قطعه در اندازه واقعی. */
    protected function drawTile(PdfWriter $pdf, Pattern $pattern, array $page, bool $withSeam): void
    {
        /** @var PatternPiece $piece */
        $piece = $page['piece'];

        $left = PdfWriter::cm(self::MARGIN);
        $top = PdfWriter::cm(self::PAGE_HEIGHT_CM - self::MARGIN - self::HEADER);
        $width = PdfWriter::cm(self::WINDOW_WIDTH);
        $height = PdfWriter::cm(self::WINDOW_HEIGHT);

        $x0 = (float) $page['x'];
        $y0 = (float) $page['y'];

        $px = fn (float|int|string|null $value) => $left + PdfWriter::cm(((float) $value) - $x0);
        $py = fn (float|int|string|null $value) => $top - PdfWriter::cm(((float) $value) - $y0);

        $pdf->save();
        $pdf->rect($left, $top - $height, $width, $height)->clip();
        $this->drawPiece($pdf, $piece, $px, $py, $withSeam, 1.0, true);
        $pdf->restore();

        $this->drawRegistration($pdf, $left, $top - $height, $width, $height);

        // عنوان کاشی
        $size = (string) $pattern->base_size;
        $title = $pdf->hasEmbeddedFont()
            ? $piece->name.' • سایز '.Jalali::digits($size).' • تعداد برش '.Jalali::digits((string) $piece->cut_quantity)
            : $piece->code.' / size '.$size.' / cut x'.$piece->cut_quantity;

        $pdf->setFillColor(...self::INK);
        $pdf->text($title, PdfWriter::cm(self::PAGE_WIDTH_CM - self::MARGIN),
            PdfWriter::cm(self::PAGE_HEIGHT_CM - self::MARGIN - 0.55), 11, 'right');

        $tile = $pdf->hasEmbeddedFont()
            ? 'ستون '.Jalali::digits((string) $page['column']).' از '.Jalali::digits((string) $page['columns'])
                .' • سطر '.Jalali::digits((string) $page['row']).' از '.Jalali::digits((string) $page['rows'])
            : 'col '.$page['column'].'/'.$page['columns'].'  row '.$page['row'].'/'.$page['rows'];

        $pdf->setFillColor(...self::MUTED);
        $pdf->text($tile, PdfWriter::cm(self::PAGE_WIDTH_CM - self::MARGIN),
            PdfWriter::cm(self::PAGE_HEIGHT_CM - self::MARGIN - 1.2), 8, 'right');
    }

    /** علامت‌های گوشه و مرز چسباندن. */
    protected function drawRegistration(PdfWriter $pdf, float $x, float $y, float $width, float $height): void
    {
        $pdf->save();
        $pdf->setStrokeColor(...self::MARK);
        $pdf->setLineWidth(0.4);
        $pdf->setDash([3, 3]);
        $pdf->rect($x, $y, $width, $height)->stroke();
        $pdf->setDash();

        $arm = PdfWriter::cm(0.6);
        $pdf->setLineWidth(0.7);
        $pdf->setStrokeColor(0.35, 0.33, 0.32);

        foreach ([[$x, $y], [$x + $width, $y], [$x, $y + $height], [$x + $width, $y + $height]] as [$cx, $cy]) {
            $pdf->line($cx - $arm, $cy, $cx + $arm, $cy);
            $pdf->line($cx, $cy - $arm, $cx, $cy + $arm);
        }

        // مثلث کوچک گوشه بالا-چپ: جهت برگ را مشخص می‌کند
        $tick = PdfWriter::cm(0.35);
        $pdf->setFillColor(0.35, 0.33, 0.32);
        $pdf->polygon([
            [$x, $y + $height],
            [$x + $tick, $y + $height],
            [$x, $y + $height - $tick],
        ], true, 'f');

        $pdf->restore();
    }

    // ------------------------------------------------------------- نمای کلی

    /**
     * صفحه نخست: همه قطعه‌ها با هم، کوچک‌شده تا در پنجره جا شوند.
     *
     * @param  array<int, PatternPiece>  $pieces
     */
    protected function drawOverview(PdfWriter $pdf, Pattern $pattern, array $pieces, array $plan, bool $withSeam): void
    {
        $left = PdfWriter::cm(self::MARGIN);
        $top = PdfWriter::cm(self::PAGE_HEIGHT_CM - self::MARGIN - self::HEADER);

        $heading = $pdf->hasEmbeddedFont()
            ? $pattern->name.' — نمای کلی قطعه‌ها'
            : 'pattern #'.$pattern->id.' overview';

        $pdf->setFillColor(...self::INK);
        $pdf->text($heading, PdfWriter::cm(self::PAGE_WIDTH_CM - self::MARGIN),
            PdfWriter::cm(self::PAGE_HEIGHT_CM - self::MARGIN - 0.55), 12, 'right');

        $note = $pdf->hasEmbeddedFont()
            ? 'این صفحه کوچک‌شده است؛ صفحه‌های بعدی در اندازه واقعی چاپ می‌شوند.'
            : 'This page is scaled down. The following pages print at 1:1.';

        $pdf->setFillColor(...self::MUTED);
        $pdf->text($note, PdfWriter::cm(self::PAGE_WIDTH_CM - self::MARGIN),
            PdfWriter::cm(self::PAGE_HEIGHT_CM - self::MARGIN - 1.2), 8, 'right');

        $layout = $this->overviewLayout($pieces, $withSeam);

        if ($layout === null) {
            return;
        }

        $scale = $layout['scale'];
        $offsetX = (PdfWriter::cm(self::WINDOW_WIDTH) - PdfWriter::cm($layout['width'] * $scale)) / 2;

        foreach ($layout['boxes'] as $box) {
            /** @var PatternPiece $piece */
            $piece = $box['piece'];

            $px = fn (float|int|string|null $value) => $left + $offsetX
                + PdfWriter::cm((((float) $value) - $box['min_x'] + $box['x']) * $scale);
            $py = fn (float|int|string|null $value) => $top
                - PdfWriter::cm((((float) $value) - $box['min_y'] + $box['y']) * $scale);

            $this->drawPiece($pdf, $piece, $px, $py, $withSeam, $scale, false);

            $label = $pdf->hasEmbeddedFont()
                ? $piece->name.' ×'.Jalali::digits((string) $piece->cut_quantity)
                : $piece->code.' x'.$piece->cut_quantity;

            $pdf->setFillColor(...self::INK);
            $pdf->text(
                $label,
                $px($box['min_x'] + (($box['max_x'] - $box['min_x']) / 2)),
                $py($box['max_y']) - 9,
                7.5,
                'center',
            );

            $pages = $this->pagesOf($plan, $piece);

            if ($pages !== '') {
                $pdf->setFillColor(...self::MUTED);
                $pdf->text(
                    $pdf->hasEmbeddedFont() ? 'برگ '.$pages : 'pages '.$pages,
                    $px($box['min_x'] + (($box['max_x'] - $box['min_x']) / 2)),
                    $py($box['max_y']) - 17,
                    6.5,
                    'center',
                );
            }
        }
    }

    /** شماره برگ‌های مربوط به یک قطعه، برای فهرست نمای کلی. */
    protected function pagesOf(array $plan, PatternPiece $piece): string
    {
        $numbers = [];

        foreach ($plan as $index => $page) {
            if (($page['type'] ?? '') === 'tile' && $page['piece'] === $piece) {
                $numbers[] = $index + 1;
            }
        }

        if ($numbers === []) {
            return '';
        }

        $first = (string) min($numbers);
        $last = (string) max($numbers);
        $text = $first === $last ? $first : $first.'–'.$last;

        return Jalali::digits($text);
    }

    /**
     * چیدن قطعه‌ها در یک شبکه و یافتن بهترین شمار ستون برای بیشترین مقیاس.
     *
     * @param  array<int, PatternPiece>  $pieces
     */
    protected function overviewLayout(array $pieces, bool $withSeam): ?array
    {
        $boxes = [];

        foreach ($pieces as $piece) {
            [$minX, $minY, $maxX, $maxY] = $this->pieceBox($piece, $withSeam);

            $boxes[] = [
                'piece' => $piece,
                'min_x' => $minX,
                'min_y' => $minY,
                'max_x' => $maxX,
                'max_y' => $maxY,
                'width' => max(1.0, $maxX - $minX),
                'height' => max(1.0, $maxY - $minY),
            ];
        }

        if ($boxes === []) {
            return null;
        }

        $gap = 2.0;
        $labelSpace = 2.4;
        $best = null;

        for ($columns = 1; $columns <= count($boxes); $columns++) {
            $placed = [];
            $x = 0.0;
            $y = 0.0;
            $rowHeight = 0.0;
            $totalWidth = 0.0;

            foreach ($boxes as $index => $box) {
                if ($index > 0 && $index % $columns === 0) {
                    $x = 0.0;
                    $y += $rowHeight + $gap + $labelSpace;
                    $rowHeight = 0.0;
                }

                $placed[] = $box + ['x' => $x, 'y' => $y];
                $x += $box['width'] + $gap;
                $rowHeight = max($rowHeight, $box['height']);
                $totalWidth = max($totalWidth, $x - $gap);
            }

            $totalHeight = $y + $rowHeight + $labelSpace;
            $scale = min(
                self::WINDOW_WIDTH / max(0.1, $totalWidth),
                self::WINDOW_HEIGHT / max(0.1, $totalHeight),
                1.0,
            );

            if ($best === null || $scale > $best['scale']) {
                $best = [
                    'scale' => $scale,
                    'boxes' => $placed,
                    'width' => $totalWidth,
                    'height' => $totalHeight,
                ];
            }
        }

        return $best;
    }

    // ------------------------------------------------------------ یک قطعه

    /**
     * کشیدن هندسه یک قطعه.
     *
     * @param  callable(float|int|string|null): float  $px
     * @param  callable(float|int|string|null): float  $py
     */
    protected function drawPiece(
        PdfWriter $pdf,
        PatternPiece $piece,
        callable $px,
        callable $py,
        bool $withSeam,
        float $scale,
        bool $detail,
    ): void {
        $pdf->save();
        $pdf->setLineJoin(1);
        $pdf->setLineCap(1);

        // خط برش (خط‌چین)
        if ($withSeam) {
            $cutting = $this->seams->cuttingLine($piece);

            if (count($cutting) > 2) {
                $pdf->setStrokeColor(...self::CUT);
                $pdf->setLineWidth(0.7);
                $pdf->setDash([4, 2.5]);
                $pdf->polygon(array_map(fn (array $p) => [$px($p['x']), $py($p['y'])], $cutting), true, 'S');
                $pdf->setDash();
            }
        }

        // خط دوخت (پیوسته، با منحنی‌ها)
        $pdf->setFillColor(...self::PAPER);
        $pdf->setStrokeColor(...self::INK);
        $pdf->setLineWidth(1.0);

        if ($this->outlinePath($pdf, $piece->outline ?? [], $px, $py)) {
            $pdf->fillAndStroke();
        }

        // خطوط نشانه (سینه، کمر، باسن، مرکز جلو و پشت)
        $pdf->setStrokeColor(...self::MUTED);
        $pdf->setLineWidth(0.5);
        $pdf->setDash([3, 2]);

        foreach ($piece->markers ?? [] as $marker) {
            if (! isset($marker['from']['x'], $marker['to']['x'])) {
                continue;
            }

            $pdf->line($px($marker['from']['x']), $py($marker['from']['y']), $px($marker['to']['x']), $py($marker['to']['y']));
        }

        // پیلی‌ها
        $pdf->setStrokeColor(0.055, 0.647, 0.914);

        foreach ($piece->pleats ?? [] as $pleat) {
            if (! isset($pleat['from']['x'], $pleat['to']['x'])) {
                continue;
            }

            $pdf->line($px($pleat['from']['x']), $py($pleat['from']['y']), $px($pleat['to']['x']), $py($pleat['to']['y']));
        }

        $pdf->setDash();

        // ساسون‌ها
        $pdf->setStrokeColor(...self::DART);
        $pdf->setLineWidth(0.7);

        foreach ($piece->darts ?? [] as $dart) {
            $legs = $dart['legs'] ?? [];

            if (count($legs) < 2 || ! isset($dart['apex']['x'])) {
                continue;
            }

            foreach ($legs as $leg) {
                $pdf->line($px($leg['x']), $py($leg['y']), $px($dart['apex']['x']), $py($dart['apex']['y']));
            }

            if (isset($dart['apex_lower']['x'])) {
                foreach ($legs as $leg) {
                    $pdf->line($px($leg['x']), $py($leg['y']), $px($dart['apex_lower']['x']), $py($dart['apex_lower']['y']));
                }
            }

            $pdf->setFillColor(...self::DART);
            $pdf->circle($px($dart['apex']['x']), $py($dart['apex']['y']), max(0.8, PdfWriter::cm(0.12 * $scale)), 'f');
        }

        $this->drawNotches($pdf, $piece, $px, $py, $scale);
        $this->drawDrills($pdf, $piece, $px, $py, $scale);
        $this->drawGrainline($pdf, $piece, $px, $py, $scale);
        $this->drawFold($pdf, $piece, $px, $py, $detail);

        $pdf->restore();
    }

    /**
     * مسیر خط دوخت با منحنی‌های درجه‌دو.
     *
     * @return bool آیا مسیری ساخته شد
     */
    protected function outlinePath(PdfWriter $pdf, array $outline, callable $px, callable $py): bool
    {
        $outline = array_values($outline);
        $count = count($outline);

        if ($count < 2) {
            return false;
        }

        $first = $outline[0];
        $pdf->moveTo($px($first['x'] ?? 0), $py($first['y'] ?? 0));
        $current = ['x' => (float) ($first['x'] ?? 0), 'y' => (float) ($first['y'] ?? 0)];

        for ($i = 1; $i < $count; $i++) {
            $point = $outline[$i];

            if (Geometry::isCurve($point)) {
                $pdf->quadraticTo(
                    $px($current['x']), $py($current['y']),
                    $px($point['cx']), $py($point['cy']),
                    $px($point['x']), $py($point['y']),
                );
            } else {
                $pdf->lineTo($px($point['x']), $py($point['y']));
            }

            $current = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
        }

        if (Geometry::isCurve($first)) {
            $pdf->quadraticTo(
                $px($current['x']), $py($current['y']),
                $px($first['cx']), $py($first['cy']),
                $px($first['x']), $py($first['y']),
            );
        }

        $pdf->closePath();

        return true;
    }

    /** علامت‌های جفت‌شدن به شکل هفتِ کوچک رو به داخل قطعه. */
    protected function drawNotches(PdfWriter $pdf, PatternPiece $piece, callable $px, callable $py, float $scale): void
    {
        $notches = $piece->notches ?? [];

        if ($notches === []) {
            return;
        }

        $centre = $this->centroid($piece);
        $depth = 0.45;
        $half = 0.16;

        $pdf->setStrokeColor(...self::NOTCH);
        $pdf->setLineWidth(0.8);

        foreach ($notches as $notch) {
            if (! isset($notch['x'], $notch['y'])) {
                continue;
            }

            $dx = $centre['x'] - (float) $notch['x'];
            $dy = $centre['y'] - (float) $notch['y'];
            $length = sqrt(($dx * $dx) + ($dy * $dy));

            if ($length < 1e-6) {
                continue;
            }

            $ux = $dx / $length;
            $uy = $dy / $length;

            $tipX = ((float) $notch['x']) + ($ux * $depth);
            $tipY = ((float) $notch['y']) + ($uy * $depth);
            $aX = ((float) $notch['x']) + (-$uy * $half);
            $aY = ((float) $notch['y']) + ($ux * $half);
            $bX = ((float) $notch['x']) - (-$uy * $half);
            $bY = ((float) $notch['y']) - ($ux * $half);

            $pdf->moveTo($px($aX), $py($aY));
            $pdf->lineTo($px($tipX), $py($tipY));
            $pdf->lineTo($px($bX), $py($bY));
            $pdf->stroke();
        }
    }

    /** سوراخ‌های نشانه: دایره کوچک با علامت به‌علاوه. */
    protected function drawDrills(PdfWriter $pdf, PatternPiece $piece, callable $px, callable $py, float $scale): void
    {
        $pdf->setStrokeColor(...self::GRAIN);
        $pdf->setLineWidth(0.6);

        foreach ($piece->drills ?? [] as $drill) {
            if (! isset($drill['x'], $drill['y'])) {
                continue;
            }

            $radius = max(1.0, PdfWriter::cm(0.18 * $scale));
            $pdf->circle($px($drill['x']), $py($drill['y']), $radius, 'S');
            $pdf->line($px($drill['x']) - $radius, $py($drill['y']), $px($drill['x']) + $radius, $py($drill['y']));
            $pdf->line($px($drill['x']), $py($drill['y']) - $radius, $px($drill['x']), $py($drill['y']) + $radius);
        }
    }

    /** پیکان راستای پارچه با دو سرِ پُر. */
    protected function drawGrainline(PdfWriter $pdf, PatternPiece $piece, callable $px, callable $py, float $scale): void
    {
        $grainline = $piece->grainline ?? null;

        if (! isset($grainline['from']['x'], $grainline['to']['x'])) {
            return;
        }

        $from = [(float) $grainline['from']['x'], (float) $grainline['from']['y']];
        $to = [(float) $grainline['to']['x'], (float) $grainline['to']['y']];

        $pdf->setStrokeColor(...self::GRAIN);
        $pdf->setFillColor(...self::GRAIN);
        $pdf->setLineWidth(0.8);
        $pdf->line($px($from[0]), $py($from[1]), $px($to[0]), $py($to[1]));

        $angle = atan2($py($to[1]) - $py($from[1]), $px($to[0]) - $px($from[0]));
        $head = max(4.0, PdfWriter::cm(0.9 * $scale));

        foreach ([[$px($to[0]), $py($to[1]), $angle], [$px($from[0]), $py($from[1]), $angle + M_PI]] as [$tx, $ty, $a]) {
            $pdf->polygon([
                [$tx, $ty],
                [$tx - ($head * cos($a - 0.35)), $ty - ($head * sin($a - 0.35))],
                [$tx - ($head * cos($a + 0.35)), $ty - ($head * sin($a + 0.35))],
            ], true, 'f');
        }
    }

    /** لبه‌هایی که روی تای پارچه بریده می‌شوند. */
    protected function drawFold(PdfWriter $pdf, PatternPiece $piece, callable $px, callable $py, bool $detail): void
    {
        if (! $piece->on_fold) {
            return;
        }

        $points = $piece->points();
        $count = count($points);

        if ($count < 2) {
            return;
        }

        $pdf->setStrokeColor(...self::DART);
        $pdf->setLineWidth(2.0);
        $pdf->setDash([8, 4]);

        foreach ($this->seams->foldEdges($piece) as $index) {
            $a = $points[$index % $count] ?? null;
            $b = $points[($index + 1) % $count] ?? null;

            if ($a === null || $b === null) {
                continue;
            }

            $pdf->line($px($a['x']), $py($a['y']), $px($b['x']), $py($b['y']));

            if ($detail) {
                $pdf->setFillColor(...self::DART);
                $pdf->text(
                    $pdf->hasEmbeddedFont() ? 'تای پارچه' : 'ON FOLD',
                    $px(($a['x'] + $b['x']) / 2) + 6,
                    $py(($a['y'] + $b['y']) / 2),
                    8,
                );
            }
        }

        $pdf->setDash();
    }

    /** مرکز ثقل تقریبی قطعه (میانگین نقطه‌ها). */
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

    // ------------------------------------------------------- خط‌کش و پانویس

    /** خط‌کش ۱۰ سانتی‌متری، شماره صفحه و نام الگو در پایین هر صفحه. */
    protected function drawFurniture(PdfWriter $pdf, Pattern $pattern, array $page, int $number, int $total): void
    {
        $baseline = PdfWriter::cm(self::MARGIN + 1.15);
        $left = PdfWriter::cm(self::MARGIN);

        $pdf->save();
        $pdf->setStrokeColor(...self::INK);
        $pdf->setLineWidth(0.8);
        $pdf->setDash();

        // خط پایه دقیقاً ۱۰ سانتی‌متر = ۲۸۳٫۴۶ واحد کاربر PDF
        $pdf->line($left, $baseline, $left + PdfWriter::cm(10), $baseline);

        for ($i = 0; $i <= 10; $i++) {
            $tick = PdfWriter::cm($i % 5 === 0 ? 0.42 : 0.24);
            $x = $left + PdfWriter::cm($i);
            $pdf->setLineWidth($i % 5 === 0 ? 0.8 : 0.5);
            $pdf->line($x, $baseline, $x, $baseline + $tick);
        }

        $pdf->setFillColor(...self::INK);

        $caption = $pdf->hasEmbeddedFont()
            ? '۱۰ سانتی‌متر — اگر با خط‌کش برابر نبود چاپگر را روی «اندازه واقعی / ۱۰۰٪» بگذارید.'
            : '10 cm reference. Print at 100% (actual size), no page scaling.';

        $pdf->text($caption, $left, $baseline - PdfWriter::cm(0.55), 8);

        $footer = $pdf->hasEmbeddedFont()
            ? 'صفحه '.Jalali::digits((string) $number).' از '.Jalali::digits((string) $total).' • '.$pattern->name
            : 'page '.$number.' of '.$total.' • pattern #'.$pattern->id;

        $pdf->setFillColor(...self::MUTED);
        $pdf->text($footer, PdfWriter::cm(self::PAGE_WIDTH_CM - self::MARGIN), PdfWriter::cm(self::MARGIN * 0.6), 8, 'right');

        $pdf->restore();
    }
}
