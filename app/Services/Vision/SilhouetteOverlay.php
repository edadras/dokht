<?php

namespace App\Services\Vision;

use App\Support\Jalali;

/**
 * کشیدن دوباره چیزی که اندازه گرفته شد، روی خود عکس.
 *
 * هدف این نیست که زیبا باشد؛ هدف این است که کاربر ببیند سامانه واقعاً کجا را
 * لباس دانسته و پهنای سرشانه، کمر، باسن و لبه را از کجا خوانده است. اگر مرز
 * اشتباه افتاده باشد، همین‌جا با چشم پیداست.
 */
class SilhouetteOverlay
{
    /** پله‌هایی که روی رونما خط‌کشی می‌شوند: موقعیت نسبی و برچسب. */
    protected const GUIDES = [
        ['position' => 0.10, 'top' => 'سرشانه', 'bottom' => 'کمر'],
        ['position' => 0.48, 'top' => 'کمر', 'bottom' => 'میانه'],
        ['position' => 0.67, 'top' => 'باسن', 'bottom' => 'باسن'],
        ['position' => 0.96, 'top' => 'لبه', 'bottom' => 'لبه'],
    ];

    /**
     * رونمای SVG سیلوئت.
     *
     * @param  bool  $upperIsShoulder  آیا بالای شکل سرشانه است (بالاتنه) یا کمر (پایین‌تنه)
     */
    public function render(Silhouette $mask, SilhouetteFeatures $features, bool $upperIsShoulder = true): string
    {
        $bounds = $mask->bounds();

        if ($bounds === null) {
            return '';
        }

        $width = $mask->width;
        $height = $mask->height;
        $font = max(3.5, $height * 0.036);
        $stroke = max(0.5, $height * 0.007);

        $contour = $this->simplify($mask->contour(), max(0.6, $height * 0.006));
        $path = $this->path($contour);

        $body = '<rect x="'.$this->n($bounds['min_x']).'" y="'.$this->n($bounds['min_y'])
            .'" width="'.$this->n($bounds['width']).'" height="'.$this->n($bounds['height'])
            .'" fill="none" stroke="#a8a29e" stroke-width="'.$this->n($stroke).'" stroke-dasharray="'
            .$this->n($stroke * 4).' '.$this->n($stroke * 3).'" />';

        if ($path !== '') {
            $body .= '<path d="'.$path.'" fill="rgba(220,110,60,0.22)" stroke="#c2410c" stroke-width="'
                .$this->n($stroke * 1.6).'" stroke-linejoin="round" />';
        }

        foreach (self::GUIDES as $guide) {
            $y = $bounds['min_y'] + $guide['position'] * ($bounds['height'] - 1);
            $runs = $mask->runs((int) round($y));

            if ($runs === []) {
                continue;
            }

            $from = $runs[0][0];
            $to = $runs[count($runs) - 1][1];
            $label = $upperIsShoulder ? $guide['top'] : $guide['bottom'];
            $share = $bounds['width'] > 0 ? ($to - $from + 1) / $bounds['width'] : 0;

            $body .= '<line x1="'.$this->n($from).'" y1="'.$this->n($y).'" x2="'.$this->n($to).'" y2="'.$this->n($y)
                .'" stroke="#0f766e" stroke-width="'.$this->n($stroke * 1.2).'" stroke-linecap="round" />';

            $body .= '<circle cx="'.$this->n($from).'" cy="'.$this->n($y).'" r="'.$this->n($stroke * 1.6).'" fill="#0f766e" />'
                .'<circle cx="'.$this->n($to).'" cy="'.$this->n($y).'" r="'.$this->n($stroke * 1.6).'" fill="#0f766e" />';

            $body .= '<text x="'.$this->n(($from + $to) / 2).'" y="'.$this->n($y - $font * 0.35)
                .'" font-size="'.$this->n($font).'" fill="#0f766e" text-anchor="middle" font-weight="bold">'
                .$this->escape($label.' '.Jalali::digits(number_format($share * 100, 0)).'٪').'</text>';
        }

        if ($features->splitStart !== null) {
            $y = $bounds['min_y'] + $features->splitStart * $bounds['height'];
            $body .= '<line x1="'.$this->n($bounds['min_x']).'" y1="'.$this->n($y).'" x2="'.$this->n($bounds['max_x'])
                .'" y2="'.$this->n($y).'" stroke="#be123c" stroke-width="'.$this->n($stroke).'" stroke-dasharray="'
                .$this->n($stroke * 2).' '.$this->n($stroke * 2).'" />'
                .'<text x="'.$this->n($bounds['max_x']).'" y="'.$this->n($y - $font * 0.3).'" font-size="'
                .$this->n($font).'" fill="#be123c" text-anchor="end" font-weight="bold">'
                .$this->escape('شروع شکاف پاچه').'</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$this->n($width).' '.$this->n($height)
            .'" preserveAspectRatio="xMidYMid meet" class="h-full w-full" role="img" aria-label="سیلوئت اندازه‌گیری‌شده">'
            .$body.'</svg>';
    }

    /**
     * ساده‌کردن مرز با الگوریتم داگلاس-پوکر تا مسیر SVG سبک بماند.
     *
     * @param  array<int, array{x: int, y: int}>  $points
     * @return array<int, array{x: int, y: int}>
     */
    public function simplify(array $points, float $tolerance): array
    {
        if (count($points) < 3) {
            return $points;
        }

        $keep = array_fill(0, count($points), false);
        $keep[0] = true;
        $keep[count($points) - 1] = true;
        $stack = [[0, count($points) - 1]];

        while ($stack !== []) {
            [$start, $end] = array_pop($stack);

            if ($end - $start < 2) {
                continue;
            }

            $best = 0.0;
            $index = $start;

            for ($i = $start + 1; $i < $end; $i++) {
                $distance = $this->distanceToSegment($points[$i], $points[$start], $points[$end]);

                if ($distance > $best) {
                    $best = $distance;
                    $index = $i;
                }
            }

            if ($best > $tolerance) {
                $keep[$index] = true;
                $stack[] = [$start, $index];
                $stack[] = [$index, $end];
            }
        }

        $simplified = [];

        foreach ($points as $i => $point) {
            if ($keep[$i]) {
                $simplified[] = $point;
            }
        }

        return $simplified;
    }

    /** @param  array<int, array{x: int, y: int}>  $points */
    protected function path(array $points): string
    {
        if (count($points) < 3) {
            return '';
        }

        $path = 'M '.$this->n($points[0]['x']).' '.$this->n($points[0]['y']);

        foreach (array_slice($points, 1) as $point) {
            $path .= ' L '.$this->n($point['x']).' '.$this->n($point['y']);
        }

        return $path.' Z';
    }

    protected function distanceToSegment(array $point, array $from, array $to): float
    {
        $dx = $to['x'] - $from['x'];
        $dy = $to['y'] - $from['y'];
        $length = $dx * $dx + $dy * $dy;

        if ($length <= 0) {
            return hypot($point['x'] - $from['x'], $point['y'] - $from['y']);
        }

        $t = max(0, min(1, (($point['x'] - $from['x']) * $dx + ($point['y'] - $from['y']) * $dy) / $length));

        return hypot($point['x'] - ($from['x'] + $t * $dx), $point['y'] - ($from['y'] + $t * $dy));
    }

    protected function n(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
