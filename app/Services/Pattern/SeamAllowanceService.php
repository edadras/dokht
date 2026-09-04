<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternPiece;

/**
 * جای دوخت.
 *
 * برای هر لبه یک «برچسب معنایی» داریم (یقه، سرشانه، حلقه، پهلو، دم، کمر) و مقدار
 * جای دوخت از نقشه seam_allowances الگو برای همان برچسب خوانده می‌شود. لبه‌هایی که
 * روی تای پارچه هستند (meta.fold_edges) جای دوخت نمی‌گیرند.
 */
class SeamAllowanceService
{
    public const TAGS = [
        'neck' => 'یقه',
        'shoulder' => 'سرشانه',
        'armhole' => 'حلقه آستین',
        'side' => 'پهلو',
        'hem' => 'دم لباس',
        'waist' => 'خط کمر',
        // منحنیِ فاقِ شلوار؛ دو پای جلو (و دو پای پشت) روی همین به هم دوخته می‌شوند
        'crotch' => 'فاق',
        // لبه بلند بند و نوار؛ جای دوختش کم است چون دولا برگردانده می‌شود
        'strap' => 'بند',
        'default' => 'سایر لبه‌ها',
    ];

    /**
     * خط برش (بیرونی) را با فاصله ثابت از خط دوخت می‌سازد.
     *
     * روش کار «آفست گوشه‌ای» است: هر لبه به اندازه allowance به سمت بیرون جابه‌جا
     * می‌شود و محل برخورد دو لبه مجاور نقطه گوشه جدید را می‌سازد. برای گوشه‌های تیز
     * طول گوشه به سه برابر جای دوخت محدود می‌شود تا نوک بی‌جهت بلند نشود. منحنی‌ها
     * پیش از محاسبه به خط شکسته تبدیل می‌شوند، پس خروجی همیشه چندضلعی ساده است.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array<int, array{x: float, y: float}>
     */
    public function offsetOutline(array $outline, float $allowance): array
    {
        $count = count(Geometry::points($outline));

        return $this->offsetOutlineEdges($outline, array_fill(0, max(1, $count), $allowance), $allowance);
    }

    /**
     * همان آفست، اما با جای دوخت جداگانه برای هر لبه.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @param  array<int, float>  $allowances  اندیس لبه ⇒ جای دوخت
     * @return array<int, array{x: float, y: float}>
     */
    public function offsetOutlineEdges(array $outline, array $allowances, float $default = 1.0): array
    {
        $source = array_values($outline);
        $sourceCount = count($source);

        if ($sourceCount < 3) {
            return Geometry::points($outline);
        }

        // منحنی‌ها به خط شکسته تبدیل می‌شوند و جای دوخت هر لبه به پاره‌خط‌هایش می‌رسد
        $points = [];
        $segmentAllowance = [];

        for ($i = 0; $i < $sourceCount; $i++) {
            $from = $source[$i];
            $to = $source[($i + 1) % $sourceCount];
            $edgeAllowance = (float) ($allowances[$i] ?? $default);
            $flat = Geometry::flatten([['x' => (float) $from['x'], 'y' => (float) $from['y']], $to]);
            array_pop($flat); // نقطه پایانی هر لبه، نقطه شروع لبه بعدی است

            foreach ($flat as $point) {
                $points[] = $point;
                $segmentAllowance[] = $edgeAllowance;
            }
        }

        $count = count($points);

        if ($count < 3) {
            return $points;
        }

        $outward = Geometry::signedArea($points) > 0 ? 1 : -1;
        $lines = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];
            $dx = $b['x'] - $a['x'];
            $dy = $b['y'] - $a['y'];
            $length = sqrt(($dx * $dx) + ($dy * $dy));

            if ($length < 1e-9) {
                $lines[] = null;

                continue;
            }

            $normal = [
                'x' => ($dy / $length) * $outward,
                'y' => (-$dx / $length) * $outward,
            ];

            $allowance = $segmentAllowance[$i] ?? $default;

            $lines[] = [
                'a' => ['x' => $a['x'] + ($normal['x'] * $allowance), 'y' => $a['y'] + ($normal['y'] * $allowance)],
                'b' => ['x' => $b['x'] + ($normal['x'] * $allowance), 'y' => $b['y'] + ($normal['y'] * $allowance)],
                'normal' => $normal,
                'allowance' => $allowance,
            ];
        }

        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $previous = $lines[($i - 1 + $count) % $count];
            $current = $lines[$i];

            if ($previous === null && $current === null) {
                $result[] = $points[$i];

                continue;
            }

            if ($previous === null) {
                $result[] = ['x' => round($current['a']['x'], 3), 'y' => round($current['a']['y'], 3)];

                continue;
            }

            if ($current === null) {
                $result[] = ['x' => round($previous['b']['x'], 3), 'y' => round($previous['b']['y'], 3)];

                continue;
            }

            $intersection = $this->intersect($previous['a'], $previous['b'], $current['a'], $current['b']);
            $cap = max($previous['allowance'], $current['allowance']) * 3;

            if ($intersection === null || Geometry::distance($points[$i], $intersection) > $cap) {
                // گوشه تیز یا لبه‌های موازی: میانگین دو نقطه آفست‌شده، محدود به سقف گوشه
                $bisector = [
                    'x' => ($previous['normal']['x'] + $current['normal']['x']),
                    'y' => ($previous['normal']['y'] + $current['normal']['y']),
                ];
                $norm = sqrt(($bisector['x'] ** 2) + ($bisector['y'] ** 2));
                $allowance = max($previous['allowance'], $current['allowance']);

                $intersection = $norm < 1e-9
                    ? ['x' => $previous['b']['x'], 'y' => $previous['b']['y']]
                    : [
                        'x' => $points[$i]['x'] + (($bisector['x'] / $norm) * min($cap, $allowance * 1.4)),
                        'y' => $points[$i]['y'] + (($bisector['y'] / $norm) * min($cap, $allowance * 1.4)),
                    ];
            }

            $result[] = ['x' => round($intersection['x'], 3), 'y' => round($intersection['y'], 3)];
        }

        return $result;
    }

    /**
     * برخورد دو خط (نه پاره‌خط)؛ برای خطوط موازی null برمی‌گرداند.
     *
     * @return array{x: float, y: float}|null
     */
    protected function intersect(array $a1, array $a2, array $b1, array $b2): ?array
    {
        $d1x = $a2['x'] - $a1['x'];
        $d1y = $a2['y'] - $a1['y'];
        $d2x = $b2['x'] - $b1['x'];
        $d2y = $b2['y'] - $b1['y'];

        $denominator = ($d1x * $d2y) - ($d1y * $d2x);

        if (abs($denominator) < 1e-9) {
            return null;
        }

        $t = ((($b1['x'] - $a1['x']) * $d2y) - (($b1['y'] - $a1['y']) * $d2x)) / $denominator;

        return [
            'x' => $a1['x'] + ($d1x * $t),
            'y' => $a1['y'] + ($d1y * $t),
        ];
    }

    /**
     * جای دوخت هر لبه هر قطعه را از نقشه الگو پر می‌کند.
     */
    public function apply(Pattern $pattern): void
    {
        $map = $pattern->seam_allowances ?? [];
        $default = (float) ($map['default'] ?? 1.0);

        foreach ($pattern->pieces as $piece) {
            $allowances = ['default' => $default];

            foreach ($this->edgeTags($piece) as $index => $tag) {
                $allowances[$index] = round((float) ($map[$tag] ?? $default), 2);
            }

            foreach ($this->foldEdges($piece) as $index) {
                $allowances[$index] = 0.0; // لبه روی تای پارچه بریده نمی‌شود
            }

            $piece->update(['edge_allowances' => $allowances]);
        }

        $pattern->unsetRelation('pieces');
    }

    /**
     * اندیس لبه ⇒ برچسب معنایی.
     *
     * اگر تولیدکننده الگو برچسب‌ها را در meta.edges گذاشته باشد همان استفاده می‌شود؛
     * وگرنه از روی جای هندسی لبه حدس زده می‌شود تا قطعه‌های دستی هم پوشش داده شوند.
     *
     * @return array<int, string>
     */
    public function edgeTags(PatternPiece $piece): array
    {
        $points = $piece->points();
        $count = count($points);

        if ($count < 2) {
            return [];
        }

        $declared = $piece->meta['edges'] ?? null;

        if (is_array($declared) && $declared !== []) {
            $tags = [];

            for ($i = 0; $i < $count; $i++) {
                $tag = $declared[$i] ?? 'default';
                $tags[$i] = isset(static::TAGS[$tag]) ? $tag : 'default';
            }

            return $tags;
        }

        [$minX, $minY, , $maxY] = $piece->bounds();
        $tags = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];
            $midX = ($a['x'] + $b['x']) / 2;
            $midY = ($a['y'] + $b['y']) / 2;
            $isHorizontal = abs($b['y'] - $a['y']) < abs($b['x'] - $a['x']);

            $tags[$i] = match (true) {
                $isHorizontal && $midY >= $maxY - 0.75 => 'hem',
                $isHorizontal && $midY <= $minY + 0.75 => 'waist',
                ! $isHorizontal && $midX <= $minX + 0.75 => 'default',
                default => 'side',
            };
        }

        return $tags;
    }

    /**
     * لبه‌هایی که روی تای پارچه قرار می‌گیرند و جای دوخت نمی‌خواهند.
     *
     * @return array<int, int>
     */
    public function foldEdges(PatternPiece $piece): array
    {
        $edges = $piece->meta['fold_edges'] ?? [];

        if (! is_array($edges)) {
            return [];
        }

        return array_values(array_map('intval', $edges));
    }

    /**
     * جای دوخت لبه‌های یک قطعه که هنوز ذخیره نشده است (آرایه خام).
     *
     * @param  array<string, mixed>  $piece
     * @param  array<string, float>  $map  نقشه برچسب ⇒ جای دوخت
     * @return array<array-key, float>
     */
    public function allowancesFor(array $piece, array $map): array
    {
        $default = (float) ($map['default'] ?? 1.0);
        $count = count($piece['outline'] ?? []);
        $tags = $piece['meta']['edges'] ?? [];
        $allowances = ['default' => $default];

        for ($i = 0; $i < $count; $i++) {
            $tag = $tags[$i] ?? 'default';
            $tag = isset(static::TAGS[$tag]) ? $tag : 'default';
            $allowances[$i] = round((float) ($map[$tag] ?? $default), 2);
        }

        foreach ($piece['meta']['fold_edges'] ?? [] as $index) {
            $allowances[(int) $index] = 0.0;
        }

        return $allowances;
    }

    /**
     * خط برش یک قطعه با جای دوخت هر لبه.
     *
     * @return array<int, array{x: float, y: float}>
     */
    public function cuttingLine(PatternPiece $piece, float $default = 1.0): array
    {
        $outline = $piece->outline ?? [];
        $count = count($outline);
        $allowances = [];

        for ($i = 0; $i < $count; $i++) {
            $allowances[$i] = $piece->allowanceFor($i, $default);
        }

        return $this->offsetOutlineEdges($outline, $allowances, $default);
    }

    /**
     * خلاصه جای دوخت‌ها برای نمایش در جدول.
     *
     * @return array<int, array{tag: string, label: string, value: float, edges: array<int, int>}>
     */
    public function summary(Pattern $pattern): array
    {
        $map = $pattern->seam_allowances ?? [];
        $default = (float) ($map['default'] ?? 1.0);
        $rows = [];

        foreach ($pattern->pieces as $piece) {
            foreach ($this->edgeTags($piece) as $index => $tag) {
                $rows[$tag]['tag'] = $tag;
                $rows[$tag]['label'] = static::TAGS[$tag] ?? $tag;
                $rows[$tag]['value'] = round((float) ($map[$tag] ?? $default), 2);
                $rows[$tag]['count'] = ($rows[$tag]['count'] ?? 0) + 1;
            }
        }

        return array_values($rows);
    }
}
