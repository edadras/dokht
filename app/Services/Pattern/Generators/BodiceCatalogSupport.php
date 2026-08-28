<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;

/**
 * موتور مشترک درفت بلوک‌های بالاتنه کاتالوگ.
 *
 * قرارداد هندسه همان قرارداد سراسری است (سانتی‌متر، x به راست، y به پایین، مبدأ
 * گوشه بالا-چپ هر قطعه) با سه تفاوت که برای «دوختنی بودن» لازم است:
 *
 *   ۱. خط کمر روی درز پهلو برای جلو و پشت یکی است؛ افت جلو روی لبه پایین دیده
 *      می‌شود نه روی درز. به این ترتیب درز پهلوی جلو و پشت دقیقاً هم‌اندازه است.
 *   ۲. پهنای یک‌چهارم دقیقاً (دور بدن + آزادی) ÷ ۴ است؛ حتی با آزادی منفی پارچه کشی.
 *   ۳. هر پنل «دور تمام‌شده» خودش را در meta.girth ثبت می‌کند (اندازه‌گیری‌شده از
 *      روی خود مسیر، نه عدد اعلامی) تا جمع دور لباس آزمون‌پذیر باشد.
 *
 * جمع دور یک لباس = مجموع meta.girth[line] × meta.girth_factor روی همه پنل‌های پوسته.
 */
trait BodiceCatalogSupport
{
    /** برچسب لبه‌های مجاز روی meta.edges. */
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'default'];

    /**
     * اندازه‌های بلوک، اصلاح‌شده تا دور تمام‌شده دقیقاً «دور بدن + آزادی» باشد.
     *
     * @return array<string, float>
     */
    protected function blockMetrics(array $m, array $ease, array $params): array
    {
        $g = $this->bodiceMetrics($m, $ease, $params);

        $g['quarter_bust'] = round($g['bust'] / 4, 3);
        $g['quarter_waist'] = round($g['waist'] / 4, 3);
        $g['quarter_hip'] = round($g['hip'] / 4, 3);

        // سرشانه هیچ‌وقت از پهنای یک‌چهارم بیرون نمی‌زند (مهم برای بلوک کشی با آزادی منفی)
        $body = $this->m($m, 'bust', 92);
        $g['body_bust'] = round($body, 3);
        $g['shoulder_half'] = round(min($g['shoulder_half'], $g['quarter_bust'] - 1.6), 3);

        // پهنای جلو و پشت سینه؛ پهنای حلقه آستین از همین‌جا درمی‌آید و باید ≈ یک‌هشتم دور سینه بماند
        $g['across_chest'] = round(max(6.0, min($g['quarter_bust'] - 3.2, ($body / 8) + 4.6)), 3);
        // پشتِ گرد پهنای بیشتری لازم دارد؛ همان اصلاحی که در bodiceMetrics روی
        // پهنای پشت نشسته بود اینجا هم نگه داشته می‌شود.
        $g['across_back'] = round(
            max(6.5, min($g['quarter_bust'] - 2.6, ($body / 8) + 5.4)) + ((float) ($g['back_curve'] ?? 0) * 0.5),
            3,
        );

        $intake = max(0.0, $g['quarter_bust'] - $g['quarter_waist']);
        $share = min(0.9, max(0.0, (float) $this->param($params, 'waist_dart_share', 0.6)));
        $g['waist_intake'] = round($intake, 3);
        $g['dart_share'] = round($share, 3);
        $g['dart_intake'] = round($intake * $share, 3);
        $g['side_intake'] = round($intake - ($intake * $share), 3);

        $g['side_waist_y'] = round((float) ($g['side_waist_base'] ?? min($g['front_waist_y'], $g['back_waist_y'])), 3);
        $g['front_drop'] = round($g['front_waist_y'] - $g['side_waist_y'], 3);
        $g['back_drop'] = round($g['back_waist_y'] - $g['side_waist_y'], 3);
        $g['hip_y'] = round($g['side_waist_y'] + $g['hip_drop'], 3);

        $g['bust_apex_y'] = round(max($g['bust_y'] + 2.5, min($g['bust_apex_y'], $g['side_waist_y'] - 4)), 3);
        $g['bust_apex_x'] = round(max(4.0, min($g['bust_apex_x'], $g['quarter_bust'] - 3)), 3);
        $g['under_bust_y'] = round(($g['bust_apex_y'] + $g['side_waist_y']) / 2, 3);
        $g['bust_dart_intake'] = round(min($g['bust_dart_intake'], ($g['side_waist_y'] - $g['bust_y']) * 0.45), 3);

        return $g;
    }

    /**
     * پارامترهای مشترک همه بلوک‌های بالاتنه؛ پیش‌فرض هرکدام قابل بازنویسی است.
     *
     * @param  array<string, float>  $defaults
     * @param  array<int, string>  $only
     * @return array<string, array<string, mixed>>
     */
    protected function baseSchema(array $defaults = [], array $only = []): array
    {
        $schema = [
            'shoulder_slope' => [
                'label' => 'شیب سرشانه', 'min' => 2, 'max' => 8, 'step' => 0.5, 'default' => 4.5, 'unit' => 'سانتی‌متر',
                'hint' => 'هرچه بیشتر باشد سرشانه افتاده‌تر می‌شود.',
            ],
            'neck_width_extra' => [
                'label' => 'اضافه عرض یقه', 'min' => -2, 'max' => 10, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'front_neck_depth_extra' => [
                'label' => 'گودی بیشتر یقه جلو', 'min' => -2, 'max' => 25, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'back_neck_depth' => [
                'label' => 'گودی یقه پشت', 'min' => 1, 'max' => 14, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'armhole_depth_extra' => [
                'label' => 'گودی بیشتر حلقه آستین', 'min' => -2, 'max' => 10, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'bodice_length_extra' => [
                'label' => 'بلندی بیشتر بالاتنه', 'min' => -6, 'max' => 20, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'waist_dart_share' => [
                'label' => 'سهم ساسون از کاهش کمر', 'min' => 0, 'max' => 0.9, 'step' => 0.05, 'default' => 0.6,
                'hint' => 'باقی کاهش کمر روی درز پهلو گرفته می‌شود.',
            ],
        ];

        if ($only !== []) {
            $schema = array_intersect_key($schema, array_flip($only));
        }

        foreach ($defaults as $key => $value) {
            if (isset($schema[$key])) {
                $schema[$key]['default'] = $value;
            }
        }

        return $schema;
    }

    /**
     * یک پنل کامل بالاتنه (جلو یا پشت).
     *
     * گزینه‌ها: side، shape، length (بلندی از خط کمر روی پهلو)، extension،
     * neck_width_extra، neck_depth_extra، shoulder_extra، shoulder_slope_extra،
     * armhole_drop، hem_flare، hip_extra، waist_dart، bust_dart، grow، on_fold،
     * cut، mirror، code، name، part، layer، bottom_tag، girth_role.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function bodyPanel(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $shape = $o['shape'] ?? 'waist';
        $grow = (float) ($o['grow'] ?? 0);
        $ext = (float) ($o['extension'] ?? 0);
        $cf = $ext;

        $qb = $g['quarter_bust'] + $grow;
        $qw = $g['quarter_waist'] + $grow;
        $qh = $g['quarter_hip'] + $grow + (float) ($o['hip_extra'] ?? 0);

        $bustY = $g['bust_y'] + (float) ($o['armhole_drop'] ?? 0);
        $neckW = $g['neck_width'] + (float) ($o['neck_width_extra'] ?? 0) + ($front ? 0.0 : 0.3);
        $neckD = ($front ? $g['front_neck_depth'] : $g['back_neck_depth']) + (float) ($o['neck_depth_extra'] ?? 0);
        $shoulderX = min($g['shoulder_half'] + (float) ($o['shoulder_extra'] ?? 0), $qb - 0.6);
        $shoulderY = $g['shoulder_drop'] + ($front ? 0.0 : 0.5) + (float) ($o['shoulder_slope_extra'] ?? 0);
        $across = min(
            $qb - 3.0,
            ($front ? $g['across_chest'] : $g['across_back']) + ($grow * 0.5) + (float) ($o['across_extra'] ?? 0),
        );

        $bustDart = ($front && ($o['bust_dart'] ?? false)) ? $g['bust_dart_intake'] : 0.0;
        $sideWaistY = $g['side_waist_y'] + $bustDart;
        $centerWaistY = ($front ? $g['front_waist_y'] : $g['back_waist_y']) + $bustDart;

        [$dartIntake, $sideIntake] = $this->waistSplit($g, $qb, $qw, (bool) ($o['waist_dart'] ?? true), $shape);

        $length = (float) ($o['length'] ?? 0);
        $flare = (float) ($o['hem_flare'] ?? 0);
        $sideBottomY = $sideWaistY + $length;
        $centerBottomY = $centerWaistY + $length;
        $hipY = $sideWaistY + $g['hip_drop'];

        $outline = [];
        $edges = [];

        if ($ext > 0) {
            $outline[] = Geometry::point(0, $neckD);
            $outline[] = Geometry::point($cf, $neckD);
            $edges[] = 'default';
        } else {
            $outline[] = Geometry::point($cf, $neckD);
        }

        // یقه
        $outline[] = Geometry::curve(
            $cf + $neckW,
            0,
            $cf + ($neckW * ($front ? 0.10 : 0.34)),
            $neckD * ($front ? 0.10 : 0.28),
        );
        $edges[] = 'neck';

        // سرشانه
        $outline[] = Geometry::point($cf + $shoulderX, $shoulderY);
        $edges[] = 'shoulder';

        // حلقه آستین: از نوک سرشانه تو می‌آید تا خط پهنای سینه و بعد گود می‌شود تا زیر بغل
        $acrossY = $shoulderY + (($bustY - $shoulderY) * 0.62);
        $outline[] = Geometry::curve($cf + $across, $acrossY, $cf + $shoulderX + 0.4, $shoulderY + (($acrossY - $shoulderY) * 0.62));
        $edges[] = 'armhole';
        $outline[] = Geometry::curve($cf + $qb, $bustY, $cf + $across + (($qb - $across) * 0.16), $bustY - (($bustY - $acrossY) * 0.06));
        $edges[] = 'armhole';

        $armholeEdges = [count($edges) - 2, count($edges) - 1];

        // درز پهلو
        [$sideOutline, $sideTags, $sideBottomX] = $this->sideEdge([
            'cf' => $cf, 'qb' => $qb, 'qh' => $qh,
            'bust_y' => $bustY, 'waist_y' => $sideWaistY, 'hip_y' => $hipY, 'bottom_y' => $sideBottomY,
            'side_intake' => $sideIntake, 'shape' => $shape, 'flare' => $flare, 'hip_drop' => $g['hip_drop'],
        ]);

        foreach ($sideOutline as $index => $point) {
            $outline[] = $point;
            $edges[] = $sideTags[$index];
        }

        $sideEdgeIndexes = range(count($edges) - count($sideTags), count($edges) - 1);

        // لبه پایین (کمر یا دم لباس) با افت جلو
        $dip = $centerBottomY - $sideBottomY;
        $outline[] = abs($dip) > 0.4
            ? Geometry::curve(0, $centerBottomY, $sideBottomX * 0.42, $sideBottomY + ($dip * 0.22))
            : Geometry::point(0, $centerBottomY);
        $edges[] = $o['bottom_tag'] ?? ($shape === 'waist' ? 'waist' : 'hem');
        $bottomEdge = count($edges) - 1;

        $edges[] = 'default'; // خط مرکز جلو/پشت
        $closingEdge = count($outline) - 1;

        // قانون ۲: ساسون سینه پهنای خودش را از درز پهلو می‌خورد. بالا خط کمرِ جلو
        // به اندازه دهانه ساسون پایین آورده شده، ولی درز پهلو منحنی است و طولش به
        // همان اندازه بلند نمی‌شود؛ روی بدنی با سینه بسیار درشت این اختلاف تا نیم
        // سانتی‌متر می‌رسد. پس درز را دقیقاً به «درز پشت + دهانه ساسون» می‌رسانیم.
        if ($bustDart > 0.01) {
            [$plainSide] = $this->sideEdge([
                'cf' => $cf, 'qb' => $qb, 'qh' => $qh,
                'bust_y' => $bustY, 'waist_y' => $g['side_waist_y'], 'hip_y' => $g['side_waist_y'] + $g['hip_drop'],
                'bottom_y' => $g['side_waist_y'] + $length,
                'side_intake' => $sideIntake, 'shape' => $shape, 'flare' => $flare, 'hip_drop' => $g['hip_drop'],
            ]);

            $target = Geometry::perimeter(array_merge([Geometry::point($cf + $qb, $bustY)], $plainSide))
                - Geometry::distance(
                    ['x' => (float) end($plainSide)['x'], 'y' => (float) end($plainSide)['y']],
                    ['x' => $cf + $qb, 'y' => $bustY],
                );

            $outline = $this->stretchSideSeamTo($outline, $edges, $sideEdgeIndexes, $target + $bustDart);
        }

        $onFold = (bool) ($o['on_fold'] ?? ($ext <= 0));

        // ساسون‌ها
        $darts = [];
        $dartX = $front ? $cf + $g['bust_apex_x'] : $cf + ($qb * 0.46);

        if ($dartIntake > 0.6) {
            $apexY = $front ? $g['bust_apex_y'] : $bustY + 4;

            if ($shape === 'waist') {
                // دو پای ساسون کمر روی خودِ لبه کمر می‌نشینند، حتی وقتی آن لبه منحنی است
                $left = $this->pointOnEdgeAtX($outline, $bottomEdge, $dartX - ($dartIntake / 2));
                $right = $this->pointOnEdgeAtX($outline, $bottomEdge, $dartX + ($dartIntake / 2));

                $darts[] = $this->dart(
                    'waist',
                    'ساسون کمر',
                    $bottomEdge,
                    ($left['x'] + $right['x']) / 2,
                    ($left['y'] + $right['y']) / 2,
                    $dartIntake,
                    $dartX,
                    $apexY,
                    'x',
                    ['legs' => [Geometry::point($left['x'], $left['y']), Geometry::point($right['x'], $right['y'])]],
                );
            } else {
                $darts[] = $this->dart('waist', 'ساسون کمر', null, $dartX, $sideWaistY, $dartIntake, $dartX, $apexY, 'x', [
                    'apex_lower' => Geometry::point($dartX, min($centerBottomY - 2, $hipY - 1)),
                ]);
            }
        }

        if ($bustDart > 0.8) {
            // دو پای ساسون سینه باید دقیقاً روی منحنی درز پهلو بنشینند، وگرنه
            // هنگام بستن ساسون، درز پهلو کج می‌شود
            $legY = $bustY + (($sideWaistY - $bustY) * 0.42);
            $edge = $sideEdgeIndexes[0];
            $upper = $this->pointOnEdgeAtY($outline, $edge, $legY - ($bustDart / 2));
            $lower = $this->pointOnEdgeAtY($outline, $edge, $legY + ($bustDart / 2));

            $darts[] = $this->dart(
                'bust',
                'ساسون سینه روی پهلو',
                $edge,
                ($upper['x'] + $lower['x']) / 2,
                ($upper['y'] + $lower['y']) / 2,
                $bustDart,
                $cf + $g['bust_apex_x'],
                $g['bust_apex_y'],
                'y',
                ['legs' => [Geometry::point($upper['x'], $upper['y']), Geometry::point($lower['x'], $lower['y'])]],
            );
        }

        // خط‌های نشانه
        $markers = [
            $this->marker('bust', 'خط سینه', $cf, $bustY, $cf + $qb),
            $this->marker($front ? 'cf' : 'cb', $front ? 'خط مرکز جلو' : 'خط مرکز پشت', $cf, $neckD, $cf, $centerBottomY),
        ];

        if ($length > 1.5) {
            $markers[] = $this->marker('waist', 'خط کمر', $cf, $sideWaistY, $cf + $qb - $sideIntake, $sideWaistY);
        }

        // پهنای خط باسن از خود مسیر خوانده می‌شود؛ در فرم راسته لبه پهلو از باسن بازتر است
        if ($sideBottomY > $hipY + 1) {
            $markers[] = $this->marker('hip', 'خط باسن', $cf, $hipY, $cf + max(0.0, $this->panelWidthAt(['outline' => $outline], $hipY) - $cf));
        }

        // نشانه‌های جفت‌شدن
        $shoulderMid = Geometry::pointOnEdge($outline, (int) array_search('shoulder', $edges, true), 0.5);
        $armholeNotch = Geometry::pointOnEdge($outline, $armholeEdges[1], $front ? 0.35 : 0.30);

        $notches = [
            $this->notch($shoulderMid['x'], $shoulderMid['y'], (int) array_search('shoulder', $edges, true), 'نشانه سرشانه', 'shoulder'),
            $this->notch($armholeNotch['x'], $armholeNotch['y'], $armholeEdges[1], $front ? 'نشانه جلوی حلقه' : 'نشانه پشت حلقه', $front ? 'armhole_front' : 'armhole_back'),
        ];

        if ($length > 1.5 && ! in_array($shape, ['straight', 'trapeze'], true)) {
            $notches[] = $this->notch($cf + $qb - $sideIntake, $sideWaistY, $sideEdgeIndexes[0], 'نشانه کمر روی پهلو', 'waist_side');
        } elseif ($length > 1.5 && $shape === 'trapeze') {
            /*
             * در فرمِ ذوزنقه‌ای، پهلو یک خطِ صافِ مورب از زیر بغل تا لبهٔ پایین است
             * و هیچ گودیِ کمری ندارد. نشانه باید *روی همان خط* بنشیند، نه روی
             * جایی که کمرگیری می‌بود: با فرمولِ کمرگیری، نشانه دو و نیم سانتی‌متر
             * بیرونِ مسیر می‌افتاد و بازرسیِ کاتالوگ همان را گرفت.
             */
            $spot = Geometry::pointOnEdge($outline, $sideEdgeIndexes[0], max(0.0, min(1.0,
                ($sideWaistY - $bustY) / max(0.1, $sideBottomY - $bustY),
            )));

            $notches[] = $this->notch(
                (float) $spot['x'],
                (float) $spot['y'],
                $sideEdgeIndexes[0],
                'نشانه کمر روی پهلو',
                'waist_side',
            );
        }

        $lines = ['bust' => $bustY];

        if (in_array($shape, ['waist', 'fitted', 'flare'], true)) {
            $lines['waist'] = $sideWaistY;
        }

        if ($shape === 'fitted' && $sideBottomY >= $hipY - 0.2) {
            $lines['hip'] = $hipY;
        }

        return $this->finishPanel($o, $outline, $edges, [
            'lines' => $lines,
            'center_x' => $cf,
            'darts' => $darts,
            'notches' => $notches,
            'markers' => $markers,
            'on_fold' => $onFold,
            'fold_edges' => $onFold ? [$closingEdge] : [],
            'grainline' => $this->grainline($cf + ($qb * 0.42), max(1.5, $bustY * 0.4), $centerBottomY - 2),
            'meta' => array_merge([
                'part' => $o['part'] ?? ($front ? 'front_bodice' : 'back_bodice'),
                'side' => $front ? 'front' : 'back',
                'shape' => $shape,
                'armhole_edge' => $armholeEdges[1],
                'side_edges' => $sideEdgeIndexes,
                'bust_y' => round($bustY, 2),
                'waist_y' => round($sideWaistY, 2),
                'hip_y' => round($hipY, 2),
                'bust_dart_intake' => round($bustDart, 2),
                'waist_dart_intake' => round($dartIntake, 2),
            ], $o['meta'] ?? []),
        ]);
    }

    /**
     * سهم ساسون و درز پهلو از کاهش کمر.
     *
     * @return array{0: float, 1: float}
     */
    protected function waistSplit(array $g, float $qb, float $qw, bool $wantsDart, string $shape): array
    {
        if (! in_array($shape, ['waist', 'fitted', 'flare'], true)) {
            return [0.0, 0.0];
        }

        $total = max(0.0, $qb - $qw);
        $share = $wantsDart ? $g['dart_share'] : 0.0;
        $dart = round($total * $share, 3);

        return [$dart, round($total - $dart, 3)];
    }

    /**
     * کشیدن درز پهلو تا طول خواسته‌شده، بدون شکستگی روی منحنی.
     *
     * سرِ زیر بغل ثابت می‌ماند و رأس‌های پایین‌تر روی امتداد خود درز پایین می‌روند.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @param  array<int, string>  $edges
     * @param  array<int, int>  $sideEdges
     * @return array<int, array<string, mixed>>
     */
    protected function stretchSideSeamTo(array $outline, array $edges, array $sideEdges, float $target): array
    {
        $piece = ['outline' => array_values($outline), 'meta' => ['edges' => $edges]];

        for ($round = 0; $round < 24; $round++) {
            $current = 0.0;

            foreach ($sideEdges as $edge) {
                $current += Geometry::edgeLength($piece['outline'], $edge);
            }

            if (abs($target - $current) <= 0.01) {
                break;
            }

            $piece = PieceOps::stretchSeam($piece, $sideEdges, $target - $current, 'start');
        }

        return array_values($piece['outline']);
    }

    /**
     * لبه درز پهلو بر پایه فرم لباس.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: float}
     */
    protected function sideEdge(array $s): array
    {
        $cf = $s['cf'];
        $waistX = $cf + $s['qb'] - $s['side_intake'];
        $hipX = $cf + max($s['qh'], $s['qb'] - $s['side_intake']);
        $bustY = $s['bust_y'];
        $waistY = $s['waist_y'];
        $hipY = $s['hip_y'];
        $bottomY = $s['bottom_y'];
        $flare = $s['flare'];

        $points = [];
        $tags = [];

        if ($s['shape'] === 'straight') {
            $points[] = Geometry::point($cf + $s['qb'] + $flare, $bottomY);
            $tags[] = 'side';

            return [$points, $tags, $cf + $s['qb'] + $flare];
        }

        if ($s['shape'] === 'trapeze') {
            $points[] = Geometry::point($cf + $s['qb'] + $flare, $bottomY);
            $tags[] = 'side';

            return [$points, $tags, $cf + $s['qb'] + $flare];
        }

        // کمرگیری
        $points[] = Geometry::curve($waistX, $waistY, $cf + $s['qb'] - ($s['side_intake'] * 0.72), $bustY + (($waistY - $bustY) * 0.55));
        $tags[] = 'side';

        if ($bottomY <= $waistY + 0.05) {
            return [$points, $tags, $waistX];
        }

        if ($s['shape'] === 'flare') {
            // لباس گشاد از کمر به بیرون باز می‌شود و روی باسن هیچ درزی ندارد؛ پس
            // پهنای آن روی خط باسن باید دست‌کم به اندازه خود باسن باشد، وگرنه روی
            // بدنی که اختلاف کمر تا باسنش زیاد است اصلاً پوشیده نمی‌شود.
            if ($bottomY > $hipY + 0.2 && $hipX > $waistX) {
                $flare = max($flare, ($hipX - $waistX) * (($bottomY - $waistY) / max(0.1, $hipY - $waistY)));
            }

            $points[] = Geometry::point($waistX + $flare, $bottomY);
            $tags[] = 'side';

            return [$points, $tags, $waistX + $flare];
        }

        // fitted: از کمر به باسن و بعد تا پایین
        if ($bottomY < $hipY - 0.2) {
            $t = ($bottomY - $waistY) / max(0.1, $hipY - $waistY);
            $x = $waistX + (($hipX - $waistX) * $t);
            $points[] = Geometry::curve($x, $bottomY, $waistX + (($x - $waistX) * 0.3), $waistY + (($bottomY - $waistY) * 0.6));
            $tags[] = 'side';

            return [$points, $tags, $x];
        }

        $points[] = Geometry::curve($hipX, $hipY, $waistX + (($hipX - $waistX) * 0.32), $waistY + (($hipY - $waistY) * 0.58));
        $tags[] = 'side';

        if ($bottomY > $hipY + 0.2) {
            $points[] = Geometry::point($hipX + $flare, $bottomY);
            $tags[] = 'side';

            return [$points, $tags, $hipX + $flare];
        }

        return [$points, $tags, $hipX];
    }

    /**
     * بستن یک پنل: اندازه‌گیری دور، طول لبه‌ها و ساخت قطعه.
     *
     * @param  array<string, mixed>  $o
     * @param  array<int, array<string, mixed>>  $outline
     * @param  array<int, string>  $edges
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    protected function finishPanel(array $o, array $outline, array $edges, array $b): array
    {
        $onFold = (bool) ($b['on_fold'] ?? false);
        $cut = (int) ($o['cut'] ?? ($onFold ? 1 : 2));
        $darts = $b['darts'] ?? [];

        $girth = $b['girth'] ?? $this->measureGirth(
            $outline,
            array_key_exists('center_x', $b) ? $b['center_x'] : 0.0,
            $b['lines'] ?? [],
            $darts,
            $b['girth_deduct'] ?? [],
        );

        $meta = array_merge([
            'edges' => $edges,
            'fold_edges' => $b['fold_edges'] ?? [],
            'lengths' => $this->edgeLengths($outline, $edges),
            'girth' => $girth,
            'girth_factor' => $onFold ? 2 : $cut,
            'girth_role' => $o['girth_role'] ?? (($o['layer'] ?? 'outer') === 'lining' ? 'lining' : 'shell'),
        ], $b['meta'] ?? []);

        $meta['armhole_length'] = $meta['lengths']['armhole'] ?? 0.0;
        $meta['neck_length'] = $meta['lengths']['neck'] ?? 0.0;
        $meta['side_seam_length'] = round(($meta['lengths']['side'] ?? 0.0) - $this->dartIntakeOnEdges($darts, $meta['side_edges'] ?? []), 2);

        return $this->piece([
            'code' => $o['code'] ?? 'panel',
            'name' => $o['name'] ?? 'پنل',
            'cut_quantity' => $cut,
            'on_fold' => $onFold,
            'mirror' => (bool) ($o['mirror'] ?? ! $onFold),
            'layer' => $o['layer'] ?? 'outer',
            'outline' => $outline,
            'grainline' => $b['grainline'] ?? null,
            'darts' => $darts,
            'notches' => $b['notches'] ?? [],
            'pleats' => $b['pleats'] ?? [],
            'markers' => $b['markers'] ?? [],
            'meta' => $meta,
        ]);
    }

    /** جمع باز شدن ساسون‌هایی که روی این لبه‌ها نشسته‌اند. */
    protected function dartIntakeOnEdges(array $darts, array $edgeIndexes): float
    {
        $total = 0.0;

        foreach ($darts as $dart) {
            if (($dart['axis'] ?? 'x') === 'y' && in_array($dart['edge'] ?? -1, $edgeIndexes, true)) {
                $total += (float) ($dart['intake'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * طول لبه‌ها به تفکیک برچسب.
     *
     * @return array<string, float>
     */
    protected function edgeLengths(array $outline, array $edges): array
    {
        $lengths = [];

        foreach ($edges as $index => $tag) {
            $lengths[$tag] = round(($lengths[$tag] ?? 0) + Geometry::edgeLength($outline, $index), 2);
        }

        return $lengths;
    }

    /**
     * دور تمام‌شده پنل روی خط‌های سینه، کمر و باسن؛ از روی خود مسیر اندازه گرفته می‌شود.
     *
     * اگر $centerX برابر null باشد، کم‌ترین x همان خط مبنا گرفته می‌شود (پنل‌های میانی
     * که لبه سمت مرکزشان خودش یک درز منحنی است).
     *
     * @param  array<string, float|null>  $lines
     * @return array<string, float>
     */
    protected function measureGirth(array $outline, ?float $centerX, array $lines, array $darts = [], array $deduct = []): array
    {
        $points = Geometry::flatten($outline);
        $girth = [];

        foreach ($lines as $key => $y) {
            if ($y === null) {
                continue;
            }

            $xs = $this->crossingsAt($points, (float) $y);

            if ($xs === []) {
                continue;
            }

            $width = max($xs) - ($centerX ?? min($xs));

            foreach ($darts as $dart) {
                if (($dart['axis'] ?? 'x') !== 'x') {
                    continue;
                }

                if (abs(((float) ($dart['center']['y'] ?? -999)) - (float) $y) > 0.9) {
                    continue;
                }

                $width -= (float) ($dart['intake'] ?? 0);
            }

            $girth[$key] = round($width - (float) ($deduct[$key] ?? 0), 3);
        }

        return $girth;
    }

    /**
     * هم‌تراز کردن نشانه‌ها و خط‌های نشانه با مسیر واقعی قطعه.
     *
     * سرآستین با «برازش ارتفاع کپ» ساخته می‌شود، پس تا لحظه آخر معلوم نیست
     * نقطه زیر بغل کجا می‌افتد. اینجا هر نشانه به نزدیک‌ترین لبه واقعی چسبانده و
     * هر خط نشانه داخل کادر قطعه بریده می‌شود تا روی کاغذ همان‌جایی بیفتد که
     * ادعا می‌کند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function alignMarks(array $piece): array
    {
        $outline = array_values($piece['outline'] ?? []);

        if (count($outline) < 3) {
            return $piece;
        }

        foreach ($piece['notches'] ?? [] as $index => $notch) {
            $near = Geometry::nearestEdge($outline, ['x' => (float) $notch['x'], 'y' => (float) $notch['y']]);
            $piece['notches'][$index]['x'] = round($near['point']['x'], 2);
            $piece['notches'][$index]['y'] = round($near['point']['y'], 2);
            $piece['notches'][$index]['edge'] = (int) $near['edge'];
        }

        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($outline);

        foreach ($piece['markers'] ?? [] as $index => $marker) {
            foreach (['from', 'to'] as $end) {
                if (! isset($marker[$end]['x'], $marker[$end]['y'])) {
                    continue;
                }

                $piece['markers'][$index][$end]['x'] = round(max($minX, min($maxX, (float) $marker[$end]['x'])), 2);
                $piece['markers'][$index][$end]['y'] = round(max($minY, min($maxY, (float) $marker[$end]['y'])), 2);
            }
        }

        if (isset($piece['grainline']['from']['x'], $piece['grainline']['to']['x'])) {
            foreach (['from', 'to'] as $end) {
                $piece['grainline'][$end]['x'] = round(max($minX, min($maxX, (float) $piece['grainline'][$end]['x'])), 2);
                $piece['grainline'][$end]['y'] = round(max($minY, min($maxY, (float) $piece['grainline'][$end]['y'])), 2);
            }
        }

        return $piece;
    }

    /**
     * نقطه‌ای روی یک لبه که ارتفاع داده‌شده را دارد.
     *
     * با نصف‌کردن پی‌درپی روی پارامتر لبه پیدا می‌شود، پس روی منحنی هم دقیق است.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array{x: float, y: float}
     */
    protected function pointOnEdgeAtY(array $outline, int $edge, float $y): array
    {
        $low = 0.0;
        $high = 1.0;
        $start = Geometry::pointOnEdge($outline, $edge, 0.0);
        $end = Geometry::pointOnEdge($outline, $edge, 1.0);
        $descending = $end['y'] >= $start['y'];

        for ($i = 0; $i < 40; $i++) {
            $mid = ($low + $high) / 2;
            $at = Geometry::pointOnEdge($outline, $edge, $mid);

            if (($at['y'] < $y) === $descending) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        $point = Geometry::pointOnEdge($outline, $edge, ($low + $high) / 2);

        return ['x' => (float) $point['x'], 'y' => (float) $point['y']];
    }

    /**
     * نقطه‌ای روی یک لبه که مختصات افقی داده‌شده را دارد.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array{x: float, y: float}
     */
    protected function pointOnEdgeAtX(array $outline, int $edge, float $x): array
    {
        $low = 0.0;
        $high = 1.0;
        $start = Geometry::pointOnEdge($outline, $edge, 0.0);
        $end = Geometry::pointOnEdge($outline, $edge, 1.0);
        $ascending = $end['x'] >= $start['x'];

        for ($i = 0; $i < 40; $i++) {
            $mid = ($low + $high) / 2;
            $at = Geometry::pointOnEdge($outline, $edge, $mid);

            if (($at['x'] < $x) === $ascending) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        $point = Geometry::pointOnEdge($outline, $edge, ($low + $high) / 2);

        return ['x' => (float) $point['x'], 'y' => (float) $point['y']];
    }

    /**
     * پهنای یک قطعه روی ارتفاع داده‌شده (از چپ‌ترین تا راست‌ترین برخورد).
     *
     * برای پیدا کردن اندازه واقعی لبه یک برش افقی به کار می‌رود تا قطعه پایینی
     * دقیقاً هم‌اندازه لبه قطعه بالایی درفت شود.
     *
     * @param  array<string, mixed>  $piece
     */
    protected function panelWidthAt(array $piece, float $y): float
    {
        $xs = $this->crossingsAt(Geometry::flatten($piece['outline'] ?? []), $y);

        return $xs === [] ? 0.0 : round(max($xs) - min($xs), 3);
    }

    /**
     * محل برخورد یک خط افقی با مسیر بسته.
     *
     * @return array<int, float>
     */
    protected function crossingsAt(array $points, float $y): array
    {
        $count = count($points);
        $xs = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];

            if (abs($a['y'] - $y) < 1e-6 && abs($b['y'] - $y) < 1e-6) {
                $xs[] = $a['x'];
                $xs[] = $b['x'];

                continue;
            }

            if ((($a['y'] - $y) * ($b['y'] - $y)) > 0) {
                continue;
            }

            if (abs($b['y'] - $a['y']) < 1e-9) {
                continue;
            }

            $t = ($y - $a['y']) / ($b['y'] - $a['y']);

            if ($t < -1e-9 || $t > 1 + 1e-9) {
                continue;
            }

            $xs[] = $a['x'] + (($b['x'] - $a['x']) * $t);
        }

        return $xs;
    }

    /**
     * خط مدل عمودی (درز پرنسسی یا درز پنل کرست).
     *
     * از روی چند نقطه کلیدی (y ⇒ x) یک خط شکسته نرم می‌سازد؛ همین خط عیناً به هر
     * دو پنل همسایه داده می‌شود تا طول دو لبه دقیقاً برابر باشد.
     *
     * @param  array<int, array{0: float, 1: float}>  $keys  [[y, x], ...] مرتب از بالا به پایین
     * @return array<int, array{x: float, y: float}>
     */
    protected function styleLine(array $keys, int $samples = 18): array
    {
        usort($keys, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $top = $keys[0][0];
        $bottom = $keys[count($keys) - 1][0];
        $line = [];

        for ($i = 0; $i <= $samples; $i++) {
            $y = $top + (($bottom - $top) * ($i / $samples));
            $line[] = ['x' => round($this->interpolateX($keys, $y), 3), 'y' => round($y, 3)];
        }

        return $line;
    }

    /** درون‌یابی نرم (هموار) بین نقطه‌های کلیدی یک خط مدل. */
    protected function interpolateX(array $keys, float $y): float
    {
        $count = count($keys);

        if ($y <= $keys[0][0]) {
            return $keys[0][1];
        }

        if ($y >= $keys[$count - 1][0]) {
            return $keys[$count - 1][1];
        }

        for ($i = 0; $i < $count - 1; $i++) {
            [$y0, $x0] = $keys[$i];
            [$y1, $x1] = $keys[$i + 1];

            if ($y > $y1 || $y1 - $y0 <= 0) {
                continue;
            }

            $t = ($y - $y0) / ($y1 - $y0);
            $smooth = $t * $t * (3 - (2 * $t));

            return $x0 + (($x1 - $x0) * $smooth);
        }

        return $keys[$count - 1][1];
    }

    /** طول یک خط شکسته باز. */
    protected function polylineLength(array $line): float
    {
        $total = 0.0;

        for ($i = 1, $count = count($line); $i < $count; $i++) {
            $total += Geometry::distance($line[$i - 1], $line[$i]);
        }

        return round($total, 3);
    }

    /**
     * برگرداندن جهت یک خط شکسته (برای لبه پنل مقابل).
     *
     * @return array<int, array{x: float, y: float}>
     */
    protected function reversePolyline(array $line): array
    {
        return array_values(array_reverse($line));
    }

    /** جابه‌جایی یک خط شکسته. */
    protected function shiftPolyline(array $line, float $dx, float $dy): array
    {
        return array_map(fn (array $p) => ['x' => round($p['x'] + $dx, 3), 'y' => round($p['y'] + $dy, 3)], $line);
    }

    /**
     * خط شکسته را با ضریب انحنا نسبت به وتر بازتنظیم می‌کند تا طولش به هدف برسد.
     *
     * همان کاری که خیاط با «راه رفتن روی درز» می‌کند: منحنی را کمی پرتر یا صاف‌تر
     * می‌گیرد تا دو درز هم‌اندازه شوند.
     */
    protected function tuneSeam(array $line, float $target): array
    {
        $count = count($line);

        if ($count < 3) {
            return $line;
        }

        $bend = function (float $k) use ($line, $count): array {
            $first = $line[0];
            $last = $line[$count - 1];
            $out = [];

            foreach ($line as $index => $point) {
                $t = $index / ($count - 1);
                $chordX = $first['x'] + (($last['x'] - $first['x']) * $t);
                $chordY = $first['y'] + (($last['y'] - $first['y']) * $t);
                $out[] = [
                    'x' => round($chordX + (($point['x'] - $chordX) * $k), 3),
                    'y' => round($chordY + (($point['y'] - $chordY) * $k), 3),
                ];
            }

            return $out;
        };

        $low = 0.0;
        $high = 6.0;

        for ($i = 0; $i < 40; $i++) {
            $mid = ($low + $high) / 2;

            if ($this->polylineLength($bend($mid)) < $target) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return $bend(($low + $high) / 2);
    }

    /**
     * دو پنل پرنسسی (میانی و پهلو) از یک بلوک.
     *
     * درز پرنسسی یک «خط نامی» دارد؛ لبه پنل میانی و لبه پنل پهلو به اندازه نصف
     * فاصله ساسون از این خط فاصله می‌گیرند: روی خط سینه فاصله صفر است (پس دور سینه
     * عوض نمی‌شود)، روی نوک سینه باز می‌شود (جای برجستگی سینه) و روی کمر بسته
     * می‌شود (همان ساسون کمر). بعد دو درز «راه برده» و هم‌اندازه می‌شوند.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<int, array<string, mixed>>
     */
    protected function princessPanels(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $origin = $o['origin'] ?? 'armhole';
        $shape = $o['shape'] ?? 'waist';
        $grow = (float) ($o['grow'] ?? 0);
        $ext = (float) ($o['extension'] ?? 0);
        $cf = $ext;

        $qb = $g['quarter_bust'] + $grow;
        $qw = $g['quarter_waist'] + $grow;
        $qh = $g['quarter_hip'] + $grow + (float) ($o['hip_extra'] ?? 0);

        $bustY = $g['bust_y'] + (float) ($o['armhole_drop'] ?? 0);
        $neckW = $g['neck_width'] + (float) ($o['neck_width_extra'] ?? 0) + ($front ? 0.0 : 0.3);
        $neckD = ($front ? $g['front_neck_depth'] : $g['back_neck_depth']) + (float) ($o['neck_depth_extra'] ?? 0);
        $shoulderX = min($g['shoulder_half'] + (float) ($o['shoulder_extra'] ?? 0), $qb - 0.6);
        $shoulderY = $g['shoulder_drop'] + ($front ? 0.0 : 0.5);
        $across = min($qb - 3.0, ($front ? $g['across_chest'] : $g['across_back']) + ($grow * 0.5));
        $acrossY = $shoulderY + (($bustY - $shoulderY) * 0.62);

        $waistY = $g['side_waist_y'];
        $centerWaistY = $front ? $g['front_waist_y'] : $g['back_waist_y'];
        $hipY = $g['hip_y'];
        $length = (float) ($o['length'] ?? 0);
        $flare = (float) ($o['hem_flare'] ?? 0);
        $sideBottomY = $waistY + $length;
        $centerBottomY = $centerWaistY + $length;

        [$dartIntake, $sideIntake] = $this->waistSplit($g, $qb, $qw, true, $shape);
        $wedge = $front
            ? (($o['bust_dart'] ?? true) ? round($g['bust_dart_intake'] * 0.8, 2) : 0.0)
            : 0.7;

        [$sidePoints, $sideTags, $sideBottomX] = $this->sideEdge([
            'cf' => $cf, 'qb' => $qb, 'qh' => $qh,
            'bust_y' => $bustY, 'waist_y' => $waistY, 'hip_y' => $hipY, 'bottom_y' => $sideBottomY,
            'side_intake' => $sideIntake, 'shape' => $shape, 'flare' => $flare, 'hip_drop' => $g['hip_drop'],
        ]);

        $bottomYAt = fn (float $x): float => $sideBottomY
            + (($centerBottomY - $sideBottomY) * max(0.0, min(1.0, 1 - (($x - $cf) / max(0.1, $sideBottomX - $cf)))));

        // خط نامی درز
        $seamX = $front ? $g['bust_apex_x'] : ($qb * 0.46);
        $apexY = $front ? $g['bust_apex_y'] : $bustY + (($waistY - $bustY) * 0.28);

        // شروع درز پرنسسی روی سرشانه باید میان گودی یقه و نوک سرشانه بماند؛ اگر
        // کاربر عرض یقه را دستی زیاد کند، این نقطه از نوک سرشانه بیرون می‌زند و
        // خط سرشانه به عقب برمی‌گردد.
        $topX = $origin === 'shoulder' ? min(($neckW + $shoulderX) / 2, $shoulderX - 0.3) : $across;
        $topY = $origin === 'shoulder' ? $shoulderY / 2 : $acrossY;

        $keys = [[$topY, $cf + $topX], [$apexY, $cf + $seamX], [$waistY, $cf + $seamX - 0.2]];

        if ($sideBottomY > $hipY + 0.5) {
            $keys[] = [$hipY, $cf + $seamX + 0.6];
            $keys[] = [$bottomYAt($cf + $seamX + 0.6 + ($flare * 0.35)), $cf + $seamX + 0.6 + ($flare * 0.35)];
        } else {
            $keys[] = [$bottomYAt($cf + $seamX), $cf + $seamX];
        }

        $endY = $keys[count($keys) - 1][0];

        $gapKeys = [[$topY, 0.0], [$bustY, 0.0], [$apexY, $wedge], [$waistY, -$dartIntake]];
        $gapKeys[] = $sideBottomY > $hipY + 0.5 ? [$hipY, 0.0] : [$endY, -$dartIntake];

        if ($sideBottomY > $hipY + 0.5) {
            $gapKeys[] = [$endY, 0.0];
        }

        // نمونه‌برداری با نقطه‌های قفل‌شده روی خط سینه، کمر و باسن
        $ys = [];

        for ($i = 0; $i <= 20; $i++) {
            $ys[] = $topY + (($endY - $topY) * ($i / 20));
        }

        foreach ([$bustY, $waistY, $hipY, $apexY] as $pin) {
            if ($pin > $topY + 0.05 && $pin < $endY - 0.05) {
                $ys[] = $pin;
            }
        }

        $ys = array_values(array_unique(array_map(fn ($y) => round($y, 3), $ys)));
        sort($ys);

        $centerLine = [];
        $sideLine = [];
        $pins = [];
        $bustIndex = 0;

        foreach ($ys as $index => $y) {
            $nominal = $this->interpolateX($keys, $y);
            $gap = $this->interpolateX($gapKeys, $y);
            $centerLine[] = ['x' => round($nominal + ($gap / 2), 3), 'y' => round($y, 3)];
            $sideLine[] = ['x' => round($nominal - ($gap / 2), 3), 'y' => round($y, 3)];

            foreach ([$bustY, $waistY, $hipY] as $pin) {
                if (abs($y - $pin) < 0.02) {
                    $pins[] = $index;
                }
            }

            if ($y <= $bustY + 0.02) {
                $bustIndex = $index;
            }
        }

        // بالای خط سینه دو لبه درز پرنسسی روی هم منطبق‌اند (فاصله‌شان صفر است)، پس
        // هم‌اندازه‌کردن طول نباید آنجا شکم بدهد؛ اختلاف طول همیشه از ساسون زیر
        // سینه می‌آید. اگر آنجا شکم داده شود، روی بدنی با سرشانه باریک و سینه
        // درشت، درز از حلقه آستین بیرون می‌زند و قطعه خودش را قطع می‌کند.
        [$centerLine, $sideLine] = $this->trueSeams($centerLine, $sideLine, $pins, $bustIndex);
        $seamLength = $this->polylineLength($centerLine);
        $seamKey = $o['seam_key'] ?? ($front ? 'princess_front' : 'princess_back');

        // ── پنل میانی ──
        $outline = [];
        $edges = [];

        if ($ext > 0) {
            $outline[] = Geometry::point(0, $neckD);
            $outline[] = Geometry::point($cf, $neckD);
            $edges[] = 'default';
        } else {
            $outline[] = Geometry::point($cf, $neckD);
        }

        $outline[] = Geometry::curve($cf + $neckW, 0, $cf + ($neckW * ($front ? 0.10 : 0.34)), $neckD * ($front ? 0.10 : 0.28));
        $edges[] = 'neck';

        if ($origin === 'shoulder') {
            $outline[] = Geometry::point($cf + $topX, $topY);
            $edges[] = 'shoulder';
        } else {
            $outline[] = Geometry::point($cf + $shoulderX, $shoulderY);
            $edges[] = 'shoulder';
            $outline[] = Geometry::curve($cf + $across, $acrossY, $cf + $shoulderX + 0.4, $shoulderY + (($acrossY - $shoulderY) * 0.62));
            $edges[] = 'armhole';
        }

        $seamStart = count($edges);
        [$points, $tags] = $this->polylineEdge($centerLine, 'default');

        foreach ($points as $index => $point) {
            $outline[] = $point;
            $edges[] = $tags[$index];
        }

        $centerSeamEdges = range($seamStart, count($edges) - 1);

        $outline[] = Geometry::point(0, $centerBottomY);
        $edges[] = $o['bottom_tag'] ?? ($shape === 'waist' ? 'waist' : 'hem');
        $edges[] = 'default';

        $onFold = (bool) ($o['on_fold'] ?? ($ext <= 0));
        $closing = count($outline) - 1;

        $lines = ['bust' => $bustY];

        if (in_array($shape, ['waist', 'fitted', 'flare'], true)) {
            $lines['waist'] = $waistY;
        }

        if ($shape === 'fitted' && $sideBottomY >= $hipY - 0.2) {
            $lines['hip'] = $hipY;
        }

        $seamNotch = $this->seamNotch($centerLine, $apexY);

        $center = $this->finishPanel([
            'code' => ($o['prefix'] ?? '').($front ? 'front-center' : 'back-center'),
            'name' => $o['center_name'] ?? ($front ? 'پنل میانی جلو' : 'پنل میانی پشت'),
            'cut' => (int) ($o['center_cut'] ?? ($onFold ? 1 : 2)),
            'mirror' => ! $onFold,
            'layer' => $o['layer'] ?? 'outer',
            'part' => $front ? 'front_panel' : 'back_panel',
        ], $outline, $edges, [
            'lines' => $lines,
            'center_x' => $cf,
            'on_fold' => $onFold,
            'fold_edges' => $onFold ? [$closing] : [],
            'grainline' => $this->grainline($cf + ($seamX * 0.45), max(1.5, $bustY * 0.4), $centerBottomY - 2),
            'notches' => [
                $this->notch(
                    $seamNotch['x'],
                    $seamNotch['y'],
                    (int) (Geometry::nearestEdge($outline, $seamNotch)['edge'] ?? $centerSeamEdges[0]),
                    'نشانه درز پرنسسی',
                    $seamKey,
                ),
            ],
            'markers' => [
                $this->marker('bust', 'خط سینه', $cf, $bustY, $this->interpolateAt($centerLine, $bustY)),
                $this->marker($front ? 'cf' : 'cb', $front ? 'خط مرکز جلو' : 'خط مرکز پشت', $cf, $neckD, $cf, $centerBottomY),
            ],
            'meta' => [
                'part' => $front ? 'front_panel' : 'back_panel',
                'side' => $front ? 'front' : 'back',
                'shape' => $shape,
                'panel' => 'center',
                'seams' => [$seamKey => round($seamLength, 2)],
                'seam_edges' => $centerSeamEdges,
                'bust_y' => round($bustY, 2),
                'waist_y' => round($waistY, 2),
            ],
        ]);

        // ── پنل پهلو ──
        $outline = [];
        $edges = [];
        $outline[] = Geometry::point($cf + $topX, $topY);

        if ($origin === 'shoulder') {
            $outline[] = Geometry::point($cf + $shoulderX, $shoulderY);
            $edges[] = 'shoulder';
            $outline[] = Geometry::curve($cf + $across, $acrossY, $cf + $shoulderX + 0.4, $shoulderY + (($acrossY - $shoulderY) * 0.62));
            $edges[] = 'armhole';
        }

        $outline[] = Geometry::curve($cf + $qb, $bustY, $cf + $across + (($qb - $across) * 0.16), $bustY - (($bustY - $acrossY) * 0.06));
        $edges[] = 'armhole';
        $armholeEdge = count($edges) - 1;

        foreach ($sidePoints as $index => $point) {
            $outline[] = $point;
            $edges[] = $sideTags[$index];
        }

        $sideEdgeIndexes = range(count($edges) - count($sideTags), count($edges) - 1);

        $seamEnd = $sideLine[count($sideLine) - 1];
        $outline[] = Geometry::point($seamEnd['x'], $seamEnd['y']);
        $edges[] = $o['bottom_tag'] ?? ($shape === 'waist' ? 'waist' : 'hem');

        $seamStart = count($edges);
        $back = $this->reversePolyline($sideLine);
        array_pop($back); // نقطه پایانی همان نقطه شروع مسیر است

        [$points, $tags] = $this->polylineEdge($back, 'default');

        foreach ($points as $index => $point) {
            $outline[] = $point;
            $edges[] = $tags[$index];
        }

        $edges[] = 'default'; // لبه بسته‌شدن: بالای درز پرنسسی
        $sideSeamEdges = range($seamStart, count($edges) - 1);
        $sideNotch = $this->seamNotch($sideLine, $apexY);

        $sidePanel = $this->finishPanel([
            'code' => ($o['prefix'] ?? '').($front ? 'front-side' : 'back-side'),
            'name' => $o['side_name'] ?? ($front ? 'پنل پهلوی جلو' : 'پنل پهلوی پشت'),
            'cut' => 2,
            'mirror' => true,
            'layer' => $o['layer'] ?? 'outer',
        ], $outline, $edges, [
            'lines' => $lines,
            'center_x' => null,
            'on_fold' => false,
            'grainline' => $this->grainline(($cf + $topX + $qb) / 2, $acrossY + 2, $sideBottomY - 2),
            'notches' => [
                $this->notch(
                    $sideNotch['x'],
                    $sideNotch['y'],
                    (int) (Geometry::nearestEdge($outline, $sideNotch)['edge'] ?? $sideSeamEdges[0]),
                    'نشانه درز پرنسسی',
                    $seamKey,
                ),
            ],
            'markers' => [
                $this->marker('bust', 'خط سینه', $this->interpolateAt($sideLine, $bustY), $bustY, $cf + $qb),
            ],
            'meta' => [
                'part' => $front ? 'front_panel' : 'back_panel',
                'side' => $front ? 'front' : 'back',
                'shape' => $shape,
                'panel' => 'side',
                'seams' => [$seamKey => round($this->polylineLength($sideLine), 2)],
                'seam_edges' => $sideSeamEdges,
                'side_edges' => $sideEdgeIndexes,
                'armhole_edge' => $armholeEdge,
                'bust_y' => round($bustY, 2),
                'waist_y' => round($waistY, 2),
            ],
        ]);

        return [$center, $sidePanel];
    }

    /** x یک خط شکسته روی ارتفاع داده‌شده. */
    protected function interpolateAt(array $line, float $y): float
    {
        $best = $line[0]['x'];
        $bestDiff = PHP_FLOAT_MAX;

        foreach ($line as $point) {
            $diff = abs($point['y'] - $y);

            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $point['x'];
            }
        }

        return round($best, 2);
    }

    /** نشانه جفت‌شدن روی درز مدل، نزدیک ارتفاع داده‌شده. */
    protected function seamNotch(array $line, float $y): array
    {
        $best = $line[0];
        $bestDiff = PHP_FLOAT_MAX;

        foreach ($line as $point) {
            $diff = abs($point['y'] - $y);

            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $point;
            }
        }

        return $best;
    }

    /**
     * «راه رفتن» روی دو درزی که باید به هم دوخته شوند و هم‌اندازه کردنشان.
     *
     * نقطه‌های قفل‌شده (خط سینه، کمر و باسن) دست‌نخورده می‌مانند تا دور لباس عوض
     * نشود؛ فقط منحنی بین آن‌ها کمی پرتر می‌شود تا طول دو درز یکی شود.
     *
     * @param  array<int, int>  $pins  اندیس نقطه‌های قفل‌شده
     * @param  int  $lockUntil  تا این اندیس هیچ شکمی داده نمی‌شود
     * @return array{0: array<int, array{x: float, y: float}>, 1: array<int, array{x: float, y: float}>}
     */
    protected function trueSeams(array $a, array $b, array $pins, int $lockUntil = 0): array
    {
        $lengthA = $this->polylineLength($a);
        $lengthB = $this->polylineLength($b);

        if (abs($lengthA - $lengthB) < 0.005) {
            return [$a, $b];
        }

        return $lengthA < $lengthB
            ? [$this->bumpToLength($a, $pins, $lengthB, -1, $lockUntil), $b]
            : [$a, $this->bumpToLength($b, $pins, $lengthA, 1, $lockUntil)];
    }

    /**
     * خط شکسته را با یک شکم افقی بین نقطه‌های قفل‌شده به طول هدف می‌رساند.
     *
     * @param  array<int, int>  $pins
     * @return array<int, array{x: float, y: float}>
     */
    protected function bumpToLength(array $line, array $pins, float $target, int $direction = 1, int $lockUntil = 0): array
    {
        $count = count($line);
        $pins = array_values(array_unique(array_merge([0, $count - 1], $pins)));
        sort($pins);

        $weights = array_fill(0, $count, 0.0);

        for ($p = 0; $p < count($pins) - 1; $p++) {
            $from = $pins[$p];
            $to = $pins[$p + 1];
            $span = $to - $from;

            if ($span < 2 || $from < $lockUntil) {
                continue;
            }

            for ($i = $from + 1; $i < $to; $i++) {
                $weights[$i] = sin(M_PI * (($i - $from) / $span));
            }
        }

        $apply = function (float $amplitude) use ($line, $weights, $direction): array {
            $out = [];

            foreach ($line as $index => $point) {
                $out[] = [
                    'x' => round($point['x'] + ($direction * $amplitude * $weights[$index]), 3),
                    'y' => $point['y'],
                ];
            }

            return $out;
        };

        $low = 0.0;
        $high = 8.0;

        for ($i = 0; $i < 44; $i++) {
            $mid = ($low + $high) / 2;

            if ($this->polylineLength($apply($mid)) < $target) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return $apply(($low + $high) / 2);
    }

    /**
     * تبدیل خط شکسته به نقطه‌های مسیر (به‌جز نقطه اول که قبلاً روی مسیر است).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    protected function polylineEdge(array $line, string $tag, bool $skipFirst = true): array
    {
        $points = [];
        $tags = [];

        foreach (array_values($line) as $index => $point) {
            if ($skipFirst && $index === 0) {
                continue;
            }

            $points[] = Geometry::point($point['x'], $point['y']);
            $tags[] = $tag;
        }

        return [$points, $tags];
    }
}
