<?php

namespace App\Services\Pattern;

/**
 * ریاضیات پایه هندسه الگو.
 *
 * قرارداد مسیر قطعه (outline) در همه‌جای سامانه یکسان است:
 *   - واحد سانتی‌متر، محور x به راست و محور y به پایین، مبدأ گوشه بالا-چپ همان قطعه.
 *   - هر عضو آرایه یک نقطه است: ['x' => float, 'y' => float].
 *   - اگر نقطه شامل ['curve' => true, 'cx' => float, 'cy' => float] باشد، رسیدن از نقطه
 *     پیشین به این نقطه با یک منحنی درجه‌دو (کوادراتیک) و نقطه کنترل (cx, cy) انجام می‌شود.
 *   - چندضلعی همیشه بسته است: آخرین نقطه به نقطه اول وصل می‌شود.
 *   - لبه شماره i از نقطه i به نقطه i+1 می‌رود و لبه آخر (n-1) قطعه را می‌بندد.
 */
class Geometry
{
    /** تعداد پاره‌خط برای تبدیل هر منحنی به خط شکسته. */
    public const CURVE_SEGMENTS = 12;

    public static function point(float $x, float $y): array
    {
        return ['x' => round($x, 3), 'y' => round($y, 3)];
    }

    /** نقطه‌ای که با منحنی درجه‌دو به آن می‌رسیم. */
    public static function curve(float $x, float $y, float $cx, float $cy): array
    {
        return ['x' => round($x, 3), 'y' => round($y, 3), 'curve' => true, 'cx' => round($cx, 3), 'cy' => round($cy, 3)];
    }

    public static function isCurve(array $point): bool
    {
        return ! empty($point['curve']) && isset($point['cx'], $point['cy']);
    }

    /** @return array<int, array{x: float, y: float}> */
    public static function points(array $outline): array
    {
        $out = [];

        foreach ($outline as $point) {
            $out[] = ['x' => (float) ($point['x'] ?? 0), 'y' => (float) ($point['y'] ?? 0)];
        }

        return $out;
    }

    /**
     * تبدیل مسیر (با منحنی‌ها) به خط شکسته ساده.
     *
     * @return array<int, array{x: float, y: float}>
     */
    public static function flatten(array $outline, int $segments = self::CURVE_SEGMENTS): array
    {
        $outline = array_values($outline);
        $count = count($outline);

        if ($count === 0) {
            return [];
        }

        $result = [['x' => (float) ($outline[0]['x'] ?? 0), 'y' => (float) ($outline[0]['y'] ?? 0)]];

        for ($i = 1; $i <= $count; $i++) {
            $target = $outline[$i % $count];
            $from = $result[count($result) - 1];

            if ($i === $count && ! static::isCurve($target)) {
                break; // بسته شدن با خط راست نیازی به نقطه تکراری ندارد
            }

            if (static::isCurve($target)) {
                for ($s = 1; $s <= $segments; $s++) {
                    $t = $s / $segments;
                    $result[] = static::quadraticAt(
                        $from,
                        ['x' => (float) $target['cx'], 'y' => (float) $target['cy']],
                        ['x' => (float) $target['x'], 'y' => (float) $target['y']],
                        $t,
                    );
                }

                if ($i === $count) {
                    array_pop($result); // نقطه پایانی همان نقطه اول است
                }

                continue;
            }

            $result[] = ['x' => (float) $target['x'], 'y' => (float) $target['y']];
        }

        return $result;
    }

    /** نقطه روی منحنی درجه‌دو. */
    public static function quadraticAt(array $p0, array $control, array $p1, float $t): array
    {
        $mt = 1 - $t;

        return [
            'x' => ($mt * $mt * $p0['x']) + (2 * $mt * $t * $control['x']) + ($t * $t * $p1['x']),
            'y' => ($mt * $mt * $p0['y']) + (2 * $mt * $t * $control['y']) + ($t * $t * $p1['y']),
        ];
    }

    /** کادر دربرگیرنده: [minX, minY, maxX, maxY]. */
    public static function bounds(array $outline): array
    {
        $points = static::flatten($outline);

        if ($points === []) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        $xs = array_column($points, 'x');
        $ys = array_column($points, 'y');

        return [(float) min($xs), (float) min($ys), (float) max($xs), (float) max($ys)];
    }

    public static function width(array $outline): float
    {
        [$minX, , $maxX] = static::bounds($outline);

        return round($maxX - $minX, 2);
    }

    public static function height(array $outline): float
    {
        [, $minY, , $maxY] = static::bounds($outline);

        return round($maxY - $minY, 2);
    }

    /** مساحت چندضلعی (سانتی‌متر مربع). */
    public static function area(array $outline): float
    {
        $points = static::flatten($outline);

        return round(abs(static::signedArea($points)), 2);
    }

    /** مساحت علامت‌دار خط شکسته؛ علامت جهت چرخش را مشخص می‌کند. */
    public static function signedArea(array $points): float
    {
        $count = count($points);

        if ($count < 3) {
            return 0.0;
        }

        $sum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];
            $sum += ($a['x'] * $b['y']) - ($b['x'] * $a['y']);
        }

        return $sum / 2;
    }

    /** محیط مسیر. */
    public static function perimeter(array $outline): float
    {
        $points = static::flatten($outline);
        $count = count($points);
        $total = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $total += static::distance($points[$i], $points[($i + 1) % $count]);
        }

        return round($total, 2);
    }

    /** طول یک لبه (با در نظر گرفتن منحنی بودن آن). */
    public static function edgeLength(array $outline, int $index): float
    {
        $outline = array_values($outline);
        $count = count($outline);

        if ($count < 2) {
            return 0.0;
        }

        $index = (($index % $count) + $count) % $count;
        $from = static::points([$outline[$index]])[0];
        $target = $outline[($index + 1) % $count];

        if (! static::isCurve($target)) {
            return round(static::distance($from, ['x' => (float) $target['x'], 'y' => (float) $target['y']]), 2);
        }

        $control = ['x' => (float) $target['cx'], 'y' => (float) $target['cy']];
        $end = ['x' => (float) $target['x'], 'y' => (float) $target['y']];
        $length = 0.0;
        $previous = $from;

        for ($s = 1; $s <= static::CURVE_SEGMENTS; $s++) {
            $current = static::quadraticAt($from, $control, $end, $s / static::CURVE_SEGMENTS);
            $length += static::distance($previous, $current);
            $previous = $current;
        }

        return round($length, 2);
    }

    /** مجموع طول چند لبه پشت سر هم. */
    public static function edgesLength(array $outline, array $indexes): float
    {
        $total = 0.0;

        foreach ($indexes as $index) {
            $total += static::edgeLength($outline, $index);
        }

        return round($total, 2);
    }

    /** نقطه‌ای در نسبت t روی یک لبه. */
    public static function pointOnEdge(array $outline, int $index, float $t = 0.5): array
    {
        $outline = array_values($outline);
        $count = count($outline);

        if ($count === 0) {
            return ['x' => 0.0, 'y' => 0.0];
        }

        $index = (($index % $count) + $count) % $count;
        $from = static::points([$outline[$index]])[0];
        $target = $outline[($index + 1) % $count];
        $end = ['x' => (float) $target['x'], 'y' => (float) $target['y']];

        if (! static::isCurve($target)) {
            return static::lerp($from, $end, $t);
        }

        return static::quadraticAt($from, ['x' => (float) $target['cx'], 'y' => (float) $target['cy']], $end, $t);
    }

    public static function distance(array $a, array $b): float
    {
        return sqrt((($b['x'] - $a['x']) ** 2) + (($b['y'] - $a['y']) ** 2));
    }

    public static function lerp(array $a, array $b, float $t): array
    {
        return [
            'x' => $a['x'] + (($b['x'] - $a['x']) * $t),
            'y' => $a['y'] + (($b['y'] - $a['y']) * $t),
        ];
    }

    /** جابه‌جایی مسیر (نقاط کنترل هم جابه‌جا می‌شوند). */
    public static function translate(array $outline, float $dx, float $dy): array
    {
        return array_map(function (array $point) use ($dx, $dy) {
            $point['x'] = round(((float) ($point['x'] ?? 0)) + $dx, 3);
            $point['y'] = round(((float) ($point['y'] ?? 0)) + $dy, 3);

            if (isset($point['cx'], $point['cy'])) {
                $point['cx'] = round(((float) $point['cx']) + $dx, 3);
                $point['cy'] = round(((float) $point['cy']) + $dy, 3);
            }

            return $point;
        }, array_values($outline));
    }

    /** گرد کردن مختصات‌ها برای ذخیره تمیز در دیتابیس. */
    public static function round(array $outline, int $precision = 2): array
    {
        return array_map(function (array $point) use ($precision) {
            $point['x'] = round((float) ($point['x'] ?? 0), $precision);
            $point['y'] = round((float) ($point['y'] ?? 0), $precision);

            if (isset($point['cx'], $point['cy'])) {
                $point['cx'] = round((float) $point['cx'], $precision);
                $point['cy'] = round((float) $point['cy'], $precision);
            }

            return $point;
        }, array_values($outline));
    }

    /** بزرگ/کوچک کردن مسیر حول مبدأ. */
    public static function scale(array $outline, float $factorX, ?float $factorY = null): array
    {
        $factorY ??= $factorX;

        return array_map(function (array $point) use ($factorX, $factorY) {
            $point['x'] = round(((float) ($point['x'] ?? 0)) * $factorX, 3);
            $point['y'] = round(((float) ($point['y'] ?? 0)) * $factorY, 3);

            if (isset($point['cx'], $point['cy'])) {
                $point['cx'] = round(((float) $point['cx']) * $factorX, 3);
                $point['cy'] = round(((float) $point['cy']) * $factorY, 3);
            }

            return $point;
        }, array_values($outline));
    }

    /** آینه کردن افقی مسیر (برای نمایش قطعه قرینه). */
    public static function mirrorX(array $outline, float $axis = 0): array
    {
        $mirrored = array_map(function (array $point) use ($axis) {
            $point['x'] = round((2 * $axis) - ((float) ($point['x'] ?? 0)), 3);

            if (isset($point['cx'])) {
                $point['cx'] = round((2 * $axis) - ((float) $point['cx']), 3);
            }

            return $point;
        }, array_values($outline));

        return $mirrored;
    }

    /**
     * همه هندسه یک قطعه را جابه‌جا می‌کند تا گوشه بالا-چپ آن روی (margin, margin) بنشیند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function normalizePiece(array $piece, float $margin = 0.0): array
    {
        [$minX, $minY] = static::bounds($piece['outline'] ?? []);
        $dx = $margin - $minX;
        $dy = $margin - $minY;

        return static::translatePiece($piece, $dx, $dy);
    }

    /* ---------------------------------------------------------------------
     |  چرخش، برش لبه و پرس‌وجوی هندسی (افزوده برای عملیات الگوسازی)
     * ------------------------------------------------------------------- */

    /**
     * چرخاندن یک نقطه حول یک مرکز.
     *
     * زاویه بر حسب رادیان است و چون محور y به پایین می‌رود، زاویه مثبت روی کاغذ
     * ساعت‌گرد دیده می‌شود. نقطه کنترل منحنی (در صورت وجود) هم با نقطه می‌چرخد.
     */
    public static function rotatePoint(array $point, array $center, float $angle): array
    {
        $cos = cos($angle);
        $sin = sin($angle);
        $cxx = (float) ($center['x'] ?? 0);
        $cyy = (float) ($center['y'] ?? 0);

        $spin = function (float $x, float $y) use ($cxx, $cyy, $cos, $sin): array {
            $dx = $x - $cxx;
            $dy = $y - $cyy;

            return [
                round($cxx + ($dx * $cos) - ($dy * $sin), 4),
                round($cyy + ($dx * $sin) + ($dy * $cos), 4),
            ];
        };

        [$x, $y] = $spin((float) ($point['x'] ?? 0), (float) ($point['y'] ?? 0));
        $point['x'] = $x;
        $point['y'] = $y;

        if (isset($point['cx'], $point['cy'])) {
            [$cx, $cy] = $spin((float) $point['cx'], (float) $point['cy']);
            $point['cx'] = $cx;
            $point['cy'] = $cy;
        }

        return $point;
    }

    /** چرخاندن یک مسیر کامل حول یک مرکز. */
    public static function rotate(array $outline, array $center, float $angle): array
    {
        return array_map(fn (array $point) => static::rotatePoint($point, $center, $angle), array_values($outline));
    }

    /**
     * اعمال یک تبدیل روی همهٔ نقطه‌های هندسی قطعه (مسیر، راستای پارچه، ساسون،
     * نشانه، مته و خط‌های نشانه). تابع ورودی یک نقطه می‌گیرد و نقطه تازه می‌دهد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function mapPiece(array $piece, callable $transform): array
    {
        if (isset($piece['outline']) && is_array($piece['outline'])) {
            $piece['outline'] = array_map(fn (array $point) => $transform($point), array_values($piece['outline']));
        }

        if (! empty($piece['grainline']['from']) && ! empty($piece['grainline']['to'])) {
            $piece['grainline']['from'] = $transform($piece['grainline']['from']);
            $piece['grainline']['to'] = $transform($piece['grainline']['to']);
        }

        foreach (['darts', 'notches', 'drills', 'pleats', 'markers'] as $key) {
            if (empty($piece[$key]) || ! is_array($piece[$key])) {
                continue;
            }

            $piece[$key] = array_map(function ($item) use ($transform) {
                if (! is_array($item)) {
                    return $item;
                }

                if (isset($item['x'], $item['y'])) {
                    $item = $transform($item);
                }

                foreach (['apex', 'apex_lower', 'from', 'to', 'center'] as $sub) {
                    if (isset($item[$sub]['x'], $item[$sub]['y'])) {
                        $item[$sub] = $transform($item[$sub]);
                    }
                }

                if (isset($item['legs']) && is_array($item['legs'])) {
                    $item['legs'] = array_map(
                        fn ($leg) => is_array($leg) && isset($leg['x'], $leg['y']) ? $transform($leg) : $leg,
                        $item['legs'],
                    );
                }

                return $item;
            }, $piece[$key]);
        }

        return $piece;
    }

    /**
     * چرخاندن همهٔ اجزای یک قطعه حول یک مرکز.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function rotatePiece(array $piece, array $center, float $angle): array
    {
        return static::mapPiece($piece, fn (array $point) => static::rotatePoint($point, $center, $angle));
    }

    /** زاویه (رادیان) از نقطه $from به نقطه $to دیده‌شده از $center. */
    public static function angleBetween(array $center, array $from, array $to): float
    {
        $a = atan2(((float) $from['y']) - ((float) $center['y']), ((float) $from['x']) - ((float) $center['x']));
        $b = atan2(((float) $to['y']) - ((float) $center['y']), ((float) $to['x']) - ((float) $center['x']));
        $angle = $b - $a;

        while ($angle > M_PI) {
            $angle -= 2 * M_PI;
        }

        while ($angle < -M_PI) {
            $angle += 2 * M_PI;
        }

        return $angle;
    }

    /**
     * شکستن یک منحنی درجه‌دو در نسبت t به دو منحنی (الگوریتم دو کاستلژو).
     *
     * انحنا دقیقاً حفظ می‌شود؛ خروجی:
     * ['point' => نقطه برش, 'first' => نقطه کنترل نیمه اول, 'second' => نقطه کنترل نیمه دوم]
     */
    public static function splitQuadratic(array $p0, array $control, array $p1, float $t): array
    {
        $first = static::lerp($p0, $control, $t);
        $second = static::lerp($control, $p1, $t);
        $point = static::lerp($first, $second, $t);

        return ['point' => $point, 'first' => $first, 'second' => $second];
    }

    /**
     * افزودن یک رأس روی لبه شماره $index در نسبت $t.
     *
     * اگر لبه منحنی باشد با دو کاستلژو شکسته می‌شود تا شکل منحنی عوض نشود.
     * خروجی مسیر تازه است؛ رأس افزوده‌شده در جای $index + 1 می‌نشیند.
     */
    public static function splitEdgeAt(array $outline, int $index, float $t): array
    {
        $outline = array_values($outline);
        $count = count($outline);

        if ($count < 2) {
            return $outline;
        }

        $index = (($index % $count) + $count) % $count;
        $t = max(0.0, min(1.0, $t));
        $from = static::points([$outline[$index]])[0];
        $nextIndex = ($index + 1) % $count;
        $target = $outline[$nextIndex];

        if (! static::isCurve($target)) {
            $point = static::lerp($from, ['x' => (float) $target['x'], 'y' => (float) $target['y']], $t);
            $inserted = static::point($point['x'], $point['y']);
        } else {
            $split = static::splitQuadratic(
                $from,
                ['x' => (float) $target['cx'], 'y' => (float) $target['cy']],
                ['x' => (float) $target['x'], 'y' => (float) $target['y']],
                $t,
            );

            $inserted = static::curve($split['point']['x'], $split['point']['y'], $split['first']['x'], $split['first']['y']);
            $target['cx'] = round($split['second']['x'], 3);
            $target['cy'] = round($split['second']['y'], 3);
            $outline[$nextIndex] = $target;
        }

        array_splice($outline, $index + 1, 0, [$inserted]);

        return $outline;
    }

    /** نسبت t روی لبه $index که نزدیک‌ترین نقطه به $point را می‌دهد. */
    public static function edgeParameterOf(array $outline, int $index, array $point, int $samples = 64): float
    {
        $best = 0.0;
        $bestDistance = INF;

        for ($i = 0; $i <= $samples; $i++) {
            $t = $i / $samples;
            $distance = static::distance(static::pointOnEdge($outline, $index, $t), $point);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $t;
            }
        }

        $step = 1 / $samples;

        for ($round = 0; $round < 30; $round++) {
            $step /= 2;
            $low = max(0.0, $best - $step);
            $high = min(1.0, $best + $step);
            $lowDistance = static::distance(static::pointOnEdge($outline, $index, $low), $point);
            $highDistance = static::distance(static::pointOnEdge($outline, $index, $high), $point);

            if ($lowDistance < $bestDistance && $lowDistance <= $highDistance) {
                $best = $low;
                $bestDistance = $lowDistance;
            } elseif ($highDistance < $bestDistance) {
                $best = $high;
                $bestDistance = $highDistance;
            }
        }

        return round($best, 6);
    }

    /**
     * نزدیک‌ترین لبه به یک نقطه.
     *
     * @return array{edge: int, t: float, distance: float, point: array{x: float, y: float}}
     */
    public static function nearestEdge(array $outline, array $point): array
    {
        $outline = array_values($outline);
        $count = count($outline);
        $best = ['edge' => 0, 't' => 0.0, 'distance' => INF, 'point' => ['x' => 0.0, 'y' => 0.0]];

        for ($edge = 0; $edge < $count; $edge++) {
            $t = static::edgeParameterOf($outline, $edge, $point, 24);
            $on = static::pointOnEdge($outline, $edge, $t);
            $distance = static::distance($on, $point);

            if ($distance < $best['distance']) {
                $best = ['edge' => $edge, 't' => $t, 'distance' => round($distance, 4), 'point' => $on];
            }
        }

        return $best;
    }

    /** مرکز سطح (نه میانگین رأس‌ها) یک مسیر بسته. */
    public static function centroid(array $outline): array
    {
        $points = static::flatten($outline);
        $count = count($points);
        $area = static::signedArea($points);

        if ($count < 3 || abs($area) < 1e-9) {
            $xs = array_column($points, 'x');
            $ys = array_column($points, 'y');

            return $points === []
                ? ['x' => 0.0, 'y' => 0.0]
                : ['x' => array_sum($xs) / $count, 'y' => array_sum($ys) / $count];
        }

        $cx = 0.0;
        $cy = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];
            $cross = ($a['x'] * $b['y']) - ($b['x'] * $a['y']);
            $cx += ($a['x'] + $b['x']) * $cross;
            $cy += ($a['y'] + $b['y']) * $cross;
        }

        return ['x' => round($cx / (6 * $area), 4), 'y' => round($cy / (6 * $area), 4)];
    }

    /** آیا نقطه درون مسیر بسته است؟ (پرتاب پرتو) */
    public static function containsPoint(array $outline, array $point): bool
    {
        $points = static::flatten($outline);
        $count = count($points);
        $x = (float) $point['x'];
        $y = (float) $point['y'];
        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $points[$i]['x'];
            $yi = $points[$i]['y'];
            $xj = $points[$j]['x'];
            $yj = $points[$j]['y'];

            if ((($yi > $y) !== ($yj > $y))
                && ($x < ((($xj - $xi) * ($y - $yi)) / (($yj - $yi) ?: 1e-12)) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** آیا مسیر خودش را قطع می‌کند؟ (قطعه‌ای که خودش را قطع کند بریدنی نیست) */
    public static function selfIntersects(array $outline, float $epsilon = 1e-6): bool
    {
        $points = static::flatten($outline);
        $count = count($points);

        if ($count < 4) {
            return false;
        }

        for ($i = 0; $i < $count; $i++) {
            $a1 = $points[$i];
            $a2 = $points[($i + 1) % $count];

            for ($j = $i + 2; $j < $count; $j++) {
                if ($i === 0 && $j === $count - 1) {
                    continue; // لبه‌های همسایه
                }

                $b1 = $points[$j];
                $b2 = $points[($j + 1) % $count];

                if (static::segmentsCross($a1, $a2, $b1, $b2, $epsilon)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** برخورد واقعی دو پاره‌خط (تماس در نقطه انتهایی مشترک برخورد حساب نمی‌شود). */
    public static function segmentsCross(array $a1, array $a2, array $b1, array $b2, float $epsilon = 1e-6): bool
    {
        $cross = fn (array $o, array $p, array $q) => (($p['x'] - $o['x']) * ($q['y'] - $o['y']))
            - (($p['y'] - $o['y']) * ($q['x'] - $o['x']));

        $d1 = $cross($b1, $b2, $a1);
        $d2 = $cross($b1, $b2, $a2);
        $d3 = $cross($a1, $a2, $b1);
        $d4 = $cross($a1, $a2, $b2);

        if ((($d1 > $epsilon && $d2 < -$epsilon) || ($d1 < -$epsilon && $d2 > $epsilon))
            && (($d3 > $epsilon && $d4 < -$epsilon) || ($d3 < -$epsilon && $d4 > $epsilon))) {
            return true;
        }

        return false;
    }

    /* ---------------------------------------------------------------------
     |  برچسب لبه‌ها و درستی‌سنجی قطعه
     * ------------------------------------------------------------------- */

    /**
     * برچسب همه لبه‌های یک قطعه، هم‌اندازه با تعداد نقطه‌های مسیر.
     *
     * @return array<int, string>
     */
    public static function edgeTags(array $piece): array
    {
        $count = count($piece['outline'] ?? []);
        $tags = array_values($piece['meta']['edges'] ?? []);

        for ($i = 0; $i < $count; $i++) {
            $tags[$i] = isset($tags[$i]) && is_string($tags[$i]) && $tags[$i] !== '' ? $tags[$i] : 'default';
        }

        return array_slice($tags, 0, max(0, $count));
    }

    /** @return array<int, int> شماره همه لبه‌هایی که این برچسب را دارند. */
    public static function edgesWithTag(array $piece, string $tag): array
    {
        $found = [];

        foreach (static::edgeTags($piece) as $index => $value) {
            if ($value === $tag) {
                $found[] = $index;
            }
        }

        return $found;
    }

    /** شماره نخستین لبه با این برچسب. */
    public static function edgeWithTag(array $piece, string $tag): ?int
    {
        return static::edgesWithTag($piece, $tag)[0] ?? null;
    }

    /**
     * درستی‌سنجی یک قطعه: فهرست ایرادها به فارسی (آرایه خالی یعنی سالم است).
     *
     * هر عملیات الگوسازی باید قطعه‌ای بدهد که از این آزمون سربلند بیرون بیاید.
     *
     * @param  array<string, mixed>  $piece
     * @return array<int, string>
     */
    public static function validatePiece(array $piece, float $minArea = 1.0): array
    {
        $problems = [];
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);

        if ($count < 3) {
            return ['مسیر قطعه کمتر از سه نقطه دارد و بسته نمی‌شود.'];
        }

        foreach ($outline as $index => $point) {
            foreach (['x', 'y', 'cx', 'cy'] as $key) {
                if (isset($point[$key]) && ! is_finite((float) $point[$key])) {
                    $problems[] = "نقطه شماره {$index} مختصات نامعتبر دارد.";
                }
            }

            if (static::isCurve($point) && (! isset($point['cx']) || ! isset($point['cy']))) {
                $problems[] = "نقطه شماره {$index} منحنی است ولی نقطه کنترل ندارد.";
            }
        }

        if (static::area($outline) < $minArea) {
            $problems[] = 'مساحت قطعه تقریباً صفر است.';
        }

        if (static::selfIntersects($outline)) {
            $problems[] = 'مسیر قطعه خودش را قطع می‌کند.';
        }

        $edges = $piece['meta']['edges'] ?? null;

        if ($edges !== null && count($edges) !== $count) {
            $problems[] = 'تعداد برچسب لبه‌ها با تعداد لبه‌های مسیر یکی نیست.';
        }

        foreach ($piece['meta']['fold_edges'] ?? [] as $edge) {
            if (! is_int($edge) || $edge < 0 || $edge >= $count) {
                $problems[] = 'شماره لبه تای پارچه بیرون از مسیر است.';
            }
        }

        foreach ($piece['darts'] ?? [] as $index => $dart) {
            $edge = $dart['edge'] ?? null;

            if ($edge !== null && (! is_int($edge) || $edge < 0 || $edge >= $count)) {
                $problems[] = "ساسون شماره {$index} به لبه‌ای بیرون از مسیر اشاره می‌کند.";
            }

            if (((float) ($dart['intake'] ?? 0)) < 0) {
                $problems[] = "ساسون شماره {$index} پهنای منفی دارد.";
            }

            if (count($dart['legs'] ?? []) !== 2) {
                $problems[] = "ساسون شماره {$index} دو پا ندارد.";
            }

            if (! isset($dart['apex']['x'], $dart['apex']['y'])) {
                $problems[] = "ساسون شماره {$index} نوک ندارد.";
            }
        }

        foreach ($piece['notches'] ?? [] as $index => $notch) {
            $edge = $notch['edge'] ?? null;

            if ($edge !== null && (! is_int($edge) || $edge < 0 || $edge >= $count)) {
                $problems[] = "نشانه شماره {$index} به لبه‌ای بیرون از مسیر اشاره می‌کند.";
            }
        }

        return $problems;
    }

    /** خلاصه درستی‌سنجی: قطعه سالم است یا نه. */
    public static function isValidPiece(array $piece, float $minArea = 1.0): bool
    {
        return static::validatePiece($piece, $minArea) === [];
    }

    /**
     * جابه‌جایی همه اجزای هندسی یک قطعه با هم.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function translatePiece(array $piece, float $dx, float $dy): array
    {
        if ($dx === 0.0 && $dy === 0.0) {
            return $piece;
        }

        $movePoint = fn (array $p) => [
            'x' => round(((float) ($p['x'] ?? 0)) + $dx, 3),
            'y' => round(((float) ($p['y'] ?? 0)) + $dy, 3),
        ] + $p;

        if (isset($piece['outline'])) {
            $piece['outline'] = static::translate($piece['outline'], $dx, $dy);
        }

        if (! empty($piece['grainline']['from']) && ! empty($piece['grainline']['to'])) {
            $piece['grainline'] = [
                'from' => $movePoint($piece['grainline']['from']),
                'to' => $movePoint($piece['grainline']['to']),
            ] + $piece['grainline'];
        }

        foreach (['darts', 'notches', 'drills', 'pleats', 'markers'] as $key) {
            if (empty($piece[$key]) || ! is_array($piece[$key])) {
                continue;
            }

            $piece[$key] = array_map(function ($item) use ($movePoint) {
                if (! is_array($item)) {
                    return $item;
                }

                if (isset($item['x'], $item['y'])) {
                    $item = $movePoint($item);
                }

                foreach (['apex', 'from', 'to', 'center'] as $sub) {
                    if (isset($item[$sub]['x'], $item[$sub]['y'])) {
                        $item[$sub] = $movePoint($item[$sub]);
                    }
                }

                if (isset($item['legs']) && is_array($item['legs'])) {
                    $item['legs'] = array_map(fn ($leg) => is_array($leg) && isset($leg['x'], $leg['y'])
                        ? $movePoint($leg)
                        : $leg, $item['legs']);
                }

                return $item;
            }, $piece[$key]);
        }

        return $piece;
    }
}
