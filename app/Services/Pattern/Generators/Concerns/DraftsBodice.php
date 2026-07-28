<?php

namespace App\Services\Pattern\Generators\Concerns;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;

/**
 * درفت مشترک بالاتنه.
 *
 * همه لباس‌های بالاتنه (بالاتنه پایه، تی‌شرت، پیراهن، پیراهن یک‌تکه و کت) از همین
 * یک درفت استفاده می‌کنند و تنها «شکل» پایین قطعه و چند گزینه فرق می‌کند:
 *   waist  → در خط کمر تمام می‌شود (بلوک پایه)
 *   straight → درز پهلو راست از زیر بغل تا لبه پایین (تی‌شرت و پیراهن مردانه)
 *   flare  → از کمر به بیرون باز می‌شود (تونیک و پیراهن آزاد)
 *   dress  → کمر گرفته، از باسن رد می‌شود و تا پایین می‌آید (پیراهن یک‌تکه)
 *
 * دو قانون خیاطی که همه‌جای این درفت رعایت می‌شود:
 *
 *   ۱) درز پهلو یک درز است، نه دو تا. جلوی بدن از پشت بلندتر است، ولی این بلندی
 *      روی مرکز جلو و ساسون سینه می‌نشیند نه روی پهلو؛ پس تراز کمرِ پهلو در جلو
 *      و پشت یکی است و خط کمرِ جلو از پهلو به سمت مرکز افت می‌کند.
 *
 *   ۲) ساسون سینه پهنای خودش را از درز پهلو می‌خورد. اگر همان مقدار پیش از دوخت
 *      به درز اضافه نشود، بعد از بستن ساسون پهلوی جلو به همان اندازه از پهلوی
 *      پشت کوتاه‌تر درمی‌آید. اینجا درز پس از نشستن ساسون «راست» می‌شود.
 */
trait DraftsBodice
{
    /**
     * یک قطعه بالاتنه کامل (جلو یا پشت).
     *
     * گزینه‌ها: side، shape، bottom_y، bottom_tag، extension (اضافه جلو برای جای دکمه)،
     * neck_extra، hem_flare، hip_ease_extra، darts، code، name، part، on_fold، cut، mirror، top_y.
     *
     * @param  array<string, float>  $g  خروجی bodiceMetrics()
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function bodicePiece(array $g, array $o = []): array
    {
        $isFront = ($o['side'] ?? 'front') === 'front';
        $shape = $o['shape'] ?? 'waist';
        $ext = (float) ($o['extension'] ?? 0);
        $cf = $ext;

        $neckWidth = $g['neck_width'] + ($isFront ? 0 : 0.3);
        $neckDepth = ($isFront ? $g['front_neck_depth'] : $g['back_neck_depth']) + (float) ($o['neck_extra'] ?? 0);
        $waistY = $isFront ? $g['front_waist_y'] : $g['back_waist_y'];

        // تراز کمر روی درز پهلو، مشترک میان جلو و پشت (قانون ۱ بالای همین فایل).
        // «افت جلو» همان اختلاف بلندی جلو و پشت است که روی مرکز جلو می‌ماند.
        $sideWaistY = min($g['front_waist_y'], $g['back_waist_y']);
        $frontRise = round($waistY - $sideWaistY, 3);

        $shoulderDrop = $g['shoulder_drop'] + ($isFront ? 0 : 0.5);
        $quarterBust = $g['quarter_bust'];
        $bustY = $g['bust_y'];
        $across = $isFront ? $g['across_chest'] : $g['across_back'];
        $bottomY = (float) ($o['bottom_y'] ?? $waistY);
        $sideIntake = $shape === 'straight' ? 0.0 : $g['side_intake'];
        $sideX = $cf + $quarterBust - $sideIntake;
        $hemFlare = (float) ($o['hem_flare'] ?? 0);

        $outline = [];
        $edges = [];

        // اضافه جلو (جای دکمه) با یک پله افقی در بالای لبه جلو شروع می‌شود
        if ($ext > 0) {
            $outline[] = Geometry::point(0, $neckDepth);
            $outline[] = Geometry::point($cf, $neckDepth);
            $edges[] = 'default';
        } else {
            $outline[] = Geometry::point($cf, $neckDepth);
        }

        // یقه
        $outline[] = Geometry::curve(
            $cf + $neckWidth,
            0,
            $cf + ($neckWidth * ($isFront ? 0.12 : 0.32)),
            $neckDepth * ($isFront ? 0.12 : 0.24),
        );
        $edges[] = 'neck';

        // سرشانه
        $outline[] = Geometry::point($cf + $g['shoulder_half'], $shoulderDrop);
        $edges[] = 'shoulder';

        // حلقه آستین: منحنی گودافتاده تا زیر بغل
        $outline[] = Geometry::curve(
            $cf + $quarterBust,
            $bustY,
            $cf + $across - ($isFront ? 1.0 : 0.6),
            $bustY * ($isFront ? 0.62 : 0.66),
        );
        $edges[] = 'armhole';

        $waistSideIndex = null;
        $hipY = $waistY + $g['hip_drop'];
        $sideHipY = $sideWaistY + $g['hip_drop'];

        if ($shape === 'straight') {
            $outline[] = Geometry::point($cf + $quarterBust + $hemFlare, $bottomY);
            $edges[] = 'side';
        } elseif ($shape === 'flare') {
            $outline[] = Geometry::curve(
                $sideX,
                $sideWaistY,
                $cf + $quarterBust - ($sideIntake * 0.7),
                $bustY + (($sideWaistY - $bustY) * 0.55),
            );
            $edges[] = 'side';
            $waistSideIndex = count($outline) - 1;
            $outline[] = Geometry::point($cf + $quarterBust + $hemFlare, $bottomY);
            $edges[] = 'side';
        } elseif ($shape === 'dress') {
            $hipX = $cf + max($quarterBust, $g['quarter_hip'] + (float) ($o['hip_ease_extra'] ?? 0));
            $outline[] = Geometry::curve(
                $sideX,
                $sideWaistY,
                $cf + $quarterBust - ($sideIntake * 0.7),
                $bustY + (($sideWaistY - $bustY) * 0.55),
            );
            $edges[] = 'side';
            $waistSideIndex = count($outline) - 1;

            $waistPoint = Geometry::point($sideX, $sideWaistY);
            $hipControl = Geometry::point($sideX + (($hipX - $sideX) * 0.35), $sideWaistY + ($g['hip_drop'] * 0.55));
            $hipPoint = Geometry::point($hipX, $sideHipY);

            if ($bottomY > $sideHipY + 0.5) {
                $outline[] = Geometry::curve($hipX, $sideHipY, $hipControl['x'], $hipControl['y']);
                $edges[] = 'side';
                $outline[] = Geometry::point($hipX + $hemFlare, $bottomY);
                $edges[] = 'side';
            } else {
                // دم بالای خط باسن افتاده (تنه کوتاه روی بدنی با فاصله کمر تا باسن
                // زیاد). اگر مسیر همچنان تا نقطه باسن پایین برود و برگردد، قطعه
                // خودش را قطع می‌کند. پس منحنی کمر تا باسن را دقیقاً روی بلندی دم
                // می‌شکنیم: انحنا و پهنای درز پهلو در آن بلندی همان می‌ماند.
                $cut = $this->splitCurveAtHeight($waistPoint, $hipControl, $hipPoint, $bottomY);
                $outline[] = Geometry::curve(
                    $cut['point']['x'] + $hemFlare,
                    $bottomY,
                    $cut['first']['x'],
                    $cut['first']['y'],
                );
                $edges[] = 'side';
            }
        } else { // waist
            $sideBottomY = $bottomY - $frontRise;
            $outline[] = Geometry::curve(
                $sideX,
                $sideBottomY,
                $cf + $quarterBust - ($sideIntake * 0.7),
                $bustY + (($sideBottomY - $bustY) * 0.55),
            );
            $edges[] = 'side';
            $waistSideIndex = count($outline) - 1;
        }

        // لبه پایین و بستن قطعه از خط مرکزی
        $outline[] = Geometry::point(0, $bottomY);
        $edges[] = $o['bottom_tag'] ?? ($shape === 'waist' ? 'waist' : 'hem');
        $edges[] = 'default'; // لبه بسته‌شدن: خط مرکز جلو/پشت

        $sideEdges = array_keys($edges, 'side', true);
        $wantsDarts = (bool) ($o['darts'] ?? true);
        $bustDartIntake = $isFront && $wantsDarts && ($o['bust_dart'] ?? false) && $g['bust_dart_intake'] > 0.8
            ? (float) $g['bust_dart_intake']
            : 0.0;

        // قانون ۲: پارچه‌ای که ساسون سینه از پهلو می‌خورد، پیش از دوخت به همان
        // درز برگردانده می‌شود تا پهلوی جلو و پشت روی هم پیاده شوند
        if ($bustDartIntake > 0 && $sideEdges !== []) {
            $outline = $this->trueSideSeamForBustDart($outline, $edges, $sideEdges, $bustDartIntake);
        }

        $closingEdge = count($outline) - 1;
        $onFold = (bool) ($o['on_fold'] ?? ($ext <= 0));

        $part = $o['part'] ?? ($isFront ? 'front_bodice' : 'back_bodice');
        $centerKey = $isFront ? 'cf' : 'cb';
        $centerLabel = $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت';

        $markers = [
            $this->marker('bust', 'خط سینه', $cf, $bustY, $cf + $quarterBust),
            $this->marker($centerKey, $centerLabel, $cf, $neckDepth, $cf, $bottomY),
        ];

        // سر بیرونی خط‌های نشانه از خودِ درز پهلو خوانده می‌شود، وگرنه روی قطعه‌ای
        // که در آن تراز باریک‌تر (یا پهن‌تر) از اندازه بدن است بیرون کاغذ می‌افتد
        if ($bottomY > $waistY + 2) {
            $at = $this->bodiceSidePointAtY($outline, $sideEdges, $waistY);
            $markers[] = $this->marker('waist', 'خط کمر', $cf, $waistY, $at['x']);
        }

        if ($bottomY > $hipY + 1) {
            $at = $this->bodiceSidePointAtY($outline, $sideEdges, $hipY);
            $markers[] = $this->marker('hip', 'خط باسن', $cf, $hipY, $at['x']);
        }

        $shoulderMid = Geometry::pointOnEdge($outline, array_search('shoulder', $edges, true), 0.5);
        $armholeIndex = (int) array_search('armhole', $edges, true);
        $armholeNotch = Geometry::pointOnEdge($outline, $armholeIndex, $isFront ? 0.62 : 0.55);

        $notches = [
            $this->notch($shoulderMid['x'], $shoulderMid['y'], (int) array_search('shoulder', $edges, true), 'نشانه سرشانه', 'shoulder'),
            $this->notch($armholeNotch['x'], $armholeNotch['y'], $armholeIndex, 'نشانه حلقه آستین', 'armhole'),
        ];

        if ($waistSideIndex !== null) {
            // نشانه کمر همان رأسِ کمر روی درز پهلوست، پس پس از راست‌سازی هم سر جایش می‌ماند
            $sideWaistPoint = $outline[$waistSideIndex];
            $notches[] = $this->notch(
                (float) $sideWaistPoint['x'],
                (float) $sideWaistPoint['y'],
                $sideEdges[0],
                'نشانه کمر روی پهلو',
                'side',
            );
        }

        $darts = [];

        if ($wantsDarts && $g['dart_intake'] > 0.6) {
            if ($shape === 'waist') {
                $bottomEdge = (int) array_search($o['bottom_tag'] ?? 'waist', $edges, true);
                $dartX = $isFront ? $cf + $g['bust_apex_x'] : $cf + ($quarterBust * 0.45);

                // خط کمرِ جلو شیب دارد؛ دو پای ساسون باید روی خودِ آن لبه بنشینند
                $left = $this->bodiceEdgePointAtX($outline, $bottomEdge, $dartX - ($g['dart_intake'] / 2));
                $right = $this->bodiceEdgePointAtX($outline, $bottomEdge, $dartX + ($g['dart_intake'] / 2));

                $darts[] = $this->dart(
                    'waist',
                    'ساسون کمر',
                    $bottomEdge,
                    ($left['x'] + $right['x']) / 2,
                    ($left['y'] + $right['y']) / 2,
                    $g['dart_intake'],
                    $dartX,
                    $isFront ? $g['bust_apex_y'] : $bustY + 3,
                    'x',
                    ['legs' => [Geometry::point($left['x'], $left['y']), Geometry::point($right['x'], $right['y'])]],
                );
            } elseif (in_array($shape, ['dress', 'flare'], true)) {
                // ساسون بادامی: در خط کمر بسته می‌شود و به بالا و پایین باز می‌شود
                $centerX = $isFront ? $cf + $g['bust_apex_x'] : $cf + ($quarterBust * 0.45);
                $darts[] = $this->dart(
                    'waist',
                    'ساسون کمر',
                    null,
                    $centerX,
                    $waistY,
                    $g['dart_intake'],
                    $centerX,
                    $isFront ? $g['bust_apex_y'] : $bustY + 3,
                    'x',
                    ['apex_lower' => Geometry::point($centerX, min($bottomY - 2, $hipY - 1))],
                );
            }
        }

        if ($bustDartIntake > 0 && $sideEdges !== []) {
            // دو پای ساسون سینه روی خودِ منحنی درز پهلو و به فاصله دهانه ساسون از
            // هم گرفته می‌شوند؛ پس بستن ساسون همان مقداری را می‌خورد که راست‌سازی
            // بالاتر به درز اضافه کرده بود
            $sideEdge = $sideEdges[0];
            $apexY = min($g['bust_apex_y'], $bustY + (($sideWaistY - $bustY) * 0.5));
            $length = Geometry::edgeLength($outline, $sideEdge);
            $half = $bustDartIntake / 2;
            $at = max($half, min($length - $half, $this->bodiceSeamDistanceAtY($outline, $sideEdge, $apexY)));

            $upper = $this->bodiceSeamPointAtDistance($outline, $sideEdge, $at - $half);
            $lower = $this->bodiceSeamPointAtDistance($outline, $sideEdge, $at + $half);

            $darts[] = $this->dart(
                'bust',
                'ساسون سینه',
                $sideEdge,
                ($upper['x'] + $lower['x']) / 2,
                ($upper['y'] + $lower['y']) / 2,
                $bustDartIntake,
                $cf + $g['bust_apex_x'],
                $apexY,
                'y',
                ['legs' => [Geometry::point($upper['x'], $upper['y']), Geometry::point($lower['x'], $lower['y'])]],
            );
        }

        return $this->piece([
            'code' => $o['code'] ?? ($isFront ? 'front' : 'back'),
            'name' => $o['name'] ?? ($isFront ? 'بالاتنه جلو' : 'بالاتنه پشت'),
            'cut_quantity' => (int) ($o['cut'] ?? 1),
            'on_fold' => $onFold,
            'mirror' => (bool) ($o['mirror'] ?? ! $onFold),
            'layer' => $o['layer'] ?? 'outer',
            'outline' => $outline,
            'grainline' => $this->grainline($cf + ($quarterBust * 0.4), $bustY * 0.35, $bottomY - 3),
            'darts' => $darts,
            'notches' => $notches,
            'markers' => $markers,
            'meta' => array_merge([
                'part' => $part,
                'edges' => $edges,
                'fold_edges' => $onFold ? [$closingEdge] : [],
                'side' => $isFront ? 'front' : 'back',
                'shape' => $shape,
                'armhole_edge' => $armholeIndex,
                'armhole_length' => Geometry::edgeLength($outline, $armholeIndex),
                'neck_length' => Geometry::edgeLength($outline, (int) array_search('neck', $edges, true)),
                'bust_y' => $bustY,
                'waist_y' => $waistY,
                'side_waist_y' => $sideWaistY,
                'hip_y' => $hipY,
                'bust_dart_intake' => round($bustDartIntake, 2),
            ], $o['meta'] ?? []),
        ]);
    }

    /**
     * راست‌سازی درز پهلو پس از نشستن ساسون سینه روی آن.
     *
     * درز با همان ابزاری کشیده می‌شود که خیاط با آن درز را «راست» می‌کند: سرِ زیر
     * بغل ثابت می‌ماند، رأس‌های پایین‌تر به نسبت فاصله‌شان روی امتداد خود درز پایین
     * می‌روند و نقطه‌های راهنمای منحنی هم با آن‌ها می‌آیند؛ پس درز بلند می‌شود ولی
     * شکستگی نمی‌گیرد. بعد از بستن ساسون، همین درز دقیقاً به اندازه پیش از
     * راست‌سازی — یعنی هم‌اندازه درز پهلوی پشت — درمی‌آید.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @param  array<int, string>  $edges
     * @param  array<int, int>  $sideEdges
     * @return array<int, array<string, mixed>>
     */
    protected function trueSideSeamForBustDart(array $outline, array $edges, array $sideEdges, float $intake): array
    {
        $withDart = [
            'outline' => array_values($outline),
            'meta' => ['edges' => $edges],
            'notches' => [],
            'darts' => [['edge' => $sideEdges[0], 'intake' => $intake]],
        ];

        // همان قطعه بدون ساسون، تنها برای اینکه طول هدف را نگه دارد
        $target = $withDart;
        $target['darts'] = [];

        $trued = PieceOps::trueSeam($withDart, $sideEdges, $target, $sideEdges, [
            'adjust' => 'a',
            'anchor' => 'start',
            'rounds' => 24,
        ]);

        return array_values($trued['a']['outline']);
    }

    /**
     * نقطه‌ای روی درز پهلو که در ارتفاع داده‌شده است.
     *
     * اگر آن ارتفاع بالاتر یا پایین‌تر از خود درز باشد، نزدیک‌ترین سرِ درز برگردانده
     * می‌شود تا خط نشانه بیرون قطعه نیفتد.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @param  array<int, int>  $sideEdges
     * @return array{x: float, y: float}
     */
    protected function bodiceSidePointAtY(array $outline, array $sideEdges, float $y): array
    {
        $best = null;

        foreach ($sideEdges as $edge) {
            $start = Geometry::pointOnEdge($outline, $edge, 0.0);
            $end = Geometry::pointOnEdge($outline, $edge, 1.0);
            $low = min((float) $start['y'], (float) $end['y']);
            $high = max((float) $start['y'], (float) $end['y']);

            if ($y >= $low - 1e-6 && $y <= $high + 1e-6) {
                return $this->bodiceEdgePointAt($outline, $edge, $y, 'y');
            }

            foreach ([$start, $end] as $candidate) {
                $gap = abs(((float) $candidate['y']) - $y);

                if ($best === null || $gap < $best['gap']) {
                    $best = ['gap' => $gap, 'x' => (float) $candidate['x'], 'y' => (float) $candidate['y']];
                }
            }
        }

        return $best === null ? ['x' => 0.0, 'y' => $y] : ['x' => $best['x'], 'y' => $best['y']];
    }

    /**
     * نقطه‌ای روی یک لبه که مختصات افقی داده‌شده را دارد.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array{x: float, y: float}
     */
    protected function bodiceEdgePointAtX(array $outline, int $edge, float $x): array
    {
        return $this->bodiceEdgePointAt($outline, $edge, $x, 'x');
    }

    /**
     * نقطه‌ای روی یک لبه با مختصات داده‌شده در یکی از دو راستا (دوبخشی).
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array{x: float, y: float}
     */
    protected function bodiceEdgePointAt(array $outline, int $edge, float $value, string $axis): array
    {
        $point = Geometry::pointOnEdge($outline, $edge, $this->bodiceEdgeParameterAt($outline, $edge, $value, $axis));

        return ['x' => (float) $point['x'], 'y' => (float) $point['y']];
    }

    /**
     * نسبت t روی یک لبه که در آن، مختصات راستای خواسته‌شده به مقدار داده‌شده می‌رسد.
     *
     * @param  array<int, array<string, mixed>>  $outline
     */
    protected function bodiceEdgeParameterAt(array $outline, int $edge, float $value, string $axis): float
    {
        $low = 0.0;
        $high = 1.0;
        $ascending = (float) Geometry::pointOnEdge($outline, $edge, 1.0)[$axis]
            >= (float) Geometry::pointOnEdge($outline, $edge, 0.0)[$axis];

        for ($i = 0; $i < 40; $i++) {
            $mid = ($low + $high) / 2;

            if ((((float) Geometry::pointOnEdge($outline, $edge, $mid)[$axis]) < $value) === $ascending) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    /**
     * فاصله‌ای روی یک لبه (از سر آن) که در ارتفاع داده‌شده است.
     *
     * @param  array<int, array<string, mixed>>  $outline
     */
    protected function bodiceSeamDistanceAtY(array $outline, int $edge, float $y): float
    {
        $t = $this->bodiceEdgeParameterAt($outline, $edge, $y, 'y');
        $steps = 128;
        $walked = 0.0;
        $previous = Geometry::pointOnEdge($outline, $edge, 0.0);

        for ($i = 1; $i <= $steps; $i++) {
            $current = Geometry::pointOnEdge($outline, $edge, ($t * $i) / $steps);
            $walked += Geometry::distance($previous, $current);
            $previous = $current;
        }

        return $walked;
    }

    /**
     * نقطه‌ای روی یک لبه، در فاصله داده‌شده از سر آن (روی خود منحنی).
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array{x: float, y: float}
     */
    protected function bodiceSeamPointAtDistance(array $outline, int $edge, float $distance): array
    {
        $steps = 128;
        $walked = 0.0;
        $previous = Geometry::pointOnEdge($outline, $edge, 0.0);

        if ($distance <= 0) {
            return ['x' => (float) $previous['x'], 'y' => (float) $previous['y']];
        }

        for ($i = 1; $i <= $steps; $i++) {
            $current = Geometry::pointOnEdge($outline, $edge, $i / $steps);
            $step = Geometry::distance($previous, $current);

            if ($walked + $step >= $distance) {
                $share = $step > 1e-9 ? ($distance - $walked) / $step : 0.0;
                $point = Geometry::lerp(
                    ['x' => (float) $previous['x'], 'y' => (float) $previous['y']],
                    ['x' => (float) $current['x'], 'y' => (float) $current['y']],
                    $share,
                );

                return ['x' => (float) $point['x'], 'y' => (float) $point['y']];
            }

            $walked += $step;
            $previous = $current;
        }

        return ['x' => (float) $previous['x'], 'y' => (float) $previous['y']];
    }

    /**
     * شکستن منحنی درجه‌دو در بلندی مشخص.
     *
     * منحنی درز پهلو در راستای y یکنواخت پایین می‌رود، پس جای برش را با جست‌وجوی
     * دودویی روی نسبت t پیدا می‌کنیم و با دو کاستلژو می‌شکنیم تا انحنا عوض نشود.
     *
     * @return array{point: array{x: float, y: float}, first: array{x: float, y: float}, second: array{x: float, y: float}}
     */
    protected function splitCurveAtHeight(array $p0, array $control, array $p1, float $y): array
    {
        $low = 0.0;
        $high = 1.0;

        for ($i = 0; $i < 40; $i++) {
            $mid = ($low + $high) / 2;
            $point = Geometry::quadraticAt($p0, $control, $p1, $mid);

            if ($point['y'] < $y) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return Geometry::splitQuadratic($p0, $control, $p1, ($low + $high) / 2);
    }
}
