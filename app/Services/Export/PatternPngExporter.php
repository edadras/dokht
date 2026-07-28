<?php

namespace App\Services\Export;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\SeamAllowanceService;
use App\Support\Jalali;
use GdImage;
use RuntimeException;

/**
 * خروجی PNG الگو، مستقیم با GD کشیده می‌شود (هیچ تبدیل SVG به تصویری در کار نیست).
 *
 * قطعه‌ها در یک شبکه چیده می‌شوند، منحنی‌های درجه‌دو به خط شکسته تبدیل می‌شوند و
 * همان چیزهایی که در PDF کشیده می‌شود اینجا هم کشیده می‌شود: خط دوخت، خط برش
 * خط‌چین، ساسون، پیلی، علامت‌های جفت‌شدن، سوراخ نشانه، راستای پارچه و برچسب.
 *
 * چگالی تصویر با ?dpi= داده می‌شود (پیش‌فرض ۱۵۰، محدود به ۷۲ تا ۶۰۰) و
 * ?seam=1 خط برش را روشن می‌کند. اگر اندازه خواسته‌شده از سقف پیکسل بگذرد،
 * ExportTooLargeException با پیام فارسی پرتاب می‌شود.
 *
 * برای نرمی خط‌ها، اگر بودجه اجازه دهد تصویر دو برابر کشیده و سپس کوچک می‌شود.
 * متن فارسی با ArabicShaper شکل‌دهی و راست‌به‌چپ می‌شود و بعد به imagettftext
 * می‌رود؛ FreeType خودش شکل‌دهی نمی‌کند و بدون این کار حرف‌ها جدا می‌افتند.
 */
class PatternPngExporter
{
    public const DEFAULT_DPI = 150;

    public const MIN_DPI = 72;

    public const MAX_DPI = 600;

    /**
     * سقف پیکسل تصویر نهایی.
     *
     * یک الگوی پیراهن کامل در اندازه واقعی حدود یک متر در دو متر است؛ با چگالی
     * پیش‌فرض ۱۵۰ نقطه بر اینچ نزدیک ۷۳ مگاپیکسل می‌شود، پس سقف باید از آن
     * بیشتر باشد وگرنه درخواست پیش‌فرض هم رد می‌شود. برای مهار حافظه، تصویرهای
     * بزرگ روی بوم نمایه‌ای (یک بایت بر پیکسل) کشیده می‌شوند نه رنگ کامل.
     */
    public const MAX_PIXELS = 90_000_000;

    /** سقف طول هر ضلع. */
    public const MAX_SIDE = 20_000;

    /** تا این اندازه، تصویر دو برابر کشیده و کوچک می‌شود تا لبه‌ها نرم شود. */
    protected const SUPERSAMPLE_LIMIT = 4_000_000;

    /** تا این اندازه بوم رنگ کامل است؛ بالاتر از آن بوم نمایه‌ای. */
    protected const TRUECOLOR_LIMIT = 24_000_000;

    /** حاشیه دور تصویر (سانتی‌متر). */
    protected const PADDING = 1.2;

    /** بلندای نوار پایین برای خط‌کش و توضیح (سانتی‌متر). */
    protected const FOOTER = 2.6;

    protected ArabicShaper $shaper;

    public function __construct(protected SeamAllowanceService $seams = new SeamAllowanceService)
    {
        $this->shaper = new ArabicShaper;
    }

    /** مسیر قلم فارسی؛ اگر نبود null و برچسب‌ها با کد لاتین کشیده می‌شوند. */
    protected function fontPath(): ?string
    {
        $path = resource_path('fonts/Vazirmatn-Regular.ttf');

        return is_file($path) && function_exists('imagettftext') ? $path : null;
    }

    /** dpi خواسته‌شده را به بازه مجاز می‌برد. */
    public static function clampDpi(mixed $value): int
    {
        $dpi = (int) round((float) ($value ?? self::DEFAULT_DPI));

        if ($dpi <= 0) {
            $dpi = self::DEFAULT_DPI;
        }

        return max(self::MIN_DPI, min(self::MAX_DPI, $dpi));
    }

    /**
     * اندازه تصویر برای یک الگو و dpi مشخص: [پهنا، بلندا] به پیکسل.
     *
     * @return array{0: int, 1: int}
     */
    public function dimensions(Pattern $pattern, array $options = []): array
    {
        $dpi = static::clampDpi($options['dpi'] ?? self::DEFAULT_DPI);
        $layout = $this->layout($pattern->pieces->all(), $options['seam_allowance'] ?? false);
        $perCm = $dpi / 2.54;

        return [
            max(1, (int) ceil($layout['width'] * $perCm)),
            max(1, (int) ceil($layout['height'] * $perCm)),
        ];
    }

    /**
     * تولید تصویر PNG.
     *
     * گزینه‌ها: dpi، seam_allowance، labels.
     */
    public function export(Pattern $pattern, array $options = []): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('افزونه GD روی این سرور فعال نیست، پس خروجی PNG ساخته نمی‌شود.');
        }

        $dpi = static::clampDpi($options['dpi'] ?? self::DEFAULT_DPI);
        $withSeam = (bool) ($options['seam_allowance'] ?? false);
        $labels = $options['labels'] ?? true;

        $pieces = $pattern->pieces->all();
        $layout = $this->layout($pieces, $withSeam);

        $perCm = $dpi / 2.54;
        $width = max(1, (int) ceil($layout['width'] * $perCm));
        $height = max(1, (int) ceil($layout['height'] * $perCm));

        $this->guardSize($width, $height, $dpi);

        // اگر بودجه اجازه دهد، دو برابر می‌کشیم و کوچک می‌کنیم تا لبه‌ها نرم شود
        $pixels = $width * $height;
        $supersample = $pixels <= self::SUPERSAMPLE_LIMIT
            && ($width * 2) <= self::MAX_SIDE
            && ($height * 2) <= self::MAX_SIDE ? 2 : 1;

        $truecolor = $pixels <= self::TRUECOLOR_LIMIT;

        $image = $truecolor
            ? imagecreatetruecolor($width * $supersample, $height * $supersample)
            : imagecreate($width, $height);

        if ($image === false) {
            throw new RuntimeException('ساخت بوم تصویر ممکن نشد.');
        }

        if (! $truecolor) {
            $supersample = 1;
        }

        imagealphablending($image, true);

        if ($truecolor) {
            imageantialias($image, true);
        }

        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

        $scale = $perCm * $supersample;

        foreach ($layout['boxes'] as $box) {
            /** @var PatternPiece $piece */
            $piece = $box['piece'];

            $px = fn (float|int|string|null $value) => (((float) $value) - $box['min_x'] + $box['x']) * $scale;
            $py = fn (float|int|string|null $value) => (((float) $value) - $box['min_y'] + $box['y']) * $scale;

            $this->drawPiece($image, $piece, $px, $py, $scale, $withSeam);

            if ($labels) {
                $this->drawPieceLabel($image, $piece, $box, $px, $py, $scale, $pattern);
            }
        }

        if ($labels) {
            $this->drawRuler($image, $layout, $scale, $pattern, $dpi);
        }

        if ($supersample > 1) {
            $small = imagescale($image, $width, $height, IMG_BICUBIC_FIXED);

            if ($small !== false) {
                imagedestroy($image);
                $image = $small;
            }
        }

        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /** بررسی سقف پیکسل با پیام فارسی. */
    protected function guardSize(int $width, int $height, int $dpi): void
    {
        $pixels = $width * $height;

        if ($pixels <= self::MAX_PIXELS && $width <= self::MAX_SIDE && $height <= self::MAX_SIDE) {
            return;
        }

        throw new ExportTooLargeException(sprintf(
            'تصویر خواسته‌شده بیش از اندازه بزرگ است: %s×%s پیکسل (%s مگاپیکسل) با چگالی %s نقطه بر اینچ. '
            .'سقف مجاز %s مگاپیکسل و %s پیکسل در هر ضلع است؛ لطفاً چگالی کمتری بخواهید '
            .'(برای نمونه %s نقطه بر اینچ) یا از خروجی PDF و SVG که برداری‌اند استفاده کنید.',
            Jalali::digits((string) $width),
            Jalali::digits((string) $height),
            Jalali::digits((string) round($pixels / 1_000_000, 1)),
            Jalali::digits((string) $dpi),
            Jalali::digits((string) round(self::MAX_PIXELS / 1_000_000)),
            Jalali::digits((string) self::MAX_SIDE),
            Jalali::digits((string) max(self::MIN_DPI, (int) floor($dpi * sqrt(self::MAX_PIXELS / max(1, $pixels))))),
        ));
    }

    /**
     * چیدن قطعه‌ها در یک شبکه با نسبت نزدیک به مربع.
     *
     * @param  array<int, PatternPiece>  $pieces
     */
    protected function layout(array $pieces, bool $withSeam): array
    {
        $boxes = [];

        foreach ($pieces as $piece) {
            [$minX, $minY, $maxX, $maxY] = Geometry::bounds($piece->outline ?? []);

            if ($withSeam) {
                foreach ($this->seams->cuttingLine($piece) as $point) {
                    $minX = min($minX, $point['x']);
                    $minY = min($minY, $point['y']);
                    $maxX = max($maxX, $point['x']);
                    $maxY = max($maxY, $point['y']);
                }
            }

            $boxes[] = [
                'piece' => $piece,
                'min_x' => $minX - 0.4,
                'min_y' => $minY - 0.4,
                'max_x' => $maxX + 0.4,
                'max_y' => $maxY + 0.4,
                'width' => max(1.0, ($maxX - $minX) + 0.8),
                'height' => max(1.0, ($maxY - $minY) + 0.8),
            ];
        }

        if ($boxes === []) {
            return ['boxes' => [], 'width' => 12.0, 'height' => 8.0, 'content_height' => 4.0];
        }

        $gap = 2.0;
        $labelSpace = 1.6;
        $columns = max(1, (int) ceil(sqrt(count($boxes))));

        $placed = [];
        $x = self::PADDING;
        $y = self::PADDING;
        $rowHeight = 0.0;
        $totalWidth = 0.0;

        foreach ($boxes as $index => $box) {
            if ($index > 0 && $index % $columns === 0) {
                $x = self::PADDING;
                $y += $rowHeight + $gap + $labelSpace;
                $rowHeight = 0.0;
            }

            $placed[] = $box + ['x' => $x, 'y' => $y];
            $x += $box['width'] + $gap;
            $rowHeight = max($rowHeight, $box['height']);
            $totalWidth = max($totalWidth, $x - $gap);
        }

        $contentHeight = $y + $rowHeight + $labelSpace;

        return [
            'boxes' => $placed,
            'width' => $totalWidth + self::PADDING,
            'height' => $contentHeight + self::PADDING + self::FOOTER,
            'content_height' => $contentHeight,
        ];
    }

    // ------------------------------------------------------------- کشیدن

    protected function drawPiece(
        GdImage $image,
        PatternPiece $piece,
        callable $px,
        callable $py,
        float $scale,
        bool $withSeam,
    ): void {
        $ink = imagecolorallocate($image, 68, 64, 60);
        $paper = imagecolorallocate($image, 250, 249, 247);
        $cut = imagecolorallocate($image, 126, 92, 218);
        $muted = imagecolorallocate($image, 168, 162, 158);
        $dart = imagecolorallocate($image, 124, 77, 219);
        $notch = imagecolorallocate($image, 212, 87, 62);
        $grain = imagecolorallocate($image, 87, 83, 78);
        $pleat = imagecolorallocate($image, 14, 165, 233);

        $outline = Geometry::flatten($piece->outline ?? [], 24);

        if (count($outline) >= 3) {
            $polygon = [];

            foreach ($outline as $point) {
                $polygon[] = (int) round($px($point['x']));
                $polygon[] = (int) round($py($point['y']));
            }

            imagefilledpolygon($image, $polygon, $paper);
        }

        // خط برش (خط‌چین)
        if ($withSeam) {
            $cutting = $this->seams->cuttingLine($piece);

            if (count($cutting) >= 3) {
                $this->dashedPolygon($image, $cutting, $px, $py, $cut, $this->thickness($scale, 0.035), $scale);
            }
        }

        // خط دوخت
        $this->polyline($image, $outline, $px, $py, $ink, $this->thickness($scale, 0.05), true);

        // خطوط نشانه
        foreach ($piece->markers ?? [] as $marker) {
            if (! isset($marker['from']['x'], $marker['to']['x'])) {
                continue;
            }

            $this->dashedLine(
                $image,
                $px($marker['from']['x']), $py($marker['from']['y']),
                $px($marker['to']['x']), $py($marker['to']['y']),
                $muted, $this->thickness($scale, 0.025), $scale,
            );
        }

        // پیلی‌ها
        foreach ($piece->pleats ?? [] as $item) {
            if (! isset($item['from']['x'], $item['to']['x'])) {
                continue;
            }

            $this->dashedLine(
                $image,
                $px($item['from']['x']), $py($item['from']['y']),
                $px($item['to']['x']), $py($item['to']['y']),
                $pleat, $this->thickness($scale, 0.03), $scale,
            );
        }

        // ساسون‌ها
        imagesetthickness($image, $this->thickness($scale, 0.035));

        foreach ($piece->darts ?? [] as $item) {
            $legs = $item['legs'] ?? [];

            if (count($legs) < 2 || ! isset($item['apex']['x'])) {
                continue;
            }

            foreach ($legs as $leg) {
                imageline($image,
                    (int) round($px($leg['x'])), (int) round($py($leg['y'])),
                    (int) round($px($item['apex']['x'])), (int) round($py($item['apex']['y'])), $dart);
            }

            if (isset($item['apex_lower']['x'])) {
                foreach ($legs as $leg) {
                    imageline($image,
                        (int) round($px($leg['x'])), (int) round($py($leg['y'])),
                        (int) round($px($item['apex_lower']['x'])), (int) round($py($item['apex_lower']['y'])), $dart);
                }
            }

            $this->disc($image, $px($item['apex']['x']), $py($item['apex']['y']), 0.14 * $scale, $dart);
        }

        // علامت‌های جفت‌شدن: هفتِ کوچک رو به داخل
        $centre = $this->centroid($outline);
        imagesetthickness($image, $this->thickness($scale, 0.04));

        foreach ($piece->notches ?? [] as $item) {
            if (! isset($item['x'], $item['y'])) {
                continue;
            }

            $dx = $centre['x'] - (float) $item['x'];
            $dy = $centre['y'] - (float) $item['y'];
            $length = sqrt(($dx * $dx) + ($dy * $dy));

            if ($length < 1e-6) {
                continue;
            }

            $ux = $dx / $length;
            $uy = $dy / $length;
            $tip = [((float) $item['x']) + ($ux * 0.45), ((float) $item['y']) + ($uy * 0.45)];
            $a = [((float) $item['x']) - ($uy * 0.16), ((float) $item['y']) + ($ux * 0.16)];
            $b = [((float) $item['x']) + ($uy * 0.16), ((float) $item['y']) - ($ux * 0.16)];

            imageline($image, (int) round($px($a[0])), (int) round($py($a[1])),
                (int) round($px($tip[0])), (int) round($py($tip[1])), $notch);
            imageline($image, (int) round($px($tip[0])), (int) round($py($tip[1])),
                (int) round($px($b[0])), (int) round($py($b[1])), $notch);
        }

        // سوراخ‌های نشانه
        foreach ($piece->drills ?? [] as $item) {
            if (! isset($item['x'], $item['y'])) {
                continue;
            }

            $radius = max(2.0, 0.18 * $scale);
            imageellipse($image, (int) round($px($item['x'])), (int) round($py($item['y'])),
                (int) round($radius * 2), (int) round($radius * 2), $grain);
        }

        $this->drawGrainline($image, $piece, $px, $py, $scale, $grain);
        $this->drawFold($image, $piece, $px, $py, $scale, $dart);

        imagesetthickness($image, 1);
    }

    protected function drawGrainline(
        GdImage $image,
        PatternPiece $piece,
        callable $px,
        callable $py,
        float $scale,
        int $colour,
    ): void {
        $grainline = $piece->grainline ?? null;

        if (! isset($grainline['from']['x'], $grainline['to']['x'])) {
            return;
        }

        $fromX = $px($grainline['from']['x']);
        $fromY = $py($grainline['from']['y']);
        $toX = $px($grainline['to']['x']);
        $toY = $py($grainline['to']['y']);

        imagesetthickness($image, $this->thickness($scale, 0.035));
        imageline($image, (int) round($fromX), (int) round($fromY), (int) round($toX), (int) round($toY), $colour);

        $angle = atan2($toY - $fromY, $toX - $fromX);
        $head = max(4.0, 0.75 * $scale);

        foreach ([[$toX, $toY, $angle], [$fromX, $fromY, $angle + M_PI]] as [$tx, $ty, $a]) {
            imagefilledpolygon($image, [
                (int) round($tx), (int) round($ty),
                (int) round($tx - ($head * cos($a - 0.35))), (int) round($ty - ($head * sin($a - 0.35))),
                (int) round($tx - ($head * cos($a + 0.35))), (int) round($ty - ($head * sin($a + 0.35))),
            ], $colour);
        }
    }

    protected function drawFold(
        GdImage $image,
        PatternPiece $piece,
        callable $px,
        callable $py,
        float $scale,
        int $colour,
    ): void {
        if (! $piece->on_fold) {
            return;
        }

        $points = $piece->points();
        $count = count($points);

        if ($count < 2) {
            return;
        }

        foreach ($this->seams->foldEdges($piece) as $index) {
            $a = $points[$index % $count] ?? null;
            $b = $points[($index + 1) % $count] ?? null;

            if ($a === null || $b === null) {
                continue;
            }

            $this->dashedLine($image, $px($a['x']), $py($a['y']), $px($b['x']), $py($b['y']),
                $colour, $this->thickness($scale, 0.08), $scale, 0.9, 0.4);
        }
    }

    /** نام قطعه، تعداد برش و اندازه، زیر هر قطعه. */
    protected function drawPieceLabel(
        GdImage $image,
        PatternPiece $piece,
        array $box,
        callable $px,
        callable $py,
        float $scale,
        Pattern $pattern,
    ): void {
        $ink = imagecolorallocate($image, 41, 37, 36);
        $muted = imagecolorallocate($image, 120, 113, 108);
        $font = $this->fontPath();

        $centreX = $px(($box['min_x'] + $box['max_x']) / 2);
        $baseline = $py($box['max_y']) + (0.65 * $scale);

        $title = $font !== null
            ? $piece->name.' ×'.Jalali::digits((string) $piece->cut_quantity)
            : $piece->code.' x'.$piece->cut_quantity;

        $meta = $font !== null
            ? Jalali::digits((string) round($piece->width(), 1)).'×'.Jalali::digits((string) round($piece->height(), 1))
                .' سانتی‌متر • سایز '.Jalali::digits((string) $pattern->base_size)
            : round($piece->width(), 1).'x'.round($piece->height(), 1).' cm / size '.$pattern->base_size;

        $this->write($image, $title, $centreX, $baseline, 0.36 * $scale, $ink, 'center');
        $this->write($image, $meta, $centreX, $baseline + (0.55 * $scale), 0.28 * $scale, $muted, 'center');
    }

    /** خط‌کش ۱۰ سانتی‌متری پایین تصویر برای کنترل مقیاس چاپ. */
    protected function drawRuler(GdImage $image, array $layout, float $scale, Pattern $pattern, int $dpi): void
    {
        $ink = imagecolorallocate($image, 41, 37, 36);
        $muted = imagecolorallocate($image, 120, 113, 108);

        $x = self::PADDING * $scale;
        $y = ($layout['content_height'] + 1.1) * $scale;

        imagesetthickness($image, max(1, (int) round(0.04 * $scale)));
        imageline($image, (int) round($x), (int) round($y), (int) round($x + (10 * $scale)), (int) round($y), $ink);

        for ($i = 0; $i <= 10; $i++) {
            $tick = ($i % 5 === 0 ? 0.42 : 0.24) * $scale;
            $tx = (int) round($x + ($i * $scale));
            imageline($image, $tx, (int) round($y), $tx, (int) round($y - $tick), $ink);
        }

        imagesetthickness($image, 1);

        $font = $this->fontPath();

        $caption = $font !== null
            ? '۱۰ سانتی‌متر در '.Jalali::digits((string) $dpi).' نقطه بر اینچ — '.$pattern->name
            : '10 cm at '.$dpi.' dpi — pattern #'.$pattern->id;

        $this->write($image, $caption, $x, $y + (0.7 * $scale), 0.32 * $scale, $muted, 'left');
    }

    // ------------------------------------------------------------- ابزارها

    /** نوشتن متن با شکل‌دهی فارسی؛ اگر قلمی نباشد از قلم درونی GD استفاده می‌شود. */
    protected function write(
        GdImage $image,
        string $text,
        float $x,
        float $y,
        float $size,
        int $colour,
        string $align = 'left',
    ): void {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $font = $this->fontPath();
        $size = max(6.0, $size);

        if ($font === null) {
            $ascii = trim((string) preg_replace('/[^\x20-\x7E]/', '', $text));

            if ($ascii === '') {
                return;
            }

            $width = imagefontwidth(5) * strlen($ascii);
            $x = match ($align) {
                'center' => $x - ($width / 2),
                'right' => $x - $width,
                default => $x,
            };

            imagestring($image, 5, (int) round($x), (int) round($y), $ascii, $colour);

            return;
        }

        $visual = $this->shaper->visual($text);
        $box = imagettfbbox($size, 0, $font, $visual);
        $width = $box === false ? 0 : abs($box[2] - $box[0]);

        $x = match ($align) {
            'center' => $x - ($width / 2),
            'right' => $x - $width,
            default => $x,
        };

        imagettftext($image, $size, 0, (int) round($x), (int) round($y), $colour, $font, $visual);
    }

    /** خط شکسته بسته یا باز. */
    protected function polyline(
        GdImage $image,
        array $points,
        callable $px,
        callable $py,
        int $colour,
        int $thickness,
        bool $close,
    ): void {
        $count = count($points);

        if ($count < 2) {
            return;
        }

        imagesetthickness($image, $thickness);
        $last = $close ? $count : $count - 1;

        for ($i = 0; $i < $last; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];

            imageline($image,
                (int) round($px($a['x'])), (int) round($py($a['y'])),
                (int) round($px($b['x'])), (int) round($py($b['y'])), $colour);
        }
    }

    /** چندضلعی خط‌چین. */
    protected function dashedPolygon(
        GdImage $image,
        array $points,
        callable $px,
        callable $py,
        int $colour,
        int $thickness,
        float $scale,
    ): void {
        $count = count($points);

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];

            $this->dashedLine($image, $px($a['x']), $py($a['y']), $px($b['x']), $py($b['y']),
                $colour, $thickness, $scale);
        }
    }

    /**
     * خط‌چین دستی (imagesetstyle با ضخامت بیش از یک نتیجه ناهموار می‌دهد،
     * پس پاره‌خط‌ها را خودمان می‌شماریم).
     */
    protected function dashedLine(
        GdImage $image,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        int $colour,
        int $thickness,
        float $scale,
        float $dash = 0.32,
        float $gap = 0.2,
    ): void {
        $length = sqrt((($x2 - $x1) ** 2) + (($y2 - $y1) ** 2));

        if ($length < 1e-6) {
            return;
        }

        imagesetthickness($image, $thickness);

        $step = max(2.0, ($dash + $gap) * $scale);
        $on = max(1.0, $dash * $scale);
        $ux = ($x2 - $x1) / $length;
        $uy = ($y2 - $y1) / $length;

        for ($travelled = 0.0; $travelled < $length; $travelled += $step) {
            $end = min($length, $travelled + $on);

            imageline($image,
                (int) round($x1 + ($ux * $travelled)), (int) round($y1 + ($uy * $travelled)),
                (int) round($x1 + ($ux * $end)), (int) round($y1 + ($uy * $end)), $colour);
        }
    }

    protected function disc(GdImage $image, float $x, float $y, float $radius, int $colour): void
    {
        $diameter = (int) round(max(2.0, $radius * 2));

        imagefilledellipse($image, (int) round($x), (int) round($y), $diameter, $diameter, $colour);
    }

    /** ضخامت خط به پیکسل، دست‌کم یک پیکسل. */
    protected function thickness(float $scale, float $cm): int
    {
        return max(1, (int) round($cm * $scale));
    }

    /** مرکز تقریبی خط شکسته. */
    protected function centroid(array $points): array
    {
        if ($points === []) {
            return ['x' => 0.0, 'y' => 0.0];
        }

        return [
            'x' => array_sum(array_column($points, 'x')) / count($points),
            'y' => array_sum(array_column($points, 'y')) / count($points),
        ];
    }
}
