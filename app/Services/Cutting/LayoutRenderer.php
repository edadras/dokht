<?php

namespace App\Services\Cutting;

use App\Models\CuttingLayout;
use App\Models\PatternPiece;
use App\Support\Jalali;

/**
 * کشیدن نقشه برش روی پارچه به شکل SVG.
 *
 * واحد مختصات داخلی سانتی‌متر است: پارچه از x = ۰ (لبه تا، وقتی پارچه تا شده) تا
 * عرض مفید کشیده می‌شود و طول در جهت y پایین می‌رود. در حالت چاپ، اندازه فیزیکی
 * تصویر با میلی‌متر تعیین و مقیاس روی نقشه نوشته می‌شود تا خیاط بداند نسبت چیست.
 */
class LayoutRenderer
{
    /** رنگ‌بندی لایه‌ها (پارچه رو، آستر، لایی، …). */
    public const LAYER_STYLES = [
        'outer' => ['fill' => '#dbeafe', 'stroke' => '#1d4ed8'],
        'lining' => ['fill' => '#ede9fe', 'stroke' => '#6d28d9'],
        'interfacing' => ['fill' => '#fef3c7', 'stroke' => '#b45309'],
        'padding' => ['fill' => '#ffe4e6', 'stroke' => '#be123c'],
        'underlining' => ['fill' => '#d1fae5', 'stroke' => '#047857'],
    ];

    protected const GUTTER = 9.0;   // جای خط‌کش در سمت چپ نقشه

    protected const MARGIN = 4.0;   // حاشیه بالا و پایین

    public function render(CuttingLayout $layout, array $options = []): string
    {
        $print = (bool) ($options['print'] ?? false);
        $showLegend = (bool) ($options['legend'] ?? true);
        $showLabels = (bool) ($options['labels'] ?? true);

        $usable = max(10.0, $layout->usableWidthCm());
        $length = max(20.0, (float) $layout->required_length_cm);
        $placements = $this->placements($layout);

        $legendHeight = $showLegend ? 8.0 : 0.0;
        $viewX = -static::GUTTER;
        $viewY = -static::MARGIN;
        $viewWidth = $usable + static::GUTTER + 6.0;
        $viewHeight = $length + static::MARGIN * 2 + $legendHeight;

        $body = implode("\n", array_filter([
            $this->fabric($usable, $length, (bool) $layout->folded),
            $this->patternGrid($layout, $usable, $length, $options),
            $this->ruler($length),
            $this->plan($placements, $showLabels),
            $showLegend ? $this->legend($placements, $usable, $length) : null,
        ]));

        $size = $print
            ? $this->printSize($viewWidth, $viewHeight, $options)
            : ['attributes' => 'style="width:100%;height:auto" class="max-w-full"', 'scale' => null];

        // یادداشت مقیاس در گوشه خالی بالای خط‌کش می‌نشیند
        $scaleNote = $print && $size['scale']
            ? $this->text(
                -static::GUTTER + 0.5,
                -1.2,
                'مقیاس ۱ به '.Jalali::digits((string) $size['scale']),
                ['size' => 2, 'fill' => '#78716c', 'anchor' => 'start'],
            )
            : '';

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s %s %s %s" %s role="img" aria-label="نقشه برش">'
            ."\n%s\n%s\n</svg>",
            $this->num($viewX),
            $this->num($viewY),
            $this->num($viewWidth),
            $this->num($viewHeight),
            $size['attributes'],
            $body,
            $scaleNote,
        );
    }

    /** اندازه فیزیکی برای چاپ: مقیاس طوری انتخاب می‌شود که نقشه در صفحه جا شود. */
    protected function printSize(float $viewWidth, float $viewHeight, array $options): array
    {
        $pageWidth = (float) ($options['page_width_mm'] ?? 180);
        $pageHeight = (float) ($options['page_height_mm'] ?? 240);

        $widthMm = $viewWidth * 10;
        $heightMm = $viewHeight * 10;

        $ratio = min($pageWidth / max(1, $widthMm), $pageHeight / max(1, $heightMm));
        $scale = max(1, (int) round(1 / max($ratio, 0.0001)));

        return [
            'attributes' => sprintf(
                'width="%smm" height="%smm"',
                $this->num($widthMm * $ratio, 1),
                $this->num($heightMm * $ratio, 1),
            ),
            'scale' => $scale,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function placements(CuttingLayout $layout): array
    {
        return array_values(array_filter((array) ($layout->placements ?? []), 'is_array'));
    }

    /** پارچه، لبه‌ها و خط تا. */
    protected function fabric(float $usable, float $length, bool $folded): string
    {
        $parts = [$this->hatchDefinition()];

        $parts[] = sprintf(
            '<rect x="0" y="0" width="%s" height="%s" fill="#fffdf9" stroke="#a8a29e" stroke-width="0.25" />',
            $this->num($usable),
            $this->num($length),
        );

        // نوار لبه پارچه (سلوج)
        $parts[] = sprintf(
            '<rect x="%s" y="0" width="1.2" height="%s" fill="url(#selvedge)" stroke="none" />',
            $this->num($usable - 1.2),
            $this->num($length),
        );

        $parts[] = $this->text($usable - 2, -1.2, 'لبه پارچه (سلوج)', ['size' => 2, 'fill' => '#78716c', 'anchor' => 'end']);

        if ($folded) {
            $parts[] = sprintf(
                '<line x1="0.35" y1="0" x2="0.35" y2="%s" stroke="#0d9488" stroke-width="0.3" stroke-dasharray="2 1.2" />',
                $this->num($length),
            );
            $parts[] = $this->text(1.2, -1.2, 'تای پارچه', ['size' => 2, 'fill' => '#0d9488', 'anchor' => 'start']);
        } else {
            $parts[] = sprintf(
                '<rect x="0" y="0" width="1.2" height="%s" fill="url(#selvedge)" stroke="none" />',
                $this->num($length),
            );
            $parts[] = $this->text(1.4, -1.2, 'پارچه باز', ['size' => 2, 'fill' => '#78716c', 'anchor' => 'start']);
        }

        return implode("\n", $parts);
    }

    /**
     * شبکه راه‌راه یا چهارخانه پارچه، پشت قطعه‌ها.
     *
     * تا وقتی خیاط شبکه طرح را نبیند، نمی‌تواند داوری کند که تطبیق درزها درست
     * درآمده است یا نه. خط‌های افقی تکرار طرح در طول پارچه‌اند و خط‌های عمودی —
     * فقط برای چهارخانه — تکرار در عرض. مبدأ شبکه، لبه تای پارچه و سر پارچه است.
     */
    protected function patternGrid(CuttingLayout $layout, float $usable, float $length, array $options): string
    {
        $fabric = $layout->fabric;
        $surface = (string) ($options['surface_pattern'] ?? $fabric?->surface_pattern ?? 'plain');
        $repeat = (float) ($options['repeat_cm'] ?? $fabric?->pattern_repeat_cm ?? 0);

        if ($repeat < 0.5 || ! in_array($surface, ['stripe', 'plaid'], true)) {
            return '';
        }

        // اگر تکرار طرح خیلی ریز باشد، چند برابرش کشیده می‌شود تا نقشه سیاه نشود
        $step = $repeat;

        while ($length / $step > 160) {
            $step *= 2;
        }

        $color = $layout->match_stripes ? '#0d9488' : '#a8a29e';
        $parts = [];

        for ($y = $step; $y < $length - 0.01; $y += $step) {
            $parts[] = sprintf(
                '<line x1="0" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="0.12" opacity="0.45" />',
                $this->num($y),
                $this->num($usable),
                $this->num($y),
                $color,
            );
        }

        if ($surface === 'plaid') {
            $stepX = (float) ($options['repeat_x_cm'] ?? $repeat);

            if ($stepX >= 0.5) {
                while ($usable / $stepX > 80) {
                    $stepX *= 2;
                }

                for ($x = $stepX; $x < $usable - 0.01; $x += $stepX) {
                    $parts[] = sprintf(
                        '<line x1="%s" y1="0" x2="%s" y2="%s" stroke="%s" stroke-width="0.12" opacity="0.45" />',
                        $this->num($x),
                        $this->num($x),
                        $this->num($length),
                        $color,
                    );
                }
            }
        }

        if ($parts === []) {
            return '';
        }

        $parts[] = $this->text(
            $usable,
            $length + 2.6,
            sprintf(
                '%s پارچه — تکرار %s سانتی‌متر%s',
                $surface === 'plaid' ? 'شبکه چهارخانه' : 'راه‌راه',
                Jalali::digits($this->num($repeat)),
                $layout->match_stripes ? ' (قطعه‌ها روی همین شبکه تنظیم شده‌اند)' : '',
            ),
            ['size' => 2, 'fill' => $color, 'anchor' => 'end'],
        );

        return implode("\n", $parts);
    }

    protected function hatchDefinition(): string
    {
        return '<defs><pattern id="selvedge" width="1.2" height="1.2" patternUnits="userSpaceOnUse" '
            .'patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="1.2" stroke="#d6d3d1" '
            .'stroke-width="0.5" /></pattern></defs>';
    }

    /** خط‌کش متری کنار پارچه. */
    protected function ruler(float $length): string
    {
        $x = -5.0;
        $parts = [sprintf(
            '<line x1="%s" y1="0" x2="%s" y2="%s" stroke="#78716c" stroke-width="0.2" />',
            $this->num($x),
            $this->num($x),
            $this->num($length),
        )];

        for ($cm = 0; $cm <= $length + 0.01; $cm += 10) {
            $isMeter = $cm % 100 === 0;
            $isHalf = $cm % 50 === 0;
            $tick = $isMeter ? 2.6 : ($isHalf ? 1.8 : 1.0);

            $parts[] = sprintf(
                '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#78716c" stroke-width="%s" />',
                $this->num($x),
                $this->num($cm),
                $this->num($x + $tick),
                $this->num($cm),
                $isMeter ? '0.25' : '0.15',
            );

            if ($isHalf) {
                $parts[] = $this->text(
                    $x - 0.6,
                    $cm + 0.7,
                    $isMeter
                        ? Jalali::digits((string) ($cm / 100)).' متر'
                        : Jalali::digits((string) $cm),
                    ['size' => 2, 'fill' => '#57534e', 'anchor' => 'end'],
                );
            }
        }

        return implode("\n", $parts);
    }

    /** قطعه‌های چیده‌شده با برچسب و راستای پارچه. */
    protected function plan(array $placements, bool $showLabels): string
    {
        $parts = [];

        foreach ($placements as $placement) {
            $style = static::LAYER_STYLES[$placement['layer'] ?? 'outer'] ?? static::LAYER_STYLES['outer'];
            $transform = (string) ($placement['transform'] ?? '');
            $path = (string) ($placement['path'] ?? '');

            $shape = $path !== ''
                ? sprintf(
                    '<path d="%s" fill="%s" fill-opacity="0.75" stroke="%s" stroke-width="0.3" stroke-linejoin="round" />',
                    $this->attr($path),
                    $style['fill'],
                    $style['stroke'],
                )
                : sprintf(
                    '<rect x="0" y="0" width="%s" height="%s" fill="%s" fill-opacity="0.6" stroke="%s" stroke-width="0.3" />',
                    $this->num($placement['width'] ?? 0),
                    $this->num($placement['height'] ?? 0),
                    $style['fill'],
                    $style['stroke'],
                );

            $inner = [$shape];

            if (! empty($placement['sew_path'])) {
                $inner[] = sprintf(
                    '<path d="%s" fill="none" stroke="%s" stroke-width="0.18" stroke-dasharray="1.4 1" opacity="0.8" />',
                    $this->attr((string) $placement['sew_path']),
                    $style['stroke'],
                );
            }

            if (! empty($placement['grainline'])) {
                $inner[] = $this->grainArrow($placement['grainline'], $style['stroke']);
            }

            $parts[] = $transform !== ''
                ? sprintf('<g transform="%s">%s</g>', $this->attr($transform), implode('', $inner))
                : sprintf(
                    '<g transform="translate(%s %s)">%s</g>',
                    $this->num($placement['x'] ?? 0),
                    $this->num($placement['y'] ?? 0),
                    implode('', $inner),
                );

            if ($showLabels) {
                $parts[] = $this->label($placement, $style['stroke']);
            }
        }

        return implode("\n", $parts);
    }

    /** پیکان راستای پارچه در مختصات خود قطعه. */
    protected function grainArrow(array $grain, string $color): string
    {
        $x1 = (float) ($grain['x1'] ?? 0);
        $y1 = (float) ($grain['y1'] ?? 0);
        $x2 = (float) ($grain['x2'] ?? 0);
        $y2 = (float) ($grain['y2'] ?? 0);

        $length = max(0.001, sqrt((($x2 - $x1) ** 2) + (($y2 - $y1) ** 2)));
        $ux = ($x2 - $x1) / $length;
        $uy = ($y2 - $y1) / $length;
        $head = 1.4;

        $arrow = fn (float $x, float $y, int $dir) => sprintf(
            '<path d="M %s %s L %s %s M %s %s L %s %s" fill="none" stroke="%s" stroke-width="0.18" />',
            $this->num($x),
            $this->num($y),
            $this->num($x - $dir * $ux * $head + $uy * $head * 0.5),
            $this->num($y - $dir * $uy * $head - $ux * $head * 0.5),
            $this->num($x),
            $this->num($y),
            $this->num($x - $dir * $ux * $head - $uy * $head * 0.5),
            $this->num($y - $dir * $uy * $head + $ux * $head * 0.5),
            $color,
        );

        return sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="0.18" />%s%s',
            $this->num($x1),
            $this->num($y1),
            $this->num($x2),
            $this->num($y2),
            $color,
            $arrow($x2, $y2, 1),
            $arrow($x1, $y1, -1),
        );
    }

    /** نام قطعه، شماره برش و نشانه‌های تا/قرینه. */
    protected function label(array $placement, string $color): string
    {
        $x = (float) ($placement['x'] ?? 0) + (float) ($placement['width'] ?? 0) / 2;
        $y = (float) ($placement['y'] ?? 0) + (float) ($placement['height'] ?? 0) / 2;

        $badges = [];

        if (! empty($placement['on_fold'])) {
            $badges[] = 'روی تا';
        }

        if (! empty($placement['mirrored'])) {
            $badges[] = 'قرینه';
        }

        if ((int) ($placement['rotation'] ?? 0) !== 0) {
            $badges[] = 'چرخش '.Jalali::digits((string) $placement['rotation']).'°';
        }

        $lines = [
            (string) ($placement['name'] ?? $placement['code'] ?? '—'),
            sprintf(
                'برش %s از %s',
                Jalali::digits((string) ($placement['cut_index'] ?? 1)),
                Jalali::digits((string) ($placement['cut_total'] ?? 1)),
            ),
        ];

        if ($badges !== []) {
            $lines[] = implode(' • ', $badges);
        }

        $out = [];

        foreach ($lines as $index => $line) {
            $out[] = $this->text($x, $y + ($index * 3) - 1, $line, [
                'size' => $index === 0 ? 2.8 : 2.1,
                'fill' => $index === 0 ? '#1c1917' : $color,
                'anchor' => 'middle',
                'weight' => $index === 0 ? 'bold' : 'normal',
            ]);
        }

        return implode('', $out);
    }

    /** راهنمای رنگ لایه‌ها زیر نقشه. */
    protected function legend(array $placements, float $usable, float $length): string
    {
        $layers = array_values(array_unique(array_map(
            fn (array $p) => (string) ($p['layer'] ?? 'outer'),
            $placements,
        )));

        if ($layers === []) {
            return '';
        }

        $parts = [];
        $x = 0.0;
        $y = $length + 5.0;

        foreach ($layers as $layer) {
            $style = static::LAYER_STYLES[$layer] ?? static::LAYER_STYLES['outer'];
            $label = PatternPiece::LAYERS[$layer] ?? $layer;

            $parts[] = sprintf(
                '<rect x="%s" y="%s" width="3" height="2.4" fill="%s" stroke="%s" stroke-width="0.2" />',
                $this->num($x),
                $this->num($y - 1.9),
                $style['fill'],
                $style['stroke'],
            );

            $parts[] = $this->text($x + 3.8, $y, $label, ['size' => 2.2, 'fill' => '#44403c', 'anchor' => 'start']);

            $x += 4.2 + mb_strlen($label) * 1.5;

            if ($x > $usable - 8) {
                $x = 0.0;
                $y += 4.0;
            }
        }

        return implode("\n", $parts);
    }

    protected function text(float $x, float $y, string $content, array $options = []): string
    {
        return sprintf(
            '<text x="%s" y="%s" font-size="%s" fill="%s" text-anchor="%s" font-weight="%s" '
            .'font-family="Vazirmatn, Tahoma, sans-serif">%s</text>',
            $this->num($x),
            $this->num($y),
            $this->num($options['size'] ?? 2.4),
            $options['fill'] ?? '#1c1917',
            $options['anchor'] ?? 'start',
            $options['weight'] ?? 'normal',
            $this->attr($content),
        );
    }

    protected function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function num(mixed $value, int $decimals = 2): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
