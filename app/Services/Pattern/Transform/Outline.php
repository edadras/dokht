<?php

namespace App\Services\Pattern\Transform;

use App\Services\Pattern\Geometry;
use InvalidArgumentException;

/**
 * ابزار درونی کار با مسیر قطعه.
 *
 * این کلاس بخش «دفترداری» عملیات الگوسازی است: پیدا کردن نقطه لنگر روی لبه،
 * شکستن لبه‌ها، جدا کردن زنجیره نقطه‌ها، برعکس کردن یک زنجیره بدون خراب شدن
 * منحنی‌ها و شماره‌گذاری دوباره ساسون و نشانه‌ها. کلاس‌های عمومی این پوشه
 * (DartTool، SlashSpread، StyleLineCutter، PieceOps) روی همین ابزار سوارند.
 *
 * @internal
 */
final class Outline
{
    /** فاصله‌ای که کمتر از آن، دو نقطه یکی حساب می‌شوند (سانتی‌متر). */
    public const EPSILON = 1e-4;

    /**
     * تبدیل «مشخصات لنگر» به لبه و نسبت روی آن.
     *
     * مشخصات می‌تواند یکی از این‌ها باشد:
     *   ['edge' => 2, 't' => 0.4]           لبه شماره ۲، چهل‌درصد آن
     *   ['edge' => 'shoulder', 't' => 0.5]  نخستین لبه با برچسب سرشانه
     *   ['x' => .., 'y' => ..]              نزدیک‌ترین جای مسیر به این نقطه
     *   ['at' => ['x' => .., 'y' => ..]]    همان بالا
     *
     * @return array{edge: int, t: float, point: array{x: float, y: float}}
     */
    public static function anchor(array $piece, array $spec): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);

        if ($count < 3) {
            throw new InvalidArgumentException('مسیر قطعه برای پیدا کردن لنگر کوتاه است.');
        }

        $point = $spec['at'] ?? $spec['point'] ?? (isset($spec['x'], $spec['y']) ? $spec : null);

        if ($point !== null && ! isset($spec['edge'])) {
            $near = Geometry::nearestEdge($outline, ['x' => (float) $point['x'], 'y' => (float) $point['y']]);

            return ['edge' => $near['edge'], 't' => $near['t'], 'point' => $near['point']];
        }

        $edge = $spec['edge'] ?? null;

        if (is_string($edge)) {
            $found = Geometry::edgesWithTag($piece, $edge);

            if ($found === []) {
                throw new InvalidArgumentException("لبه‌ای با برچسب «{$edge}» روی این قطعه نیست.");
            }

            $edge = $found[(int) ($spec['nth'] ?? 0)] ?? $found[0];
        }

        if (! is_int($edge)) {
            throw new InvalidArgumentException('مشخصات لنگر باید لبه یا نقطه داشته باشد.');
        }

        $edge = (($edge % $count) + $count) % $count;

        $t = $point !== null
            ? Geometry::edgeParameterOf($outline, $edge, ['x' => (float) $point['x'], 'y' => (float) $point['y']])
            : (float) ($spec['t'] ?? 0.5);

        $t = max(0.0, min(1.0, $t));

        return ['edge' => $edge, 't' => $t, 'point' => Geometry::pointOnEdge($outline, $edge, $t)];
    }

    /**
     * افزودن چند رأس تازه به مسیر و بازگرداندن شماره هرکدام.
     *
     * لبه‌ها از انتها به ابتدا شکسته می‌شوند تا شماره‌ها به هم نریزد. اگر چند لنگر
     * روی یک لبه بنشینند، هر شکستن لبه را کوتاه می‌کند؛ پس نسبت لنگر بعدی باید
     * نسبت به همان لبه کوتاه‌شده حساب شود، وگرنه نقطه‌ها روی هم می‌لغزند (لنگر
     * ۰٫۲۵ بعد از شکستن در ۰٫۷۵ روی ۰٫۱۸۷۵ می‌افتاد). دو لنگر با نسبت یکسان هم
     * یک رأس مشترک می‌گیرند، نه دو رأس چسبیده به هم.
     *
     * @param  array<int, array{edge: int, t: float}>  $anchors
     * @return array{outline: array<int, array<string, mixed>>, tags: array<int, string>, index: array<int|string, int>}
     */
    public static function insertAnchors(array $outline, array $tags, array $anchors): array
    {
        $outline = array_values($outline);
        $tags = array_values($tags);
        $order = array_keys($anchors);

        usort($order, fn ($a, $b) => [$anchors[$b]['edge'], $anchors[$b]['t']] <=> [$anchors[$a]['edge'], $anchors[$a]['t']]);

        $index = [];
        $bounds = []; // شماره لبه ⇒ کوچک‌ترین نسبتی که تا کنون روی آن لبه شکسته شده

        foreach ($order as $key) {
            $edge = (int) $anchors[$key]['edge'];
            $t = (float) $anchors[$key]['t'];
            $bound = $bounds[$edge] ?? 1.0;
            $local = $bound > 1e-9 ? $t / $bound : 0.0;
            $inserted = true;

            if ($local <= 1e-6) {
                $position = $edge;
                $inserted = false;
            } elseif ($local >= 1 - 1e-6) {
                $position = $edge + 1;
                $inserted = false;
            } else {
                $outline = Geometry::splitEdgeAt($outline, $edge, $local);
                array_splice($tags, $edge + 1, 0, [$tags[$edge] ?? 'default']);
                $position = $edge + 1;
                $bounds[$edge] = $t;
            }

            if ($inserted) {
                foreach ($index as $other => $at) {
                    if ($at > $edge) {
                        $index[$other] = $at + 1;
                    }
                }
            }

            $index[$key] = $position % max(1, count($outline));
        }

        return ['outline' => $outline, 'tags' => $tags, 'index' => $index];
    }

    /**
     * زنجیره نقطه‌ها از رأس $from تا رأس $to (هر دو شامل)، در جهت مسیر.
     *
     * اطلاعات منحنیِ نخستین نقطه پاک می‌شود چون لبه رسیدن به آن بیرون زنجیره است.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function chain(array $outline, int $from, int $to, bool $full = false): array
    {
        $outline = array_values($outline);
        $count = count($outline);

        if ($count === 0) {
            return [];
        }

        $from = (($from % $count) + $count) % $count;
        $to = (($to % $count) + $count) % $count;
        $steps = ((($to - $from) % $count) + $count) % $count;

        if ($steps === 0 && $full) {
            $steps = $count;
        }

        $chain = [];

        for ($step = 0; $step <= $steps; $step++) {
            $point = $outline[($from + $step) % $count];
            $chain[] = $step === 0 ? self::straighten($point) : $point;
        }

        return $chain;
    }

    /**
     * برعکس کردن یک زنجیره؛ نقطه کنترل هر منحنی به نقطه درست خودش منتقل می‌شود.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function reverse(array $chain): array
    {
        $chain = array_values($chain);
        $count = count($chain);
        $out = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $point = ['x' => (float) $chain[$i]['x'], 'y' => (float) $chain[$i]['y']];

            if ($i < $count - 1 && Geometry::isCurve($chain[$i + 1])) {
                $point['curve'] = true;
                $point['cx'] = (float) $chain[$i + 1]['cx'];
                $point['cy'] = (float) $chain[$i + 1]['cy'];
            }

            $out[] = $point;
        }

        return $out;
    }

    /** پاک کردن اطلاعات منحنی از یک نقطه (وقتی نقطه سرِ زنجیره می‌شود). */
    public static function straighten(array $point): array
    {
        return ['x' => (float) ($point['x'] ?? 0), 'y' => (float) ($point['y'] ?? 0)];
    }

    /** آیا دو نقطه یکی‌اند؟ */
    public static function same(array $a, array $b, float $epsilon = self::EPSILON): bool
    {
        return Geometry::distance(
            ['x' => (float) $a['x'], 'y' => (float) $a['y']],
            ['x' => (float) $b['x'], 'y' => (float) $b['y']],
        ) <= $epsilon;
    }

    /**
     * چسباندن چند زنجیره به هم و ساختن یک مسیر بسته.
     *
     * اگر نقطه پایانی یک زنجیره با نقطه آغاز زنجیره بعد یکی باشد، تکراری حذف
     * می‌شود. برچسب لبه‌ها هم‌زمان ساخته می‌شود.
     *
     * @param  array<int, array{points: array<int, array<string, mixed>>, tags: array<int, string>}>  $parts
     * @return array{outline: array<int, array<string, mixed>>, tags: array<int, string>}
     */
    public static function join(array $parts, string $closingTag = 'default'): array
    {
        $outline = [];
        $tags = []; // برچسب لبه‌ای که از نقطه هم‌شماره بیرون می‌رود

        foreach ($parts as $part) {
            $points = array_values($part['points'] ?? []);
            $partTags = array_values($part['tags'] ?? []);

            if ($points === []) {
                continue;
            }

            $start = 0;

            if ($outline !== [] && self::same($outline[count($outline) - 1], $points[0])) {
                $start = 1;
                $tags[count($outline) - 1] = $partTags[0] ?? ($part['bridge'] ?? 'default');
            } elseif ($outline !== []) {
                $tags[count($outline) - 1] = $part['bridge'] ?? ($partTags[0] ?? 'default');
            }

            for ($i = $start; $i < count($points); $i++) {
                $outline[] = $points[$i];
                $tags[count($outline) - 1] = $partTags[$i] ?? $closingTag;
            }
        }

        $count = count($outline);

        if ($count > 3 && self::same($outline[$count - 1], $outline[0])) {
            $dropped = array_pop($outline);
            unset($tags[$count - 1]);
            $count--;

            // انحنای لبه بسته‌شدن روی نقطه پایانی است و نباید با حذف آن گم شود
            if (Geometry::isCurve($dropped)) {
                $outline[0]['curve'] = true;
                $outline[0]['cx'] = $dropped['cx'];
                $outline[0]['cy'] = $dropped['cy'];
            }
        }

        $tags[$count - 1] = $closingTag;

        return self::dedupe($outline, array_values($tags));
    }

    /**
     * حذف رأس‌های تکراری پشت سر هم.
     *
     * @return array{outline: array<int, array<string, mixed>>, tags: array<int, string>}
     */
    public static function dedupe(array $outline, array $tags, float $epsilon = self::EPSILON): array
    {
        $outline = array_values($outline);
        $tags = array_values($tags);
        $count = count($outline);
        $points = [];
        $kept = [];

        for ($i = 0; $i < $count; $i++) {
            if ($points !== [] && self::same($points[count($points) - 1], $outline[$i], $epsilon)) {
                $kept[count($kept) - 1] = $tags[$i] ?? 'default';

                continue;
            }

            $points[] = $outline[$i];
            $kept[] = $tags[$i] ?? 'default';
        }

        $total = count($points);

        if ($total > 3 && self::same($points[$total - 1], $points[0], $epsilon)) {
            $dropped = array_pop($points);
            $last = array_pop($kept);
            $kept[count($kept) - 1] = $last;

            // لبه بسته‌شدن، منحنی خودش را روی نقطه پایانی نگه می‌دارد؛ چون آن نقطه
            // همان نقطه نخست است، انحنا باید به نقطه نخست منتقل شود وگرنه لبه صاف می‌شود.
            if (Geometry::isCurve($dropped)) {
                $points[0]['curve'] = true;
                $points[0]['cx'] = $dropped['cx'];
                $points[0]['cy'] = $dropped['cy'];
            }
        }

        return ['outline' => $points, 'tags' => $kept];
    }

    /**
     * نوشتن مسیر و برچسب‌های تازه روی قطعه و مرتب کردن آن.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function apply(array $piece, array $outline, array $tags, bool $normalize = true): array
    {
        $clean = self::dedupe($outline, $tags);

        $piece['outline'] = Geometry::round($clean['outline'], 3);
        $piece['meta']['edges'] = array_values($clean['tags']);
        $piece = self::reindex($piece);

        return $normalize ? Geometry::normalizePiece($piece) : $piece;
    }

    /**
     * شماره لبه ساسون‌ها و نشانه‌ها را دوباره از روی هندسه پیدا می‌کند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function reindex(array $piece): array
    {
        $outline = array_values($piece['outline'] ?? []);

        if (count($outline) < 3) {
            return $piece;
        }

        foreach ($piece['notches'] ?? [] as $index => $notch) {
            if (isset($notch['x'], $notch['y'])) {
                $piece['notches'][$index]['edge'] = Geometry::nearestEdge($outline, [
                    'x' => (float) $notch['x'], 'y' => (float) $notch['y'],
                ])['edge'];
            }
        }

        foreach ($piece['darts'] ?? [] as $index => $dart) {
            if (! isset($dart['center']['x'], $dart['center']['y'])) {
                continue;
            }

            // ساسون بادامی وسط قطعه می‌ماند و لبه‌ای ندارد؛ ولی ساسونی که تازه روی
            // محیط باز شده (مثل ساسون چرخانده‌شده) هنوز شماره لبه نگرفته است و باید
            // اینجا بگیرد. ملاک این است که هر دو پا روی محیط نشسته باشند.
            if (($dart['edge'] ?? null) === null && ! self::mouthOnOutline($outline, $dart)) {
                continue;
            }

            $piece['darts'][$index]['edge'] = Geometry::nearestEdge($outline, [
                'x' => (float) $dart['center']['x'], 'y' => (float) $dart['center']['y'],
            ])['edge'];
        }

        return $piece;
    }

    /** آیا دهانه ساسون (هر دو پا) روی محیط قطعه نشسته است؟ */
    public static function mouthOnOutline(array $outline, array $dart, float $tolerance = 0.15): bool
    {
        $legs = array_values($dart['legs'] ?? []);

        if (count($legs) !== 2) {
            return false;
        }

        foreach ($legs as $leg) {
            if (! isset($leg['x'], $leg['y'])) {
                return false;
            }

            $near = Geometry::nearestEdge($outline, ['x' => (float) $leg['x'], 'y' => (float) $leg['y']]);

            if ($near['distance'] > $tolerance) {
                return false;
            }
        }

        return true;
    }

    /** لبه‌های روی تای پارچه را بعد از تغییر شکل دوباره پیدا می‌کند. */
    public static function refoldEdges(array $piece, array $foldSegments): array
    {
        if ($foldSegments === []) {
            return [];
        }

        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);
        $edges = [];

        for ($i = 0; $i < $count; $i++) {
            $a = ['x' => (float) $outline[$i]['x'], 'y' => (float) $outline[$i]['y']];
            $b = ['x' => (float) $outline[($i + 1) % $count]['x'], 'y' => (float) $outline[($i + 1) % $count]['y']];

            foreach ($foldSegments as $segment) {
                if (self::onSegment($a, $segment) && self::onSegment($b, $segment)) {
                    $edges[] = $i;
                    break;
                }
            }
        }

        return $edges;
    }

    /** آیا نقطه روی یک پاره‌خط (با رواداری کوچک) نشسته است؟ */
    public static function onSegment(array $point, array $segment, float $tolerance = 0.05): bool
    {
        $a = $segment[0];
        $b = $segment[1];
        $dx = $b['x'] - $a['x'];
        $dy = $b['y'] - $a['y'];
        $lengthSquared = ($dx * $dx) + ($dy * $dy);

        if ($lengthSquared < 1e-9) {
            return Geometry::distance($point, $a) <= $tolerance;
        }

        $t = ((($point['x'] - $a['x']) * $dx) + (($point['y'] - $a['y']) * $dy)) / $lengthSquared;
        $t = max(0.0, min(1.0, $t));

        return Geometry::distance($point, ['x' => $a['x'] + ($dx * $t), 'y' => $a['y'] + ($dy * $t)]) <= $tolerance;
    }

    /**
     * طول یک زنجیره از نقطه‌ها (با منحنی‌ها) وقتی مسیر بسته نیست.
     */
    public static function chainLength(array $chain): float
    {
        $chain = array_values($chain);
        $total = 0.0;

        for ($i = 0; $i < count($chain) - 1; $i++) {
            $from = ['x' => (float) $chain[$i]['x'], 'y' => (float) $chain[$i]['y']];
            $to = $chain[$i + 1];

            if (! Geometry::isCurve($to)) {
                $total += Geometry::distance($from, ['x' => (float) $to['x'], 'y' => (float) $to['y']]);

                continue;
            }

            $control = ['x' => (float) $to['cx'], 'y' => (float) $to['cy']];
            $end = ['x' => (float) $to['x'], 'y' => (float) $to['y']];
            $previous = $from;

            for ($s = 1; $s <= Geometry::CURVE_SEGMENTS; $s++) {
                $current = Geometry::quadraticAt($from, $control, $end, $s / Geometry::CURVE_SEGMENTS);
                $total += Geometry::distance($previous, $current);
                $previous = $current;
            }
        }

        return round($total, 4);
    }

    /**
     * چندضلعی بسته یک زنجیره (برای آزمون «این نقطه داخل کدام نیمه است؟»).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function polygon(array $chain, array $extra = []): array
    {
        $polygon = array_values($chain);

        foreach ($extra as $point) {
            $polygon[] = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
        }

        return $polygon;
    }
}
