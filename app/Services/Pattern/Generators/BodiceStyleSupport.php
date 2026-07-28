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
                'lines' => $bottomY >= $hipY - 0.2
                    ? ['waist' => $g['side_waist_y'], 'hip' => $hipY]
                    : ['waist' => $g['side_waist_y']],
                'center_x' => null,
                'on_fold' => $isCenter && ($o['center_fold'] ?? true),
                'fold_edges' => $isCenter && ($o['center_fold'] ?? true) ? [count($edges) - 1] : [],
                'grainline' => $this->grainline(($left[0]['x'] + $right[0]['x']) / 2, $left[0]['y'] + 2, $bottomY - 2),
                'notches' => $this->panelWaistNotches($outline, $left, $right, $g['side_waist_y']),
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
     * نشانه خط کمر روی دو درز یک پنل کرست.
     *
     * دو پنل همسایه دقیقاً روی همین نقطه به هم می‌رسند، پس نشانه‌ها هنگام دوخت
     * روی هم می‌افتند و پنل‌ها نمی‌لغزند.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array<int, array<string, mixed>>
     */
    protected function panelWaistNotches(array $outline, array $left, array $right, float $waistY): array
    {
        $notches = [];

        foreach (['left' => $left, 'right' => $right] as $line) {
            $point = $this->seamNotch($line, $waistY);
            $edge = Geometry::nearestEdge($outline, ['x' => $point['x'], 'y' => $point['y']])['edge'] ?? 0;
            $notches[] = $this->notch($point['x'], $point['y'], (int) $edge, 'خط کمر روی درز پنل', 'panel_waist');
        }

        return $notches;
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
        // چین کمر نمی‌تواند از خودِ کاهش کمر بیشتر باشد، وگرنه دور کمر بزرگ‌تر از بدن درمی‌آید
        $gather = max(0.0, min((float) ($o['gather'] ?? 4), $g['quarter_bust'] - $g['quarter_waist']));
        $bustY = $g['bust_y'] + (float) ($o['armhole_drop'] ?? 0);
        $shoulderX = $g['shoulder_half'];
        $shoulderY = $g['shoulder_drop'];
        // عرض یقه هیچ‌وقت به نوک سرشانه نمی‌رسد، وگرنه خط سرشانه برمی‌گردد
        $neckW = min($g['neck_width'] + (float) ($o['neck_width_extra'] ?? 0), $shoulderX - 3);
        $across = min($qb - 3.0, $g['across_chest']);
        $acrossY = $shoulderY + (($bustY - $shoulderY) * 0.62);
        $waistY = $g['side_waist_y'];
        $length = (float) ($o['length'] ?? 0);
        $bottomSideY = $waistY + $length;
        $bottomCenterY = $g['front_waist_y'] + $length;

        $sideIntake = max(0.0, $qb - $qw - $gather);
        $waistSideX = $qb - $sideIntake;

        $outline = [
            Geometry::curve(
                $neckW,
                0,
                min($neckW + (($overlap + $neckW) * 0.18), $shoulderX - 1.5),
                $bottomCenterY * 0.42,
            ),
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
     * پنلِ پایینِ یک خط مدل افقی.
     *
     * همان قطعه‌ای که زیر خط امپایر، زیر یوک، زیر کمر افتاده یا زیر کمر پیراهن
     * دوخته می‌شود. لبه بالای آن دقیقاً هم‌اندازه لبه پایین قطعه بالایی است (به
     * علاوه چینی که خواسته شده) و از همان‌جا به پایین با فرم دلخواه ادامه می‌دهد.
     *
     * گزینه‌ها: side، top_width (نیم‌پهنای لبه بالا)، top_y (ارتفاع خط مدل روی
     * بلوک)، length، shape (fitted | straight | flare)، gather، flare، grow،
     * hip_extra، extension، hem_drop، code، name، part، layer، girth_role.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function lowerPanel(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $shape = $o['shape'] ?? 'flare';
        $grow = (float) ($o['grow'] ?? 0);
        $ext = (float) ($o['extension'] ?? 0);
        $cf = $ext;

        $qw = $g['quarter_waist'] + $grow;
        $qh = $g['quarter_hip'] + $grow + (float) ($o['hip_extra'] ?? 0);

        $topWidth = max(4.0, (float) ($o['top_width'] ?? $qw));
        $topY = (float) ($o['top_y'] ?? $g['side_waist_y']);
        $length = max(6.0, (float) ($o['length'] ?? 40));
        $gather = max(0.0, (float) ($o['gather'] ?? 0));
        $flare = (float) ($o['flare'] ?? 0);
        $hemDrop = (float) ($o['hem_drop'] ?? 0);

        $waistY = $g['side_waist_y'] - $topY;
        $hipY = $g['hip_y'] - $topY;
        $topX = $cf + $topWidth + $gather;

        $outline = [];
        $edges = [];

        if ($ext > 0) {
            $outline[] = Geometry::point(0, 0);
            $outline[] = Geometry::point($cf, 0);
            $edges[] = $o['top_tag'] ?? 'waist';
        } else {
            $outline[] = Geometry::point($cf, 0);
        }

        $outline[] = Geometry::point($topX, 0);
        $edges[] = $o['top_tag'] ?? 'waist';

        $lines = [];
        $hemX = $topX + $flare;

        if ($shape === 'fitted') {
            // کمرگیری واقعی: از خط مدل به کمر، از کمر به باسن و بعد تا پایین
            if ($waistY > 1.0 && $waistY < $length - 1.0) {
                $outline[] = Geometry::curve($cf + $qw, $waistY, $cf + $topWidth - (($topWidth - $qw) * 0.4), $waistY * 0.5);
                $edges[] = 'side';
                $lines['waist'] = $waistY;
            }

            if ($hipY > 1.0 && $hipY < $length - 1.0) {
                $from = $waistY > 1.0 && $waistY < $length - 1.0 ? $qw : $topWidth;
                $fromY = $waistY > 1.0 && $waistY < $length - 1.0 ? $waistY : 0.0;
                $outline[] = Geometry::curve($cf + $qh, $hipY, $cf + $from + (($qh - $from) * 0.34), $fromY + (($hipY - $fromY) * 0.58));
                $edges[] = 'side';
                $lines['hip'] = $hipY;
                $hemX = $cf + $qh + $flare;
            }

            $outline[] = Geometry::point($hemX, $length);
            $edges[] = 'side';
        } elseif ($shape === 'straight') {
            $hemX = max($topX, $cf + $qh) + $flare;
            $outline[] = Geometry::point($hemX, $length);
            $edges[] = 'side';
        } else {
            $outline[] = Geometry::point($hemX, $length);
            $edges[] = 'side';
        }

        $outline[] = $hemDrop > 0.3
            ? Geometry::curve(0, $length + $hemDrop, $hemX * 0.45, $length + ($hemDrop * 0.28))
            : Geometry::point(0, $length);
        $edges[] = 'hem';
        $edges[] = 'default';

        $onFold = (bool) ($o['on_fold'] ?? ($ext <= 0));
        $pleats = [];

        if ($gather > 0.5) {
            $pleats[] = [
                'type' => 'gather',
                'label' => 'چین لبه بالا',
                'edge' => $ext > 0 ? 1 : 0,
                'intake' => round($gather, 2),
                'from' => Geometry::point($cf, 0),
                'to' => Geometry::point($topX, 0),
            ];
        }

        return $this->finishPanel([
            'code' => $o['code'] ?? (($o['prefix'] ?? '').($front ? 'lower-front' : 'lower-back')),
            'name' => $o['name'] ?? ($front ? 'پنل پایین جلو' : 'پنل پایین پشت'),
            'cut' => (int) ($o['cut'] ?? ($onFold ? 1 : 2)),
            'mirror' => ! $onFold,
            'layer' => $o['layer'] ?? 'outer',
            'girth_role' => $o['girth_role'] ?? (($o['layer'] ?? 'outer') === 'lining' ? 'lining_skirt' : 'skirt'),
        ], $outline, $edges, [
            'lines' => $lines,
            'center_x' => $cf,
            'on_fold' => $onFold,
            'fold_edges' => $onFold ? [count($outline) - 1] : [],
            'grainline' => $this->grainline($cf + ($topWidth * 0.42), 2, $length - 2),
            'pleats' => $pleats,
            'girth_deduct' => $gather > 0 ? ['waist' => $gather, 'hip' => 0] : [],
            'notches' => isset($lines['waist']) ? [
                $this->notch($cf + $qw, $waistY, 1 + ($ext > 0 ? 1 : 0), 'خط کمر روی پهلو', 'waist_side'),
            ] : [],
            'markers' => array_values(array_filter([
                $this->marker($front ? 'cf' : 'cb', $front ? 'خط مرکز جلو' : 'خط مرکز پشت', $cf, 0, $cf, $length),
                isset($lines['waist']) ? $this->marker('waist', 'خط کمر', $cf, $waistY, $cf + $qw) : null,
                isset($lines['hip']) ? $this->marker('hip', 'خط باسن', $cf, $hipY, $cf + $qh) : null,
            ])),
            'meta' => array_merge([
                'part' => $o['part'] ?? ($front ? 'skirt_front' : 'skirt_back'),
                'side' => $front ? 'front' : 'back',
                'shape' => $shape,
                'style_line_y' => round($topY, 2),
                'top_width' => round($topWidth, 2),
                'waist_y' => round($waistY, 2),
                'hip_y' => round($hipY, 2),
                'fullness' => $gather > 0.5 ? ['top' => round($gather, 2)] : [],
            ], $o['meta'] ?? []),
        ]);
    }

    /**
     * دامن کلوش کامل: یک‌چهارم حلقه با کمر روی کمان داخلی.
     *
     * @return array<string, mixed>
     */
    protected function circleSkirtPanel(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $qw = $g['quarter_waist'] + (float) ($o['grow'] ?? 0);
        $fullness = max(0.25, min(1.0, (float) ($o['fullness'] ?? 1.0)));
        $length = max(15.0, (float) ($o['length'] ?? 65));

        // کمان کمر یک‌چهارم = qw ⇒ شعاع از طول کمان درمی‌آید
        $sweep = (M_PI / 2) * $fullness;
        $radius = $qw / $sweep;
        $outer = $radius + $length;
        $steps = 16;

        $outline = [];
        $edges = [];

        for ($i = 0; $i <= $steps; $i++) {
            $angle = $sweep * ($i / $steps);
            $outline[] = Geometry::point($radius * sin($angle), $radius * cos($angle));
            $edges[] = 'waist';
        }

        array_pop($edges);
        $edges[] = 'side';

        for ($i = $steps; $i >= 0; $i--) {
            $angle = $sweep * ($i / $steps);
            $outline[] = Geometry::point($outer * sin($angle), $outer * cos($angle));
            $edges[] = 'hem';
        }

        array_pop($edges);
        $edges[] = 'side';

        $waistArc = 0.0;

        for ($i = 1; $i <= $steps; $i++) {
            $waistArc += Geometry::distance(
                ['x' => $outline[$i - 1]['x'], 'y' => $outline[$i - 1]['y']],
                ['x' => $outline[$i]['x'], 'y' => $outline[$i]['y']],
            );
        }

        return $this->finishPanel([
            'code' => $o['code'] ?? (($o['prefix'] ?? '').($front ? 'skirt-front' : 'skirt-back')),
            'name' => $o['name'] ?? ($front ? 'دامن کلوش جلو' : 'دامن کلوش پشت'),
            'cut' => (int) ($o['cut'] ?? 1),
            'mirror' => false,
            'layer' => $o['layer'] ?? 'outer',
            'girth_role' => $o['girth_role'] ?? 'skirt',
        ], $outline, $edges, [
            'girth' => ['waist' => round($waistArc, 3)],
            'on_fold' => (bool) ($o['on_fold'] ?? true),
            'fold_edges' => [],
            'grainline' => $this->grainline($radius * 0.3, $radius * 0.75, $outer * 0.9),
            'markers' => [
                $this->marker('waist', 'کمان کمر', 0, $radius, $radius, 0),
            ],
            'meta' => [
                'part' => $front ? 'skirt_front' : 'skirt_back',
                'side' => $front ? 'front' : 'back',
                'skirt_type' => 'circle',
                'radius' => round($radius, 2),
                'fullness' => ['hem' => round(($outer * $sweep) - $waistArc, 2)],
            ],
        ]);
    }

    /**
     * سجاف جلو برای لباس جلوباز.
     *
     * @return array<string, mixed>
     */
    protected function frontFacingPiece(array $g, float $stand, float $bottom, array $o = []): array
    {
        $top = $g['neck_width'] + $stand + (float) ($o['neck_extra'] ?? 0);
        $width = max(5.0, (float) ($o['width'] ?? 8));

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($top, 0),
            Geometry::curve($width, $bottom, $top + 1.0, $g['front_waist_y'] * 0.55),
            Geometry::point(0, $bottom),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'front-facing',
            'name' => $o['name'] ?? 'سجاف جلو',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 3, $bottom - 3),
            'meta' => [
                'part' => 'facing',
                'edges' => ['neck', 'side', 'hem', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
                'girth_role' => 'trim',
            ],
        ]);
    }

    /**
     * سجاف یقه پشت.
     *
     * @return array<string, mixed>
     */
    protected function backNeckFacingPiece(array $g, array $o = []): array
    {
        $neckW = $g['neck_width'] + 0.3 + (float) ($o['neck_width_extra'] ?? 0);
        $depth = $g['back_neck_depth'];
        $width = max(5.0, (float) ($o['width'] ?? 7));
        $shoulder = $g['shoulder_half'];

        $outline = [
            Geometry::point(0, $depth),
            Geometry::curve($neckW, 0, $neckW * 0.34, $depth * 0.28),
            Geometry::point($shoulder, $g['shoulder_drop'] + 0.5),
            Geometry::point($shoulder - 1.5, $g['shoulder_drop'] + $width),
            Geometry::curve(0, $depth + $width, $neckW * 0.6, $depth + $width - 0.5),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'back-neck-facing',
            'name' => 'سجاف یقه پشت',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($neckW * 0.7, $depth + 1, $depth + $width - 1),
            'meta' => [
                'part' => 'facing',
                'edges' => ['neck', 'shoulder', 'default', 'default', 'default'],
                'fold_edges' => [4],
                'interfacing' => true,
                'girth_role' => 'trim',
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
