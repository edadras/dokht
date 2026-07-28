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
