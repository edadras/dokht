<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * ابزارهای «خط مدل» روی بلوک بالاتنه.
 *
 * برش افقی (امپایر، کمر افتاده، یوک، پپلوم)، پنل‌بندی کرست، بالاتنه راپ و
 * نوارهای کمکی. برش افقی مسیر را به خط شکسته تبدیل و روی خط داده‌شده به دو
 * قطعه تقسیم می‌کند؛ برچسب لبه‌ها و ساسون‌ها هم با قطعه‌ها جابه‌جا می‌شوند.
 */
trait BodiceStyleSupport
{
    /**
     * تبدیل مسیر به خط شکسته با نگه‌داشتن برچسب هر پاره‌خط.
     *
     * @return array{0: array<int, array{x: float, y: float}>, 1: array<int, string>}
     */
    protected function flattenTagged(array $outline, array $edges): array
    {
        $outline = array_values($outline);
        $count = count($outline);
        $points = [];
        $tags = [];

        for ($i = 0; $i < $count; $i++) {
            $from = ['x' => (float) $outline[$i]['x'], 'y' => (float) $outline[$i]['y']];
            $target = $outline[($i + 1) % $count];
            $tag = $edges[$i] ?? 'default';

            if (! Geometry::isCurve($target)) {
                $points[] = $from;
                $tags[] = $tag;

                continue;
            }

            $control = ['x' => (float) $target['cx'], 'y' => (float) $target['cy']];
            $end = ['x' => (float) $target['x'], 'y' => (float) $target['y']];
            $previous = $from;

            for ($s = 1; $s <= Geometry::CURVE_SEGMENTS; $s++) {
                $current = Geometry::quadraticAt($from, $control, $end, $s / Geometry::CURVE_SEGMENTS);
                $points[] = $previous;
                $tags[] = $tag;
                $previous = $current;
            }
        }

        return [$points, $tags];
    }

    /**
     * برش افقی یک قطعه روی خط y؛ خروجی [بالا، پایین].
     *
     * @param  array<string, mixed>  $piece
     * @param  array<string, mixed>  $top  کلیدهای code، name، cut، lines، meta
     * @param  array<string, mixed>  $bottom
     * @return array<int, array<string, mixed>>
     */
    protected function cutPanelAt(array $piece, float $y, array $top = [], array $bottom = []): array
    {
        [$points, $tags] = $this->flattenTagged($piece['outline'], $piece['meta']['edges'] ?? []);
        $count = count($points);

        foreach ($points as $point) {
            if (abs($point['y'] - $y) < 1e-4) {
                $y += 1e-3;
            }
        }

        $crossings = [];

        for ($j = 0; $j < $count; $j++) {
            $a = $points[$j];
            $b = $points[($j + 1) % $count];

            if ((($a['y'] - $y) * ($b['y'] - $y)) >= 0) {
                continue;
            }

            $t = ($y - $a['y']) / ($b['y'] - $a['y']);
            $crossings[] = ['seg' => $j, 'point' => ['x' => $a['x'] + (($b['x'] - $a['x']) * $t), 'y' => $y]];
        }

        if (count($crossings) !== 2) {
            return [$piece];
        }

        $chainA = $this->chainBetween($points, $tags, $crossings[0], $crossings[1], $top['cut_tag'] ?? 'waist');
        $chainB = $this->chainBetween($points, $tags, $crossings[1], $crossings[0], $bottom['cut_tag'] ?? 'waist');

        $averageA = array_sum(array_column($chainA['outline'], 'y')) / count($chainA['outline']);
        $averageB = array_sum(array_column($chainB['outline'], 'y')) / count($chainB['outline']);

        [$upper, $lower] = $averageA < $averageB ? [$chainA, $chainB] : [$chainB, $chainA];

        return [
            $this->rebuildPiece($piece, $upper, $y, $top, true),
            $this->rebuildPiece($piece, $lower, $y, $bottom, false),
        ];
    }

    /**
     * زنجیره نقطه‌ها بین دو نقطه برش.
     *
     * @return array{outline: array<int, array{x: float, y: float}>, edges: array<int, string>}
     */
    protected function chainBetween(array $points, array $tags, array $from, array $to, string $cutTag): array
    {
        $count = count($points);
        $outline = [$from['point']];
        $edges = [$tags[$from['seg']] ?? 'default'];

        $index = ($from['seg'] + 1) % $count;

        while (true) {
            $outline[] = $points[$index];

            if ($index === $to['seg']) {
                break;
            }

            $edges[] = $tags[$index] ?? 'default';
            $index = ($index + 1) % $count;
        }

        $edges[] = $tags[$to['seg']] ?? 'default';
        $outline[] = $to['point'];
        $edges[] = $cutTag;

        return ['outline' => $outline, 'edges' => $edges];
    }

    /**
     * ساخت دوباره یک قطعه از زنجیره برش‌خورده.
     *
     * @param  array<string, mixed>  $piece
     * @param  array{outline: array, edges: array}  $chain
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function rebuildPiece(array $piece, array $chain, float $y, array $o, bool $isTop): array
    {
        $outline = array_map(fn (array $p) => Geometry::point($p['x'], $p['y']), $chain['outline']);
        $edges = $chain['edges'];
        $meta = $piece['meta'] ?? [];

        $keep = fn (array $item): bool => $isTop
            ? (($item['y'] ?? ($item['center']['y'] ?? ($item['from']['y'] ?? 0))) <= $y + 0.01)
            : (($item['y'] ?? ($item['center']['y'] ?? ($item['from']['y'] ?? 0))) >= $y - 0.01);

        $darts = array_values(array_filter($piece['darts'] ?? [], $keep));
        $notches = array_values(array_filter($piece['notches'] ?? [], $keep));
        $markers = array_values(array_filter($piece['markers'] ?? [], $keep));

        foreach ($darts as $index => $dart) {
            $darts[$index]['edge'] = null;
        }

        $onFold = (bool) ($o['on_fold'] ?? ($piece['on_fold'] ?? false));
        $cut = (int) ($o['cut'] ?? ($piece['cut_quantity'] ?? 1));

        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($outline);

        $lines = [];

        foreach (($o['lines'] ?? []) as $key => $lineY) {
            if ($lineY >= $minY - 0.05 && $lineY <= $maxY + 0.05) {
                $lines[$key] = $lineY;
            }
        }

        $girth = $this->measureGirth($outline, $o['center_x'] ?? 0.0, $lines, $darts, $o['girth_deduct'] ?? []);

        $newMeta = array_merge($meta, [
            'edges' => $edges,
            'fold_edges' => $onFold ? [$this->verticalEdgeIndex($outline, $edges, $minX)] : [],
            'lengths' => $this->edgeLengths($outline, $edges),
            'girth' => $girth,
            'girth_factor' => $onFold ? 2 : $cut,
            'cut_at' => round($y, 2),
        ], $o['meta'] ?? []);

        $newMeta['armhole_length'] = $newMeta['lengths']['armhole'] ?? 0.0;
        $newMeta['neck_length'] = $newMeta['lengths']['neck'] ?? 0.0;
        $newMeta['side_seam_length'] = round($newMeta['lengths']['side'] ?? 0.0, 2);
        unset($newMeta['side_edges'], $newMeta['armhole_edge'], $newMeta['seam_edges']);

        return $this->piece([
            'code' => $o['code'] ?? (($piece['code'] ?? 'panel').($isTop ? '-top' : '-bottom')),
            'name' => $o['name'] ?? (($piece['name'] ?? 'پنل').($isTop ? ' بالا' : ' پایین')),
            'cut_quantity' => $cut,
            'on_fold' => $onFold,
            'mirror' => (bool) ($o['mirror'] ?? ! $onFold),
            'layer' => $o['layer'] ?? ($piece['layer'] ?? 'outer'),
            'outline' => $outline,
            'grainline' => $this->grainline(($minX + $maxX) * 0.4, $minY + 1.5, $maxY - 1.5),
            'darts' => $darts,
            'notches' => $notches,
            'markers' => $markers,
            'meta' => $newMeta,
        ]);
    }

    /** اندیس لبه‌ای که روی خط مرکز (کم‌ترین x) ایستاده است؛ برای fold_edges. */
    protected function verticalEdgeIndex(array $outline, array $edges, float $minX): int
    {
        $count = count($outline);

        for ($i = 0; $i < $count; $i++) {
            $a = $outline[$i];
            $b = $outline[($i + 1) % $count];

            if (abs($a['x'] - $minX) < 0.05 && abs($b['x'] - $minX) < 0.05) {
                return $i;
            }
        }

        return $count - 1;
    }

    /**
     * پنل‌بندی کرست: نیم‌تنه جلو و پشت هرکدام به چند پنل عمودی.
     *
     * درز هر دو پنل همسایه دقیقاً یک خط شکسته است، پس طول دو لبه برابر و جمع
     * پهنای پنل‌ها روی هر خط دقیقاً یک‌چهارم دور همان خط است.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<int, array<string, mixed>>
     */
    protected function corsetPanels(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $panels = max(2, min(6, (int) ($o['panels'] ?? 3)));
        $topY = $g['bust_y'] + ($front ? -1.5 : 1.0) + (float) ($o['top_extra'] ?? 0);
        $topSideY = $g['bust_y'] + 2.5;
        $bottomY = $g['side_waist_y'] + (float) ($o['length'] ?? ($g['hip_drop'] * 0.55));

        $qb = $g['quarter_bust'];
        $qw = $g['quarter_waist'];
        $qh = $g['quarter_hip'];
        $hipY = $g['hip_y'];

        $fractions = [];

        for ($i = 0; $i <= $panels; $i++) {
            $fractions[] = $i / $panels;
        }

        $lineAt = function (float $fraction) use ($qb, $qw, $qh, $g, $topY, $topSideY, $bottomY, $hipY): array {
            $keys = [
                [$g['bust_y'], $fraction * $qb],
                [$g['side_waist_y'], $fraction * $qw],
                [$hipY, $fraction * $qh],
            ];
            $top = $topY + (($topSideY - $topY) * $fraction);
            $ys = [];

            for ($i = 0; $i <= 16; $i++) {
                $ys[] = $top + (($bottomY - $top) * ($i / 16));
            }

            foreach ([$g['bust_y'], $g['side_waist_y']] as $pin) {
                if ($pin > $top + 0.05 && $pin < $bottomY - 0.05) {
                    $ys[] = $pin;
                }
            }

            $ys = array_values(array_unique(array_map(fn ($v) => round($v, 3), $ys)));
            sort($ys);

            return array_map(fn ($y) => ['x' => round($this->interpolateX($keys, $y), 3), 'y' => $y], $ys);
        };

        $lines = array_map($lineAt, $fractions);
        $pieces = [];

        for ($i = 0; $i < $panels; $i++) {
            $left = $lines[$i];
            $right = $lines[$i + 1];

            $outline = [];
            $edges = [];

            // لبه بالا از چپ به راست
            $outline[] = Geometry::point($left[0]['x'], $left[0]['y']);
            $outline[] = Geometry::point($right[0]['x'], $right[0]['y']);
            $edges[] = 'default';

            // درز راست به پایین
            foreach (array_slice($right, 1) as $point) {
                $outline[] = Geometry::point($point['x'], $point['y']);
                $edges[] = $i === $panels - 1 ? 'side' : 'default';
            }

            // لبه پایین
            $outline[] = Geometry::point($left[count($left) - 1]['x'], $left[count($left) - 1]['y']);
            $edges[] = 'hem';

            // درز چپ به بالا
            $up = array_slice(array_reverse($left), 1, count($left) - 2);

            foreach ($up as $point) {
                $outline[] = Geometry::point($point['x'], $point['y']);
                $edges[] = 'default';
            }

            $edges[] = 'default';

            $isCenter = $i === 0;
            $prefix = $front ? 'corset_front_' : 'corset_back_';
            $seams = [$prefix.($i + 1) => round($this->polylineLength($right), 2)];

            if (! $isCenter) {
                $seams[$prefix.$i] = round($this->polylineLength($left), 2);
            }

            $pieces[] = $this->finishPanel([
                'code' => ($o['prefix'] ?? '').($front ? 'front-panel-' : 'back-panel-').($i + 1),
                'name' => ($front ? 'پنل جلو ' : 'پنل پشت ').($i + 1),
                'cut' => $isCenter && ($o['center_fold'] ?? true) ? 1 : 2,
                'mirror' => ! ($isCenter && ($o['center_fold'] ?? true)),
                'layer' => $o['layer'] ?? 'outer',
            ], $outline, $edges, [
                'lines' => ['bust' => $g['bust_y'], 'waist' => $g['side_waist_y']],
                'center_x' => null,
                'on_fold' => $isCenter && ($o['center_fold'] ?? true),
                'fold_edges' => $isCenter && ($o['center_fold'] ?? true) ? [count($edges) - 1] : [],
                'grainline' => $this->grainline(($left[0]['x'] + $right[0]['x']) / 2, $left[0]['y'] + 2, $bottomY - 2),
                'markers' => [
                    $this->marker('bust', 'خط سینه', $this->interpolateAt($left, $g['bust_y']), $g['bust_y'], $this->interpolateAt($right, $g['bust_y'])),
                    $this->marker('waist', 'خط کمر', $this->interpolateAt($left, $g['side_waist_y']), $g['side_waist_y'], $this->interpolateAt($right, $g['side_waist_y'])),
                ],
                'meta' => [
                    'part' => $front ? 'front_panel' : 'back_panel',
                    'side' => $front ? 'front' : 'back',
                    'panel' => $i + 1,
                    'boning' => true,
                    'seams' => $seams,
                    'waist_y' => round($g['side_waist_y'], 2),
                    'bust_y' => round($g['bust_y'], 2),
                ],
            ]);
        }

        return $pieces;
    }

    /**
     * جلوی راپ (کراس): از سرشانه به کمر مقابل می‌رود و روی هم می‌افتد.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function wrapFrontPanel(array $g, array $o = []): array
    {
        $qb = $g['quarter_bust'];
        $qw = $g['quarter_waist'];
        $overlap = (float) ($o['overlap'] ?? 14);
        $gather = (float) ($o['gather'] ?? 4);
        $bustY = $g['bust_y'] + (float) ($o['armhole_drop'] ?? 0);
        $shoulderX = $g['shoulder_half'];
        $shoulderY = $g['shoulder_drop'];
        $neckW = $g['neck_width'] + (float) ($o['neck_width_extra'] ?? 0);
        $across = min($qb - 3.0, $g['across_chest']);
        $acrossY = $shoulderY + (($bustY - $shoulderY) * 0.62);
        $waistY = $g['side_waist_y'];
        $length = (float) ($o['length'] ?? 0);
        $bottomSideY = $waistY + $length;
        $bottomCenterY = $g['front_waist_y'] + $length;

        $sideIntake = max(0.0, $qb - $qw - $gather);
        $waistSideX = $qb - $sideIntake;

        $outline = [
            Geometry::curve($neckW, 0, $neckW + (($overlap + $neckW) * 0.18), $bottomCenterY * 0.42),
            Geometry::point($shoulderX, $shoulderY),
            Geometry::curve($across, $acrossY, $shoulderX + 0.4, $shoulderY + (($acrossY - $shoulderY) * 0.62)),
            Geometry::curve($qb, $bustY, $across + (($qb - $across) * 0.16), $bustY - (($bustY - $acrossY) * 0.06)),
            Geometry::curve($waistSideX, $bottomSideY, $qb - ($sideIntake * 0.72), $bustY + (($bottomSideY - $bustY) * 0.55)),
            Geometry::point(-$overlap, $bottomCenterY),
        ];

        $edges = ['shoulder', 'armhole', 'armhole', 'side', 'hem', 'neck'];

        $pleats = [[
            'type' => 'gather',
            'label' => 'کولیس کمر جلو',
            'edge' => 4,
            'intake' => round($gather, 2),
            'from' => Geometry::point(0, $bottomCenterY),
            'to' => Geometry::point($qb * 0.55, $bottomSideY + (($bottomCenterY - $bottomSideY) * 0.45)),
        ]];

        return $this->finishPanel([
            'code' => $o['code'] ?? 'wrap-front',
            'name' => $o['name'] ?? 'جلوی راپ',
            'cut' => 2,
            'mirror' => true,
            'layer' => $o['layer'] ?? 'outer',
        ], $outline, $edges, [
            'lines' => ['bust' => $bustY, 'waist' => $waistY],
            'center_x' => 0.0,
            'on_fold' => false,
            'grainline' => $this->grainline($qb * 0.45, max(2.0, $bustY * 0.4), $bottomSideY - 2),
            'pleats' => $pleats,
            'girth_deduct' => ['waist' => $gather],
            'notches' => [
                $this->notch($qb, $bustY, 3, 'زیر بغل', 'underarm'),
            ],
            'markers' => [
                $this->marker('bust', 'خط سینه', 0, $bustY, $qb),
                $this->marker('cf', 'خط مرکز جلو', 0, 0, 0, $bottomCenterY),
            ],
            'meta' => [
                'part' => 'front_bodice',
                'side' => 'front',
                'shape' => 'wrap',
                'wrap_overlap' => round($overlap, 2),
                'fullness' => ['waist' => round($gather, 2)],
                'bust_y' => round($bustY, 2),
                'waist_y' => round($waistY, 2),
            ],
        ]);
    }

    /**
     * نوار مستطیلی (کمربند، بند، نوار یقه، مچ‌بند).
     *
     * @return array<string, mixed>
     */
    protected function bandPiece(string $code, string $name, float $length, float $height, array $o = []): array
    {
        $length = max(2.0, $length);
        $height = max(1.0, $height);

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => (int) ($o['cut'] ?? 2),
            'on_fold' => (bool) ($o['on_fold'] ?? false),
            'mirror' => false,
            'layer' => $o['layer'] ?? 'outer',
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($length, 0),
                Geometry::point($length, $height),
                Geometry::point(0, $height),
            ],
            'grainline' => $this->grainline($length * 0.5, 0.6, $height - 0.6),
            'markers' => ($o['fold_line'] ?? false)
                ? [$this->marker('fold', 'خط تا', 0, $height / 2, $length)]
                : [],
            'meta' => array_merge([
                'part' => $o['part'] ?? 'belt',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'lengths' => ['default' => round($length, 2)],
                'girth' => [],
                'girth_factor' => 0,
                'girth_role' => 'trim',
            ], $o['meta'] ?? []),
        ]);
    }
}
