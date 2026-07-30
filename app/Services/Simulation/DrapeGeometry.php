<?php

namespace App\Services\Simulation;

use App\Services\Pattern\Geometry;

/**
 * پل میان «مسیر الگو» و «خط شکسته‌ای که مرورگر مثلث‌بندی می‌کند».
 *
 * مرورگر با رأس کار می‌کند و الگو با لبه؛ این کلاس همان تبدیل را انجام می‌دهد و
 * مهم‌تر از آن، حساب نگه می‌دارد که هر لبه اصلی به کدام بازه از رأس‌ها تبدیل شده
 * است. بدون این حساب، دو لبه درز در مرورگر پیدا نمی‌شوند.
 *
 * قرارداد جهت: چندضلعی خروجی همیشه با مساحت علامت‌دار مثبت (روی همان عددهای خام
 * بسته) ساخته می‌شود. چون مرورگر محور y را قرینه می‌کند، این همان پادساعتگردِ
 * دستگاه سه‌بعدی است و بردار عمود مثلث‌ها رو به بیرون می‌ماند.
 *
 * @internal
 */
final class DrapeGeometry
{
    /** بلندترین پاره‌خطِ یک لبهٔ راست روی خط شکستهٔ دوخت، سانتی‌متر. */
    public const STRAIGHT_STEP = 5.0;

    /**
     * خط شکسته یک مسیر، به همراه بازه رأس هر لبه اصلی.
     *
     * منطق اینجا مو‌به‌مو همان Geometry::flatten است — تنها فرقش این است که
     * می‌گوید هر لبه از کدام رأس تا کدام رأس رفته. نکته ظریفش لبه بسته‌شونده است:
     * اگر خط راست باشد رأس تازه‌ای نمی‌سازد و اگر منحنی باشد رأس پایانی‌اش همان
     * رأس صفر است؛ در هر دو حال end آن لبه صفر می‌شود.
     *
     * لبهٔ راست هم شکسته می‌شود، و این تفاوتِ دومش با Geometry::flatten است.
     * چرا: درز روی رأس بریده می‌شود، پس لبه‌ای که رأس میانی ندارد اصلاً قابل
     * تقسیم نیست. خط یقهٔ یقهٔ پیراهن یک پاره‌خطِ راستِ ۲۵٫۸ سانتی‌متری بود؛
     * باید میان تنهٔ جلو و یوک تقسیم می‌شد و نمی‌شد، پس همهٔ ۲۵٫۸ روی خط یقهٔ
     * ۱۴٫۴ سانتی‌متریِ جلو می‌رفت و تنهٔ دیگر بی‌یقه می‌ماند. روی مانکن یقه
     * وارونه و چین‌خورده دیده می‌شد. با رأس هر ۳ سانتی‌متر، هر درزی جای بریدن
     * دارد و مرزِ مثلث‌بندی هم یک‌دست می‌شود.
     *
     * @return array{polygon: array<int, array{x: float, y: float}>, spans: array<int, array{0: int, 1: int}>}
     */
    public static function flattenWithSpans(
        array $outline,
        int $segments = Geometry::CURVE_SEGMENTS,
        float $step = self::STRAIGHT_STEP,
        array $split = [],
    ): array {
        $outline = array_values($outline);
        $count = count($outline);

        if ($count === 0) {
            return ['polygon' => [], 'spans' => []];
        }

        $polygon = [['x' => (float) ($outline[0]['x'] ?? 0), 'y' => (float) ($outline[0]['y'] ?? 0)]];
        $spans = [];
        $cursor = 0;

        for ($i = 1; $i <= $count; $i++) {
            $target = $outline[$i % $count];
            $from = $polygon[count($polygon) - 1];
            $edge = $i - 1;

            if ($i === $count && ! Geometry::isCurve($target)) {
                foreach (static::stepsBetween($from, $target, ($split[$edge] ?? false) ? $step : 0.0) as $point) {
                    $polygon[] = $point;
                }

                $spans[$edge] = [$cursor, 0];

                break;
            }

            if (Geometry::isCurve($target)) {
                for ($s = 1; $s <= $segments; $s++) {
                    $polygon[] = Geometry::quadraticAt(
                        $from,
                        ['x' => (float) $target['cx'], 'y' => (float) $target['cy']],
                        ['x' => (float) $target['x'], 'y' => (float) $target['y']],
                        $s / $segments,
                    );
                }

                if ($i === $count) {
                    array_pop($polygon);
                    $end = 0;
                } else {
                    $end = count($polygon) - 1;
                }
            } else {
                foreach (static::stepsBetween($from, $target, ($split[$edge] ?? false) ? $step : 0.0) as $point) {
                    $polygon[] = $point;
                }

                $polygon[] = ['x' => (float) $target['x'], 'y' => (float) $target['y']];
                $end = count($polygon) - 1;
            }

            $spans[$edge] = [$cursor, $end];
            $cursor = $end;
        }

        ksort($spans);

        return ['polygon' => $polygon, 'spans' => $spans];
    }

    /**
     * رأس‌های میانیِ یک پاره‌خطِ راست — بدون خودِ دو سر.
     *
     * @param  array{x: float, y: float}  $from
     * @param  array<string, mixed>  $to
     * @return array<int, array{x: float, y: float}>
     */
    protected static function stepsBetween(array $from, array $to, float $step): array
    {
        if ($step <= 0.0) {
            return [];
        }

        $x = (float) ($to['x'] ?? 0);
        $y = (float) ($to['y'] ?? 0);
        $length = hypot($x - $from['x'], $y - $from['y']);
        $parts = (int) min(24, floor($length / $step));

        if ($parts < 2) {
            return [];
        }

        $out = [];

        for ($k = 1; $k < $parts; $k++) {
            $t = $k / $parts;

            $out[] = [
                'x' => $from['x'] + (($x - $from['x']) * $t),
                'y' => $from['y'] + (($y - $from['y']) * $t),
            ];
        }

        return $out;
    }

    /**
     * طول کمانِ خط شکسته از رأس $from تا رأس $to (رو به جلو، با پیچش دور مسیر).
     *
     * @param  array<int, array{x: float, y: float}>  $polygon
     */
    public static function arcLength(array $polygon, int $from, int $to): float
    {
        $count = count($polygon);

        if ($count < 2) {
            return 0.0;
        }

        $total = 0.0;
        $at = (($from % $count) + $count) % $count;
        $end = (($to % $count) + $count) % $count;
        $guard = 0;

        while ($at !== $end && $guard++ <= $count) {
            $next = ($at + 1) % $count;
            $total += Geometry::distance($polygon[$at], $polygon[$next]);
            $at = $next;
        }

        return $total;
    }

    /** نقطه میانی یک کمان: میانگین رأس‌های روی آن. */
    public static function arcMidpoint(array $polygon, int $from, int $to): array
    {
        $count = count($polygon);

        if ($count === 0) {
            return ['x' => 0.0, 'y' => 0.0];
        }

        $at = (($from % $count) + $count) % $count;
        $end = (($to % $count) + $count) % $count;
        $sumX = 0.0;
        $sumY = 0.0;
        $seen = 0;
        $guard = 0;

        while ($guard++ <= $count) {
            $sumX += $polygon[$at]['x'];
            $sumY += $polygon[$at]['y'];
            $seen++;

            if ($at === $end) {
                break;
            }

            $at = ($at + 1) % $count;
        }

        return ['x' => $sumX / max(1, $seen), 'y' => $sumY / max(1, $seen)];
    }

    /**
     * برعکس کردن جهت پیمایش یک مسیر بسته.
     *
     * لبه شماره k مسیر تازه، همان لبه شماره n-1-k مسیر کهنه است که وارونه پیموده
     * می‌شود. نقطه کنترل منحنی درجه‌دو زیر وارون‌شدن عوض نمی‌شود، فقط باید روی
     * نقطه دیگری بنشیند: هر رأس، انحنای رأسِ بعدیِ مسیر کهنه را برمی‌دارد.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function reverseOutline(array $outline): array
    {
        $outline = array_values($outline);
        $count = count($outline);

        if ($count < 3) {
            return $outline;
        }

        $out = [];

        for ($j = 0; $j < $count; $j++) {
            $position = $outline[($count - $j) % $count];
            $source = $outline[($count - $j + 1) % $count];

            $point = ['x' => (float) ($position['x'] ?? 0), 'y' => (float) ($position['y'] ?? 0)];

            if (Geometry::isCurve($source)) {
                $point['curve'] = true;
                $point['cx'] = (float) $source['cx'];
                $point['cy'] = (float) $source['cy'];
            }

            $out[] = $point;
        }

        return $out;
    }

    /**
     * برعکس کردن فهرستی که یک درایه به ازای هر لبه دارد (برچسب یا شماره لبه اصلی).
     *
     * @param  array<int, mixed>  $perEdge
     * @return array<int, mixed>
     */
    public static function reversePerEdge(array $perEdge): array
    {
        $perEdge = array_values($perEdge);
        $count = count($perEdge);
        $out = [];

        for ($k = 0; $k < $count; $k++) {
            $out[$k] = $perEdge[$count - 1 - $k];
        }

        return $out;
    }

    /**
     * آینه کردن کل یک قطعه حول محور عمودی.
     *
     * همه هندسه قطعه (مسیر، ساسون، نشانه، مته) با هم قرینه می‌شود و چون آینه‌کردن
     * جهت پیمایش را وارون می‌کند، مسیر دوباره برعکس می‌شود تا جهت سر جایش بماند.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<int, mixed>  $perEdge  فهرست هم‌اندازه لبه‌ها که با مسیر برعکس می‌شود
     * @return array{piece: array<string, mixed>, per_edge: array<int, mixed>}
     */
    public static function mirrorPiece(array $piece, array $perEdge, float $axis = 0.0): array
    {
        $flip = function (array $point) use ($axis): array {
            $point['x'] = round((2 * $axis) - ((float) ($point['x'] ?? 0)), 4);

            if (isset($point['cx'])) {
                $point['cx'] = round((2 * $axis) - ((float) $point['cx']), 4);
            }

            return $point;
        };

        $piece = Geometry::mapPiece($piece, $flip);
        $piece['outline'] = self::reverseOutline($piece['outline'] ?? []);

        return ['piece' => $piece, 'per_edge' => self::reversePerEdge($perEdge)];
    }

    /**
     * اگر مسیر در جهت اشتباه بسته شده باشد، برش می‌گرداند.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<int, mixed>  $perEdge
     * @return array{piece: array<string, mixed>, per_edge: array<int, mixed>, flipped: bool}
     */
    public static function orient(array $piece, array $perEdge): array
    {
        $area = Geometry::signedArea(Geometry::flatten($piece['outline'] ?? []));

        if ($area >= 0) {
            return ['piece' => $piece, 'per_edge' => $perEdge, 'flipped' => false];
        }

        $piece['outline'] = self::reverseOutline($piece['outline'] ?? []);

        return ['piece' => $piece, 'per_edge' => self::reversePerEdge($perEdge), 'flipped' => true];
    }

    /**
     * بازه‌های پشت‌سرهم لبه‌هایی که به مجموعه خواسته‌شده تعلق دارند.
     *
     * یک درز روی قطعه باز‌شده دو بار می‌آید (یکی روی هر نیمه)، پس خروجی فهرست
     * است نه یک کمان.
     *
     * @param  array<int, int|null>  $origins  شماره لبه اصلی هر لبه نهایی
     * @param  array<int, int>  $wanted
     * @return array<int, array<int, int>> فهرست دنباله‌های لبه نهایی
     */
    public static function runs(array $origins, array $wanted): array
    {
        $count = count($origins);

        if ($count === 0 || $wanted === []) {
            return [];
        }

        $want = array_flip(array_map('intval', $wanted));
        $inside = [];

        for ($i = 0; $i < $count; $i++) {
            $inside[$i] = $origins[$i] !== null && isset($want[$origins[$i]]);
        }

        $anyIn = in_array(true, $inside, true);
        $anyOut = in_array(false, $inside, true);

        if (! $anyIn) {
            return [];
        }

        if (! $anyOut) {
            return [range(0, $count - 1)];
        }

        $runs = [];

        for ($i = 0; $i < $count; $i++) {
            if (! $inside[$i] || $inside[($i - 1 + $count) % $count]) {
                continue;
            }

            $run = [];
            $at = $i;

            while ($inside[$at] && count($run) < $count) {
                $run[] = $at;
                $at = ($at + 1) % $count;
            }

            $runs[] = $run;
        }

        return $runs;
    }
}
