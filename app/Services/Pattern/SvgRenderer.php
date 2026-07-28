<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Support\Jalali;

/**
 * کشیدن الگو به صورت SVG.
 *
 * همه مسیرها در واحد سانتی‌متر ساخته می‌شوند و مقیاس فقط با viewBox و اندازه بیرونی
 * تصویر اعمال می‌شود؛ به این ترتیب همان مسیر برای چاپ ۱:۱، پیش‌نمایش و چیدمان برش
 * قابل استفاده است. قرارداد نقطه‌ها و منحنی‌ها در Geometry توضیح داده شده است.
 */
class SvgRenderer
{
    public function __construct(protected SeamAllowanceService $seams = new SeamAllowanceService) {}

    /**
     * رشته d مسیر یک قطعه (سانتی‌متر).
     *
     * گزینه seam_allowance => true خط برش بیرونی را برمی‌گرداند نه خط دوخت.
     */
    public function piecePath(PatternPiece $piece, array $options = []): string
    {
        if ($options['seam_allowance'] ?? false) {
            $points = $this->seams->cuttingLine($piece, (float) ($options['default_allowance'] ?? 1.0));

            return $this->polygonPath($points);
        }

        return $this->outlinePath($piece->outline ?? []);
    }

    /**
     * مسیر یک outline خام (بدون نیاز به مدل).
     */
    public function outlinePath(array $outline): string
    {
        $outline = array_values($outline);
        $count = count($outline);

        if ($count === 0) {
            return '';
        }

        $first = $outline[0];
        $d = 'M '.$this->n($first['x'] ?? 0).' '.$this->n($first['y'] ?? 0);

        for ($i = 1; $i < $count; $i++) {
            $d .= ' '.$this->segment($outline[$i]);
        }

        // بستن قطعه: اگر نقطه اول منحنی باشد، لبه بسته‌شدن هم منحنی است
        if (Geometry::isCurve($first)) {
            $d .= ' Q '.$this->n($first['cx']).' '.$this->n($first['cy']).' '.$this->n($first['x']).' '.$this->n($first['y']);
        }

        return $d.' Z';
    }

    /** مسیر یک چندضلعی ساده. */
    public function polygonPath(array $points): string
    {
        if ($points === []) {
            return '';
        }

        $parts = [];

        foreach (array_values($points) as $index => $point) {
            $parts[] = ($index === 0 ? 'M ' : 'L ').$this->n($point['x']).' '.$this->n($point['y']);
        }

        return implode(' ', $parts).' Z';
    }

    /**
     * یک قطعه تنها در قالب یک SVG مستقل.
     *
     * گزینه‌ها: scale (پیکسل بر سانتی‌متر)، seam_allowance، labels، padding، mm (اندازه واقعی).
     */
    public function renderPiece(PatternPiece $piece, array $options = []): string
    {
        $scale = (float) ($options['scale'] ?? 3);
        $padding = (float) ($options['padding'] ?? 2);
        $labels = $options['labels'] ?? true;
        $withSeam = $options['seam_allowance'] ?? true;

        [$minX, $minY, $maxX, $maxY] = $piece->bounds();

        if ($withSeam) {
            $cutting = $this->seams->cuttingLine($piece);

            foreach ($cutting as $point) {
                $minX = min($minX, $point['x']);
                $minY = min($minY, $point['y']);
                $maxX = max($maxX, $point['x']);
                $maxY = max($maxY, $point['y']);
            }
        }

        $width = ($maxX - $minX) + ($padding * 2);
        $height = ($maxY - $minY) + ($padding * 2);

        $body = $this->pieceGroup($piece, [
            'seam_allowance' => $withSeam,
            'labels' => $labels,
            'interactive' => $options['interactive'] ?? false,
        ]);

        $sizeAttributes = ($options['mm'] ?? false)
            ? 'width="'.$this->n($width * 10).'mm" height="'.$this->n($height * 10).'mm"'
            : 'width="'.$this->n($width * $scale).'" height="'.$this->n($height * $scale).'"';

        return $this->document(
            $sizeAttributes,
            $this->n($minX - $padding).' '.$this->n($minY - $padding).' '.$this->n($width).' '.$this->n($height),
            $body,
        );
    }

    /**
     * همه قطعه‌های یک الگو، چیده‌شده در یک شبکه.
     *
     * گزینه‌ها: scale، seam_allowance، labels، width، height، interactive، columns، gap.
     */
    public function renderPattern(Pattern $pattern, array $options = []): string
    {
        return $this->renderPieces($pattern->pieces, array_merge([
            'size' => $pattern->base_size,
            'pattern_id' => $pattern->id,
        ], $options));
    }

    /**
     * چیدن مجموعه‌ای از قطعه‌ها در یک شبکه.
     *
     * قطعه‌ها می‌توانند ذخیره‌نشده باشند؛ برای ساخت پیش‌نمایش الگوهای پایه به کار می‌آید.
     *
     * @param  iterable<PatternPiece>  $pieces
     */
    public function renderPieces(iterable $pieces, array $options = []): string
    {
        $pieces = collect($pieces);

        if ($pieces->isEmpty()) {
            return $this->document('width="100" height="60"', '0 0 100 60',
                '<text x="50" y="32" text-anchor="middle" font-size="6" fill="#a8a29e">قطعه‌ای برای نمایش نیست</text>');
        }

        $scale = (float) ($options['scale'] ?? 3);
        $gap = (float) ($options['gap'] ?? 6);
        $labels = $options['labels'] ?? true;
        $withSeam = $options['seam_allowance'] ?? false;
        $interactive = $options['interactive'] ?? false;

        $boxes = [];

        foreach ($pieces as $piece) {
            [$minX, $minY, $maxX, $maxY] = $piece->bounds();

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
                'min_x' => $minX,
                'min_y' => $minY,
                'width' => max(1.0, $maxX - $minX),
                'height' => max(1.0, $maxY - $minY),
            ];
        }

        $columns = (int) ($options['columns'] ?? max(1, (int) ceil(sqrt(count($boxes)))));
        $rows = [];
        $index = 0;

        foreach ($boxes as $box) {
            $rows[intdiv($index, $columns)][] = $box;
            $index++;
        }

        $content = '';
        $offsetY = $gap;
        $totalWidth = 0.0;

        foreach ($rows as $row) {
            $offsetX = $gap;
            $rowHeight = 0.0;

            foreach ($row as $box) {
                $dx = $offsetX - $box['min_x'];
                $dy = $offsetY - $box['min_y'];

                $content .= '<g transform="translate('.$this->n($dx).' '.$this->n($dy).')">'
                    .$this->pieceGroup($box['piece'], [
                        'seam_allowance' => $withSeam,
                        'labels' => $labels,
                        'interactive' => $interactive,
                        'size' => $options['size'] ?? null,
                    ])
                    .'</g>';

                $offsetX += $box['width'] + $gap;
                $rowHeight = max($rowHeight, $box['height']);
            }

            $totalWidth = max($totalWidth, $offsetX);
            $offsetY += $rowHeight + $gap + ($labels ? 5 : 0);
        }

        $totalHeight = $offsetY + 12; // جا برای خط‌کش پایین
        $totalWidth = max($totalWidth, 40);

        if ($labels) {
            $content .= $this->scaleBar(($gap), $totalHeight - 8);
        }

        $sizeAttributes = match (true) {
            isset($options['width'], $options['height']) => 'width="'.$options['width'].'" height="'.$options['height'].'"',
            isset($options['width']) => 'width="'.$options['width'].'" height="'.$this->n(($totalHeight / $totalWidth) * (float) $options['width']).'"',
            default => 'width="'.$this->n($totalWidth * $scale).'" height="'.$this->n($totalHeight * $scale).'"',
        };

        return $this->document(
            $sizeAttributes,
            '0 0 '.$this->n($totalWidth).' '.$this->n($totalHeight),
            $content,
            $interactive && isset($options['pattern_id']) ? ' data-pattern-id="'.$options['pattern_id'].'"' : '',
        );
    }

    /** بندانگشتی کوچک برای فهرست‌ها. */
    public function thumbnail(Pattern $pattern, int $size = 220): string
    {
        return $this->renderPattern($pattern, [
            'width' => $size,
            'height' => $size,
            'labels' => false,
            'seam_allowance' => false,
            'gap' => 4,
        ]);
    }

    /**
     * گروه SVG یک قطعه: خط دوخت، خط برش، ساسون، نشانه‌ها، راستای پارچه و نام.
     */
    public function pieceGroup(PatternPiece $piece, array $options = []): string
    {
        $labels = $options['labels'] ?? true;
        $withSeam = $options['seam_allowance'] ?? false;
        $interactive = $options['interactive'] ?? false;

        [$minX, $minY, $maxX, $maxY] = $piece->bounds();

        $parts = [];

        if ($withSeam) {
            $parts[] = '<path d="'.$this->piecePath($piece, ['seam_allowance' => true]).'" fill="none" '
                .'stroke="#a78bfa" stroke-width="0.12" stroke-dasharray="1.2 0.8" />';
        }

        $parts[] = '<path d="'.$this->piecePath($piece).'" fill="#faf9f7" fill-opacity="0.85" '
            .'stroke="#44403c" stroke-width="0.18" stroke-linejoin="round" />';

        // خطوط نشانه (سینه، کمر، باسن، مرکز جلو و پشت)
        foreach ($piece->markers ?? [] as $marker) {
            if (! isset($marker['from']['x'], $marker['to']['x'])) {
                continue;
            }

            $dash = in_array($marker['key'] ?? '', ['cf', 'cb', 'fold', 'sleeve_center'], true) ? '2 1' : '1 1';

            $parts[] = '<line x1="'.$this->n($marker['from']['x']).'" y1="'.$this->n($marker['from']['y'])
                .'" x2="'.$this->n($marker['to']['x']).'" y2="'.$this->n($marker['to']['y'])
                .'" stroke="#a8a29e" stroke-width="0.1" stroke-dasharray="'.$dash.'" />';

            if ($labels && ! empty($marker['label'])) {
                $parts[] = '<text x="'.$this->n(((float) $marker['from']['x']) + 0.4).'" y="'
                    .$this->n(((float) $marker['from']['y']) - 0.4).'" font-size="1.1" fill="#a8a29e">'
                    .$this->escape($marker['label']).'</text>';
            }
        }

        // ساسون‌ها
        foreach ($piece->darts ?? [] as $dart) {
            $legs = $dart['legs'] ?? [];
            $apex = $dart['apex'] ?? null;

            if (count($legs) < 2 || ! isset($apex['x'])) {
                continue;
            }

            foreach ($legs as $leg) {
                $parts[] = '<line x1="'.$this->n($leg['x']).'" y1="'.$this->n($leg['y']).'" x2="'.$this->n($apex['x'])
                    .'" y2="'.$this->n($apex['y']).'" stroke="#7c4ddb" stroke-width="0.12" />';
            }

            if (isset($dart['apex_lower']['x'])) {
                foreach ($legs as $leg) {
                    $parts[] = '<line x1="'.$this->n($leg['x']).'" y1="'.$this->n($leg['y']).'" x2="'
                        .$this->n($dart['apex_lower']['x']).'" y2="'.$this->n($dart['apex_lower']['y'])
                        .'" stroke="#7c4ddb" stroke-width="0.12" />';
                }
            }

            $parts[] = '<circle cx="'.$this->n($apex['x']).'" cy="'.$this->n($apex['y']).'" r="0.22" fill="#7c4ddb" />';
        }

        // پیلی‌ها
        foreach ($piece->pleats ?? [] as $pleat) {
            if (! isset($pleat['from']['x'], $pleat['to']['x'])) {
                continue;
            }

            $parts[] = '<line x1="'.$this->n($pleat['from']['x']).'" y1="'.$this->n($pleat['from']['y'])
                .'" x2="'.$this->n($pleat['to']['x']).'" y2="'.$this->n($pleat['to']['y'])
                .'" stroke="#0ea5e9" stroke-width="0.12" stroke-dasharray="1.5 0.6" />';
        }

        // علامت‌های جفت‌شدن
        foreach ($piece->notches ?? [] as $notch) {
            if (! isset($notch['x'], $notch['y'])) {
                continue;
            }

            $parts[] = '<circle cx="'.$this->n($notch['x']).'" cy="'.$this->n($notch['y'])
                .'" r="0.3" fill="none" stroke="#d4573e" stroke-width="0.12" />';
        }

        // سوراخ‌های نشانه
        foreach ($piece->drills ?? [] as $drill) {
            if (! isset($drill['x'], $drill['y'])) {
                continue;
            }

            $parts[] = '<circle cx="'.$this->n($drill['x']).'" cy="'.$this->n($drill['y']).'" r="0.18" fill="#78716c" />';
        }

        $parts[] = $this->grainlineArrow($piece);

        if ($piece->on_fold) {
            $parts[] = $this->foldMark($piece);
        }

        if ($labels) {
            $parts[] = '<text x="'.$this->n($minX + 0.6).'" y="'.$this->n($maxY + 2.6).'" font-size="1.9" '
                .'font-weight="bold" fill="#44403c">'.$this->escape($piece->name).'</text>';

            $meta = [
                Jalali::digits((string) round($piece->width(), 1)).'×'.Jalali::digits((string) round($piece->height(), 1)).' سانتی‌متر',
                'تعداد برش: '.Jalali::digits((string) $piece->cut_quantity),
            ];

            if ($piece->on_fold) {
                $meta[] = 'روی تای پارچه';
            }

            if (! empty($options['size'])) {
                $meta[] = 'سایز '.Jalali::digits((string) $options['size']);
            }

            $parts[] = '<text x="'.$this->n($minX + 0.6).'" y="'.$this->n($maxY + 4.6).'" font-size="1.3" '
                .'fill="#78716c">'.$this->escape(implode(' • ', $meta)).'</text>';
        }

        $attributes = 'class="pattern-piece"';

        if ($interactive) {
            $attributes .= ' data-piece-id="'.$piece->id.'" data-piece-code="'.$this->escape($piece->code).'"';
        }

        return '<g '.$attributes.'>'.implode('', $parts).'</g>';
    }

    /** پیکان راستای پارچه. */
    protected function grainlineArrow(PatternPiece $piece): string
    {
        $grainline = $piece->grainline ?? null;

        if (! isset($grainline['from']['x'], $grainline['to']['x'])) {
            return '';
        }

        $from = $grainline['from'];
        $to = $grainline['to'];
        $angle = atan2(((float) $to['y']) - ((float) $from['y']), ((float) $to['x']) - ((float) $from['x']));
        $head = 1.1;

        $arrow = function (array $tip, float $direction) use ($head, $angle) {
            $left = [
                'x' => $tip['x'] - ($head * cos($angle + ($direction * 0.45))),
                'y' => $tip['y'] - ($head * sin($angle + ($direction * 0.45))),
            ];
            $right = [
                'x' => $tip['x'] - ($head * cos($angle - ($direction * 0.45))),
                'y' => $tip['y'] - ($head * sin($angle - ($direction * 0.45))),
            ];

            return '<polyline points="'.$this->n($left['x']).','.$this->n($left['y']).' '.$this->n($tip['x']).','
                .$this->n($tip['y']).' '.$this->n($right['x']).','.$this->n($right['y'])
                .'" fill="none" stroke="#57534e" stroke-width="0.12" />';
        };

        return '<g class="grainline">'
            .'<line x1="'.$this->n($from['x']).'" y1="'.$this->n($from['y']).'" x2="'.$this->n($to['x']).'" y2="'
            .$this->n($to['y']).'" stroke="#57534e" stroke-width="0.12" />'
            .$arrow(['x' => (float) $to['x'], 'y' => (float) $to['y']], 1)
            .$arrow(['x' => (float) $from['x'], 'y' => (float) $from['y']], -1)
            .'</g>';
    }

    /** علامت تای پارچه روی لبه‌هایی که روی تا بریده می‌شوند. */
    protected function foldMark(PatternPiece $piece): string
    {
        $tags = $this->seams->foldEdges($piece);
        $points = $piece->points();
        $count = count($points);

        if ($count < 2) {
            return '';
        }

        $marks = '';

        foreach ($tags as $index) {
            $a = $points[$index % $count] ?? null;
            $b = $points[($index + 1) % $count] ?? null;

            if ($a === null || $b === null) {
                continue;
            }

            $marks .= '<line x1="'.$this->n($a['x']).'" y1="'.$this->n($a['y']).'" x2="'.$this->n($b['x'])
                .'" y2="'.$this->n($b['y']).'" stroke="#7c4ddb" stroke-width="0.3" stroke-dasharray="3 1.2" />';

            $midX = ($a['x'] + $b['x']) / 2;
            $midY = ($a['y'] + $b['y']) / 2;
            $marks .= '<text x="'.$this->n($midX + 0.6).'" y="'.$this->n($midY).'" font-size="1.2" fill="#7c4ddb">'
                .'تای پارچه</text>';
        }

        return $marks;
    }

    /** خط‌کش ۱۰ سانتی‌متری برای بررسی درستی مقیاس چاپ. */
    public function scaleBar(float $x, float $y): string
    {
        $ticks = '';

        for ($i = 0; $i <= 10; $i++) {
            $height = $i % 5 === 0 ? 1.6 : 0.9;
            $ticks .= '<line x1="'.$this->n($x + $i).'" y1="'.$this->n($y).'" x2="'.$this->n($x + $i)
                .'" y2="'.$this->n($y - $height).'" stroke="#44403c" stroke-width="0.1" />';
        }

        return '<g class="scale-bar">'
            .'<line x1="'.$this->n($x).'" y1="'.$this->n($y).'" x2="'.$this->n($x + 10).'" y2="'.$this->n($y)
            .'" stroke="#44403c" stroke-width="0.15" />'
            .$ticks
            .'<text x="'.$this->n($x + 10.6).'" y="'.$this->n($y).'" font-size="1.4" fill="#44403c">'
            .'۱۰ سانتی‌متر (کنترل مقیاس چاپ)</text>'
            .'</g>';
    }

    /** یک قطعه از مسیر بعدی. */
    protected function segment(array $point): string
    {
        if (Geometry::isCurve($point)) {
            return 'Q '.$this->n($point['cx']).' '.$this->n($point['cy']).' '.$this->n($point['x']).' '.$this->n($point['y']);
        }

        return 'L '.$this->n($point['x']).' '.$this->n($point['y']);
    }

    protected function document(string $sizeAttributes, string $viewBox, string $body, string $extra = ''): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" '.$sizeAttributes.' viewBox="'.$viewBox.'" '
            .'font-family="Vazirmatn, sans-serif"'.$extra.'>'.$body.'</svg>';
    }

    protected function n(float|int|string|null $value): string
    {
        $number = round((float) $value, 3);

        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.') ?: '0';
    }

    protected function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
