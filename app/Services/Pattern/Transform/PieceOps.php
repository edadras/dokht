<?php

namespace App\Services\Pattern\Transform;

use App\Services\Pattern\Geometry;
use InvalidArgumentException;

/**
 * کارهای همیشگی روی قطعه‌ها: دوختن، باز کردن تا، بلند و کوتاه کردن،
 * «پیاده‌کردن» دو درز روی هم و راست‌سازی و گرد کردن گوشه‌ها.
 *
 * این‌ها همان کارهایی است که خیاط با متر و خط‌کش روی کاغذ الگو می‌کند:
 *
 *   merge()     دو قطعه را از درز مشترک به هم می‌دوزد (وارونِ برش سبک‌خط).
 *   unfold()    قطعه‌ای که روی دو لای پارچه بریده می‌شود را کامل باز می‌کند.
 *   refold()    دوباره آن را نصف و روی تا می‌گذارد.
 *   extend()    یک لبه (پایین لباس، سرآستین، فاق) را بلند یا کوتاه می‌کند.
 *   walk()      دو درز را روی هم «پیاده» می‌کند: طول هرکدام، اختلاف و جای نشانه‌ها.
 *   trueSeam()  یکی از دو درز را کمی تغییر می‌دهد تا هم‌اندازه شوند، بدون شکستگی.
 *   smooth()    گوشه تیز را به منحنی روان تبدیل می‌کند (بعد از هر چرخش یا برش لازم است).
 */
final class PieceOps
{
    /** اختلاف کمتر از این مقدار، اختلاف حساب نمی‌شود (سانتی‌متر). */
    public const TOLERANCE = 0.01;

    /* ---------------------------------------------------------------------
     |  اندازه‌گیری
     * ------------------------------------------------------------------- */

    /**
     * شماره لبه‌های یک درز.
     *
     * ورودی می‌تواند شماره لبه، برچسب لبه یا فهرست شماره‌ها باشد. برچسب می‌تواند
     * به چند لبه پشت سر هم اشاره کند (مثلاً پهلویی که بعد از برش سه تکه شده) و
     * همه آن‌ها برگردانده می‌شوند.
     *
     * @return array<int, int>
     */
    public static function edges(array $piece, int|string|array $edge): array
    {
        if (is_array($edge)) {
            return array_values(array_map('intval', $edge));
        }

        if (is_string($edge)) {
            $found = Geometry::edgesWithTag($piece, $edge);

            if ($found === []) {
                throw new InvalidArgumentException("لبه‌ای با برچسب «{$edge}» روی این قطعه نیست.");
            }

            return $found;
        }

        return [$edge];
    }

    /** طول خام یک درز (مجموع طول لبه‌ها). */
    public static function edgeLength(array $piece, int|string|array $edge): float
    {
        $total = 0.0;

        foreach (self::edges($piece, $edge) as $index) {
            $total += Geometry::edgeLength($piece['outline'], $index);
        }

        return round($total, 3);
    }

    /**
     * طول دوخته‌شده یک درز: طول لبه منهای ساسون، پیلی و چینی که روی آن بسته می‌شود.
     *
     * این همان عددی است که خیاط با متر روی درز اندازه می‌گیرد.
     */
    public static function seamLength(array $piece, int|string|array $edge): float
    {
        $total = 0.0;

        foreach (self::edges($piece, $edge) as $index) {
            $total += Geometry::edgeLength($piece['outline'], $index);
            $total -= FullnessRecorder::consumedOn($piece, $index);
        }

        return round(max(0.0, $total), 3);
    }

    /**
     * رأس‌های یک درز پشت سر هم: از سرِ نخستین لبه تا تهِ آخرین لبه.
     *
     * @return array{start: int, end: int, indexes: array<int, int>}
     */
    public static function seamSpan(array $piece, int|string|array $edge): array
    {
        $indexes = self::edges($piece, $edge);
        sort($indexes);
        $count = count($piece['outline'] ?? []);

        return [
            'start' => $indexes[0] ?? 0,
            'end' => (($indexes[count($indexes) - 1] ?? 0) + 1) % max(1, $count),
            'indexes' => $indexes,
        ];
    }

    /* ---------------------------------------------------------------------
     |  پیاده‌کردن و راست‌سازی درز
     * ------------------------------------------------------------------- */

    /**
     * «پیاده‌کردن» دو درز روی هم.
     *
     * خیاط دو قطعه را کنار هم می‌گذارد و درزشان را قدم‌به‌قدم روی هم می‌چرخاند تا
     * ببیند هم‌اندازه‌اند یا نه و نشانه‌ها کجا باید بیفتد. این متد همان کار را
     * می‌کند و گزارش می‌دهد:
     *
     *   ['a' => [...], 'b' => [...], 'difference', 'ease', 'longer', 'matched', 'notches']
     *
     * difference = طول دوخته‌شده A منهای B. notches نقطه‌های متناظر روی هر دو درز
     * است (پیش‌فرض یک‌چهارم، نصف و سه‌چهارم) که اضافه‌بودن پارچه بین آن‌ها پخش شود.
     *
     * گزینه‌ها: at (فهرست نسبت‌ها)، reverse ('a'|'b'|null، وقتی دو درز در جهت
     * مخالف هم دوخته می‌شوند)، tolerance.
     */
    public static function walk(
        array $a,
        int|string|array $edgeA,
        array $b,
        int|string|array $edgeB,
        array $options = [],
    ): array {
        $tolerance = (float) ($options['tolerance'] ?? self::TOLERANCE);
        $fractions = $options['at'] ?? [0.25, 0.5, 0.75];
        $reverse = $options['reverse'] ?? null;

        $lengthA = self::seamLength($a, $edgeA);
        $lengthB = self::seamLength($b, $edgeB);
        $rawA = self::edgeLength($a, $edgeA);
        $rawB = self::edgeLength($b, $edgeB);
        $difference = round($lengthA - $lengthB, 3);

        $notches = [];

        foreach ($fractions as $fraction) {
            $fraction = max(0.0, min(1.0, (float) $fraction));

            $notches[] = [
                'at' => round($fraction, 4),
                'a' => self::pointAlong($a, $edgeA, $fraction * $rawA, $reverse === 'a'),
                'b' => self::pointAlong($b, $edgeB, $fraction * $rawB, $reverse === 'b'),
            ];
        }

        return [
            'a' => ['length' => $rawA, 'seam' => $lengthA, 'edges' => self::edges($a, $edgeA)],
            'b' => ['length' => $rawB, 'seam' => $lengthB, 'edges' => self::edges($b, $edgeB)],
            'difference' => $difference,
            'ease' => round(abs($difference), 3),
            'longer' => abs($difference) <= $tolerance ? 'equal' : ($difference > 0 ? 'a' : 'b'),
            'matched' => abs($difference) <= $tolerance,
            'notches' => $notches,
        ];
    }

    /**
     * نقطه‌ای روی یک درز، در فاصله داده‌شده از سر آن.
     *
     * @return array{x: float, y: float, edge: int, t: float, at: float}
     */
    public static function pointAlong(array $piece, int|string|array $edge, float $distance, bool $reverse = false): array
    {
        $indexes = self::edges($piece, $edge);

        if ($reverse) {
            $indexes = array_reverse($indexes);
        }

        $total = 0.0;

        foreach ($indexes as $index) {
            $length = Geometry::edgeLength($piece['outline'], $index);

            if ($distance <= $total + $length || $index === $indexes[count($indexes) - 1]) {
                $local = $length > 0 ? max(0.0, min(1.0, ($distance - $total) / $length)) : 0.0;
                $t = $reverse ? 1 - $local : $local;
                $on = Geometry::pointOnEdge($piece['outline'], $index, $t);

                return [
                    'x' => round($on['x'], 3),
                    'y' => round($on['y'], 3),
                    'edge' => $index,
                    't' => round($t, 4),
                    'at' => round($distance, 3),
                ];
            }

            $total += $length;
        }

        return ['x' => 0.0, 'y' => 0.0, 'edge' => $indexes[0] ?? 0, 't' => 0.0, 'at' => 0.0];
    }

    /**
     * راست‌سازی دو درز تا هم‌اندازه شوند.
     *
     * یک سر درز ثابت می‌ماند و سر دیگر در راستای خود درز کشیده یا جمع می‌شود؛
     * رأس‌های میانی به نسبت فاصله‌شان از سر ثابت جابه‌جا می‌شوند تا منحنی روان
     * بماند و درز نشکند. چند بار تکرار می‌شود تا اختلاف به رواداری برسد.
     *
     * گزینه‌ها: adjust ('a'|'b'|'longer'|'shorter'، پیش‌فرض longer)،
     * anchor ('start'|'end'، پیش‌فرض start)، tolerance، rounds.
     *
     * @return array{a: array<string, mixed>, b: array<string, mixed>, adjusted: string, length: float, difference: float, rounds: int}
     */
    public static function trueSeam(
        array $a,
        int|string|array $edgeA,
        array $b,
        int|string|array $edgeB,
        array $options = [],
    ): array {
        $tolerance = (float) ($options['tolerance'] ?? self::TOLERANCE);
        $maxRounds = max(1, (int) ($options['rounds'] ?? 16));
        $anchor = (string) ($options['anchor'] ?? 'start');

        $lengthA = self::seamLength($a, $edgeA);
        $lengthB = self::seamLength($b, $edgeB);
        $choice = (string) ($options['adjust'] ?? 'longer');

        $side = match ($choice) {
            'a', 'b' => $choice,
            'shorter' => $lengthA <= $lengthB ? 'a' : 'b',
            default => $lengthA >= $lengthB ? 'a' : 'b',
        };

        $target = $side === 'a' ? $lengthB : $lengthA;
        $piece = $side === 'a' ? $a : $b;
        $edge = $side === 'a' ? $edgeA : $edgeB;
        $rounds = 0;

        for (; $rounds < $maxRounds; $rounds++) {
            $current = self::seamLength($piece, $edge);
            $difference = $target - $current;

            if (abs($difference) <= $tolerance) {
                break;
            }

            $piece = self::stretchSeam($piece, $edge, $difference, $anchor);
        }

        $final = self::seamLength($piece, $edge);
        $other = $side === 'a' ? self::seamLength($b, $edgeB) : self::seamLength($a, $edgeA);

        return [
            'a' => $side === 'a' ? $piece : $a,
            'b' => $side === 'b' ? $piece : $b,
            'adjusted' => abs($lengthA - $lengthB) <= $tolerance ? 'none' : $side,
            'length' => round($final, 3),
            'difference' => round($final - $other, 3),
            'rounds' => $rounds,
        ];
    }

    /**
     * کشیدن یا جمع‌کردن یک درز به اندازه $delta در راستای خودش.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function stretchSeam(array $piece, int|string|array $edge, float $delta, string $anchor = 'start'): array
    {
        $span = self::seamSpan($piece, $edge);
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $chainIndexes = [];

        for ($step = 0; $step <= count($span['indexes']); $step++) {
            $chainIndexes[] = ($span['start'] + $step) % $count;
        }

        if ($anchor === 'end') {
            $chainIndexes = array_reverse($chainIndexes);
        }

        $fixed = $outline[$chainIndexes[0]];
        $moving = $outline[$chainIndexes[count($chainIndexes) - 1]];
        $previous = $outline[$chainIndexes[count($chainIndexes) - 2] ?? $chainIndexes[0]];

        $dx = ((float) $moving['x']) - ((float) $previous['x']);
        $dy = ((float) $moving['y']) - ((float) $previous['y']);
        $norm = sqrt(($dx * $dx) + ($dy * $dy));

        if ($norm < 1e-9) {
            $dx = ((float) $moving['x']) - ((float) $fixed['x']);
            $dy = ((float) $moving['y']) - ((float) $fixed['y']);
            $norm = sqrt(($dx * $dx) + ($dy * $dy));
        }

        if ($norm < 1e-9) {
            return $piece;
        }

        $ux = $dx / $norm;
        $uy = $dy / $norm;

        // سهم هر رأس از جابه‌جایی، به نسبت فاصله‌اش از سر ثابت
        $distances = [0.0];
        $total = 0.0;

        for ($i = 1; $i < count($chainIndexes); $i++) {
            $from = $outline[$chainIndexes[$i - 1]];
            $to = $outline[$chainIndexes[$i]];
            $total += Geometry::distance(
                ['x' => (float) $from['x'], 'y' => (float) $from['y']],
                ['x' => (float) $to['x'], 'y' => (float) $to['y']],
            );
            $distances[$i] = $total;
        }

        if ($total < 1e-9) {
            return $piece;
        }

        $moved = [];

        for ($i = 1; $i < count($chainIndexes); $i++) {
            $share = $distances[$i] / $total;
            $shift = $delta * $share;
            $at = $chainIndexes[$i];
            $point = $outline[$at];
            $point['x'] = round(((float) $point['x']) + ($ux * $shift), 4);
            $point['y'] = round(((float) $point['y']) + ($uy * $shift), 4);

            if (isset($point['cx'], $point['cy'])) {
                $point['cx'] = round(((float) $point['cx']) + ($ux * $shift * 0.5), 4);
                $point['cy'] = round(((float) $point['cy']) + ($uy * $shift * 0.5), 4);
            }

            $outline[$at] = $point;
            $moved[$at] = ['dx' => $ux * $shift, 'dy' => $uy * $shift];
        }

        $piece['outline'] = $outline;
        $piece['meta']['trued'] = round(((float) ($piece['meta']['trued'] ?? 0)) + $delta, 3);

        // نشانه‌های روی همین درز با آن جابه‌جا می‌شوند
        foreach ($piece['notches'] ?? [] as $key => $notch) {
            if (! in_array((int) ($notch['edge'] ?? -1), $span['indexes'], true)) {
                continue;
            }

            $near = Geometry::nearestEdge($outline, ['x' => (float) $notch['x'], 'y' => (float) $notch['y']]);
            $piece['notches'][$key]['x'] = round($near['point']['x'], 3);
            $piece['notches'][$key]['y'] = round($near['point']['y'], 3);
            $piece['notches'][$key]['edge'] = $near['edge'];
        }

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  بلند و کوتاه کردن
     * ------------------------------------------------------------------- */

    /**
     * بلند یا کوتاه کردن یک قطعه از یک لبه.
     *
     * لبه (پایین لباس، سرآستین، فاق) در راستای عمود بر خودش و رو به بیرون
     * جابه‌جا می‌شود و لبه‌های همسایه با آن کش می‌آیند — همان کاری که با
     * «پایین‌آوردن خط دامن» می‌کنیم. مقدار منفی یعنی کوتاه کردن.
     *
     * گزینه‌ها: direction (بردار دلخواه)، label.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function extend(array $piece, string $edgeTag, float $amount, array $options = []): array
    {
        if (abs($amount) < 1e-6) {
            return $piece;
        }

        $indexes = isset($options['edges'])
            ? array_map('intval', $options['edges'])
            : Geometry::edgesWithTag($piece, $edgeTag);

        if ($indexes === []) {
            throw new InvalidArgumentException("لبه‌ای با برچسب «{$edgeTag}» برای بلند/کوتاه کردن پیدا نشد.");
        }

        sort($indexes);
        $outline = array_values($piece['outline']);
        $count = count($outline);

        $vertices = [];

        foreach ($indexes as $index) {
            $vertices[$index] = true;
            $vertices[($index + 1) % $count] = true;
        }

        $vertices = array_keys($vertices);

        if (isset($options['direction'])) {
            $ux = (float) $options['direction']['x'];
            $uy = (float) $options['direction']['y'];
            $norm = sqrt(($ux * $ux) + ($uy * $uy)) ?: 1.0;
            $ux /= $norm;
            $uy /= $norm;
        } else {
            $first = $outline[$indexes[0]];
            $last = $outline[($indexes[count($indexes) - 1] + 1) % $count];
            $dx = ((float) $last['x']) - ((float) $first['x']);
            $dy = ((float) $last['y']) - ((float) $first['y']);
            $norm = sqrt(($dx * $dx) + ($dy * $dy));

            if ($norm < 1e-9) {
                throw new InvalidArgumentException('لبه‌ای که باید بلند شود طول ندارد.');
            }

            $ux = -$dy / $norm;
            $uy = $dx / $norm;

            // عمود باید رو به بیرون قطعه باشد
            $middle = Geometry::lerp(
                ['x' => (float) $first['x'], 'y' => (float) $first['y']],
                ['x' => (float) $last['x'], 'y' => (float) $last['y']],
                0.5,
            );
            $centroid = Geometry::centroid($outline);

            if ((($middle['x'] - $centroid['x']) * $ux) + (($middle['y'] - $centroid['y']) * $uy) < 0) {
                $ux = -$ux;
                $uy = -$uy;
            }
        }

        $dx = $ux * $amount;
        $dy = $uy * $amount;

        foreach ($vertices as $at) {
            $point = $outline[$at];
            $point['x'] = round(((float) $point['x']) + $dx, 3);
            $point['y'] = round(((float) $point['y']) + $dy, 3);

            if (isset($point['cx'], $point['cy'])) {
                $point['cx'] = round(((float) $point['cx']) + $dx, 3);
                $point['cy'] = round(((float) $point['cy']) + $dy, 3);
            }

            $outline[$at] = $point;
        }

        // نقطه کنترل لبه‌های همسایه نصفِ جابه‌جایی را می‌گیرد تا منحنی نشکند
        foreach ($indexes as $index) {
            $neighbour = ($index + 2) % $count;

            if (! in_array($neighbour, $vertices, true) && isset($outline[$neighbour]['cx'], $outline[$neighbour]['cy'])) {
                $outline[$neighbour]['cx'] = round(((float) $outline[$neighbour]['cx']) + ($dx * 0.5), 3);
                $outline[$neighbour]['cy'] = round(((float) $outline[$neighbour]['cy']) + ($dy * 0.5), 3);
            }
        }

        $piece['outline'] = $outline;

        foreach ($piece['notches'] ?? [] as $key => $notch) {
            if (in_array((int) ($notch['edge'] ?? -1), $indexes, true)) {
                $piece['notches'][$key]['x'] = round(((float) $notch['x']) + $dx, 3);
                $piece['notches'][$key]['y'] = round(((float) $notch['y']) + $dy, 3);
            }
        }

        $piece['meta']['extended'][$edgeTag] = round(((float) ($piece['meta']['extended'][$edgeTag] ?? 0)) + $amount, 2);

        return Geometry::normalizePiece(Outline::reindex($piece));
    }

    /* ---------------------------------------------------------------------
     |  تا و باز کردن تا
     * ------------------------------------------------------------------- */

    /**
     * باز کردن قطعه‌ای که روی دو لای پارچه بریده می‌شود.
     *
     * قطعه نسبت به خط تا آینه می‌شود و یک قطعه کامل به دست می‌آید؛ ساسون‌ها،
     * نشانه‌ها و خط‌های نشانه هم قرینه‌شان اضافه می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function unfold(array $piece, array $options = []): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);
        $fold = $options['edge'] ?? ($piece['meta']['fold_edges'][0] ?? null);

        if ($fold === null || $count < 3) {
            return $piece;
        }

        $fold = (int) $fold;
        $tags = Geometry::edgeTags($piece);
        $a = ['x' => (float) $outline[$fold]['x'], 'y' => (float) $outline[$fold]['y']];
        $b = ['x' => (float) $outline[($fold + 1) % $count]['x'], 'y' => (float) $outline[($fold + 1) % $count]['y']];

        $mirror = self::mirrorAcross($a, $b);

        $chain = Outline::chain($outline, ($fold + 1) % $count, $fold);
        $chainTags = [];

        for ($i = 0; $i < count($chain) - 1; $i++) {
            $chainTags[$i] = $tags[(($fold + 1 + $i) % $count)] ?? 'default';
        }

        $mirrored = array_map($mirror, Outline::reverse($chain));
        $mirroredTags = array_reverse($chainTags);

        $joined = Outline::join([
            ['points' => $chain, 'tags' => $chainTags],
            ['points' => $mirrored, 'tags' => $mirroredTags],
        ], $mirroredTags[count($mirroredTags) - 1] ?? 'default');

        $centroid = Geometry::centroid($outline);
        $side = (($b['x'] - $a['x']) * ($centroid['y'] - $a['y'])) - (($b['y'] - $a['y']) * ($centroid['x'] - $a['x']));

        $extra = Geometry::mapPiece([
            'outline' => [],
            'darts' => $piece['darts'] ?? [],
            'notches' => $piece['notches'] ?? [],
            'markers' => $piece['markers'] ?? [],
            'drills' => $piece['drills'] ?? [],
        ], $mirror);

        $piece['darts'] = array_merge($piece['darts'] ?? [], $extra['darts']);
        $piece['notches'] = array_merge($piece['notches'] ?? [], $extra['notches']);
        $piece['markers'] = array_merge($piece['markers'] ?? [], $extra['markers']);
        $piece['drills'] = array_merge($piece['drills'] ?? [], $extra['drills']);

        $piece['on_fold'] = false;
        $piece['mirror'] = false;
        $piece['meta']['fold_edges'] = [];

        $piece = Outline::apply($piece, $joined['outline'], $joined['tags'], normalize: false);

        // خط تا باید در همان دستگاه مختصاتی ذخیره شود که قطعه پس از مرتب‌سازی
        // دارد، وگرنه refold() آن را جای دیگری می‌برد و قطعه را نابجا می‌برد.
        [$minX, $minY] = Geometry::bounds($piece['outline']);
        $piece['meta']['unfolded'] = [
            'line' => [
                Geometry::point($a['x'] - $minX, $a['y'] - $minY),
                Geometry::point($b['x'] - $minX, $b['y'] - $minY),
            ],
            'side' => $side >= 0 ? 1 : -1,
        ];

        return Geometry::normalizePiece($piece);
    }

    /**
     * برگرداندن یک قطعه کامل به نیم‌قطعه روی تای پارچه.
     *
     * وارونِ unfold(): قطعه از خط تا نصف می‌شود و همان نیمه‌ای که پیش‌تر داشتیم
     * نگه داشته می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function refold(array $piece, array $options = []): array
    {
        $line = $options['line'] ?? ($piece['meta']['unfolded']['line'] ?? null);

        if ($line === null) {
            throw new InvalidArgumentException('خط تا برای بستن دوباره قطعه معلوم نیست.');
        }

        $side = (int) ($options['side'] ?? ($piece['meta']['unfolded']['side'] ?? 1));

        $halves = StyleLineCutter::cut($piece, [
            ['x' => (float) $line[0]['x'], 'y' => (float) $line[0]['y']],
            ['x' => (float) $line[1]['x'], 'y' => (float) $line[1]['y']],
        ], array_merge([
            'tag' => 'default',
            'notches' => false,
            'normalize' => false,
            'names' => [$piece['name'] ?? 'قطعه', $piece['name'] ?? 'قطعه'],
            'codes' => [$piece['code'] ?? 'piece', $piece['code'] ?? 'piece'],
        ], $options['cut'] ?? []));

        $a = ['x' => (float) $line[0]['x'], 'y' => (float) $line[0]['y']];
        $b = ['x' => (float) $line[1]['x'], 'y' => (float) $line[1]['y']];

        $keep = null;

        foreach ($halves as $half) {
            $centroid = Geometry::centroid($half['outline']);
            $sign = (($b['x'] - $a['x']) * ($centroid['y'] - $a['y'])) - (($b['y'] - $a['y']) * ($centroid['x'] - $a['x']));

            if (($sign >= 0 ? 1 : -1) === $side) {
                $keep = $half;
                break;
            }
        }

        $keep ??= $halves[0];

        $keep['code'] = $piece['code'] ?? 'piece';
        $keep['name'] = $piece['name'] ?? 'قطعه';
        $keep['on_fold'] = true;
        $keep['mirror'] = false;
        $keep['meta']['fold_edges'] = $keep['meta']['cut_edges'] ?? [];
        unset($keep['meta']['unfolded'], $keep['meta']['cut_edges'], $keep['meta']['cut_of']);

        return Geometry::normalizePiece($keep);
    }

    /** تابع آینه‌کردن نسبت به خطی که از دو نقطه می‌گذرد. */
    public static function mirrorAcross(array $a, array $b): callable
    {
        $dx = ((float) $b['x']) - ((float) $a['x']);
        $dy = ((float) $b['y']) - ((float) $a['y']);
        $norm = ($dx * $dx) + ($dy * $dy);

        if ($norm < 1e-12) {
            return fn (array $point) => $point;
        }

        $reflect = function (float $x, float $y) use ($a, $dx, $dy, $norm): array {
            $vx = $x - ((float) $a['x']);
            $vy = $y - ((float) $a['y']);
            $dot = (($vx * $dx) + ($vy * $dy)) / $norm;
            $px = $dx * $dot;
            $py = $dy * $dot;

            return [
                round(((float) $a['x']) + ((2 * $px) - $vx), 4),
                round(((float) $a['y']) + ((2 * $py) - $vy), 4),
            ];
        };

        return function (array $point) use ($reflect): array {
            [$x, $y] = $reflect((float) ($point['x'] ?? 0), (float) ($point['y'] ?? 0));
            $point['x'] = $x;
            $point['y'] = $y;

            if (isset($point['cx'], $point['cy'])) {
                [$cx, $cy] = $reflect((float) $point['cx'], (float) $point['cy']);
                $point['cx'] = $cx;
                $point['cy'] = $cy;
            }

            return $point;
        };
    }

    /* ---------------------------------------------------------------------
     |  دوختن دو قطعه به هم
     * ------------------------------------------------------------------- */

    /**
     * دوختن دو قطعه از درز مشترک و ساختن یک قطعه یکپارچه.
     *
     * وارونِ StyleLineCutter::cut() است و وقتی لازم می‌شود که یک مدل، درزی را
     * برمی‌دارد (مثلاً یوک را با تنه یکی می‌کند). قطعه دوم چرخانده و جابه‌جا
     * می‌شود تا درزش دقیقاً روی درز قطعه اول بنشیند.
     *
     * گزینه‌ها: edge_a، edge_b (شماره لبه، برچسب یا فهرست؛ پیش‌فرض درزی که
     * StyleLineCutter در meta.cut_edges گذاشته)، code، name، drop_seam_notches.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    public static function merge(array $a, array $b, array $options = []): array
    {
        $edgeA = $options['edge_a'] ?? ($a['meta']['cut_edges'] ?? null);
        $edgeB = $options['edge_b'] ?? ($b['meta']['cut_edges'] ?? null);

        if ($edgeA === null || $edgeB === null) {
            throw new InvalidArgumentException('درز مشترک دو قطعه معلوم نیست؛ edge_a و edge_b را بدهید.');
        }

        $spanA = self::seamSpan($a, $edgeA);
        $spanB = self::seamSpan($b, $edgeB);

        $outlineA = array_values($a['outline']);
        $outlineB = array_values($b['outline']);

        $a0 = Outline::straighten($outlineA[$spanA['start']]);
        $a1 = Outline::straighten($outlineA[$spanA['end']]);
        $b0 = Outline::straighten($outlineB[$spanB['start']]);
        $b1 = Outline::straighten($outlineB[$spanB['end']]);

        // درز دو قطعه در جهت مخالف هم دوخته می‌شود: سر درز B روی ته درز A می‌نشیند
        $angle = atan2($a0['y'] - $a1['y'], $a0['x'] - $a1['x']) - atan2($b1['y'] - $b0['y'], $b1['x'] - $b0['x']);

        $b = Geometry::rotatePiece($b, $b0, $angle);
        $outlineB = array_values($b['outline']);
        $b0 = Outline::straighten($outlineB[$spanB['start']]);
        $b = Geometry::translatePiece($b, $a1['x'] - $b0['x'], $a1['y'] - $b0['y']);
        $outlineB = array_values($b['outline']);

        $tagsA = Geometry::edgeTags($a);
        $tagsB = Geometry::edgeTags($b);

        $chainA = Outline::chain($outlineA, $spanA['end'], $spanA['start'], full: true);
        $chainB = Outline::chain($outlineB, $spanB['end'], $spanB['start'], full: true);

        $chainTagsA = [];
        $countA = count($outlineA);

        for ($i = 0; $i < count($chainA) - 1; $i++) {
            $chainTagsA[$i] = $tagsA[($spanA['end'] + $i) % $countA] ?? 'default';
        }

        $chainTagsB = [];
        $countB = count($outlineB);

        for ($i = 0; $i < count($chainB) - 1; $i++) {
            $chainTagsB[$i] = $tagsB[($spanB['end'] + $i) % $countB] ?? 'default';
        }

        $joined = Outline::join([
            ['points' => $chainA, 'tags' => $chainTagsA],
            ['points' => $chainB, 'tags' => $chainTagsB],
        ], $chainTagsB[count($chainTagsB) - 1] ?? 'default');

        $dropPairs = (bool) ($options['drop_seam_notches'] ?? true);
        $seamPairs = array_filter([$a['meta']['cut_pair'] ?? null, $b['meta']['cut_pair'] ?? null]);

        $notches = array_merge($a['notches'] ?? [], $b['notches'] ?? []);

        if ($dropPairs && $seamPairs !== []) {
            $notches = array_values(array_filter(
                $notches,
                fn ($notch) => ! in_array($notch['pair'] ?? null, $seamPairs, true),
            ));
        }

        $merged = $a;
        $merged['code'] = (string) ($options['code'] ?? ($a['meta']['cut_of'] ?? $a['code'] ?? 'piece'));
        $merged['name'] = (string) ($options['name'] ?? ($a['name'] ?? 'قطعه'));
        $merged['darts'] = array_merge($a['darts'] ?? [], $b['darts'] ?? []);
        $merged['notches'] = $notches;
        $merged['markers'] = array_merge($a['markers'] ?? [], $b['markers'] ?? []);
        $merged['drills'] = array_merge($a['drills'] ?? [], $b['drills'] ?? []);
        $merged['on_fold'] = ! empty($a['on_fold']) || ! empty($b['on_fold']);
        $merged['meta'] = array_merge($b['meta'] ?? [], $a['meta'] ?? []);
        $merged['meta']['gathers'] = array_merge($a['meta']['gathers'] ?? [], $b['meta']['gathers'] ?? []);
        $merged['meta']['pleats'] = array_merge($a['meta']['pleats'] ?? [], $b['meta']['pleats'] ?? []);
        $merged['meta']['merged_from'] = [$a['code'] ?? null, $b['code'] ?? null];

        foreach (['gathers', 'pleats'] as $kind) {
            if ($merged['meta'][$kind] === []) {
                unset($merged['meta'][$kind]);
            }
        }

        unset($merged['meta']['cut_edges'], $merged['meta']['cut_pair'], $merged['meta']['cut_of'], $merged['meta']['cut_side']);

        $merged = Outline::apply($merged, $joined['outline'], $joined['tags'], normalize: false);
        $merged['meta']['fold_edges'] = self::foldEdgesFrom($merged, [$a, $b]);

        return Geometry::normalizePiece($merged);
    }

    /**
     * لبه‌های روی تای پارچه را بعد از دوختن دوباره پیدا می‌کند.
     *
     * @return array<int, int>
     */
    protected static function foldEdgesFrom(array $piece, array $sources): array
    {
        $segments = [];

        foreach ($sources as $source) {
            $outline = array_values($source['outline'] ?? []);
            $count = count($outline);

            foreach ($source['meta']['fold_edges'] ?? [] as $edge) {
                $edge = (int) $edge;

                if ($count > 0 && $edge >= 0 && $edge < $count) {
                    $segments[] = [
                        ['x' => (float) $outline[$edge]['x'], 'y' => (float) $outline[$edge]['y']],
                        ['x' => (float) $outline[($edge + 1) % $count]['x'], 'y' => (float) $outline[($edge + 1) % $count]['y']],
                    ];
                }
            }
        }

        return Outline::refoldEdges($piece, $segments);
    }

    /* ---------------------------------------------------------------------
     |  روان کردن گوشه
     * ------------------------------------------------------------------- */

    /**
     * گرد کردن یک یا چند گوشه تیز مسیر به منحنی روان.
     *
     * بعد از چرخاندن ساسون یا برش سبک‌خط، جای اتصال دو تکه گوشه‌دار می‌شود؛
     * خیاط آن را با پیستوله «روان» می‌کند. اینجا گوشه با یک منحنی درجه‌دو که
     * نقطه کنترلش خودِ گوشه است جایگزین می‌شود، پس خط ورودی و خروجی مماس می‌مانند.
     *
     * گزینه‌ها: radius (سانتی‌متر) یا ratio (نسبت کوتاه‌ترین لبه همسایه، پیش‌فرض ۰٫۲۵).
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @param  array<int, int>  $indexes
     * @return array<int, array<string, mixed>>
     */
    public static function smooth(array $outline, array $indexes, array $options = []): array
    {
        return self::smoothWithTags($outline, Geometry::edgeTags(['outline' => $outline]), $indexes, $options)['outline'];
    }

    /**
     * همان smooth()، ولی روی یک قطعه کامل تا برچسب لبه‌ها و نشانه‌ها هم درست بمانند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function smoothPiece(array $piece, array $indexes, array $options = []): array
    {
        $result = self::smoothWithTags(
            array_values($piece['outline']),
            Geometry::edgeTags($piece),
            $indexes,
            $options,
        );

        return Outline::apply($piece, $result['outline'], $result['tags'], normalize: (bool) ($options['normalize'] ?? false));
    }

    /**
     * @return array{outline: array<int, array<string, mixed>>, tags: array<int, string>}
     */
    protected static function smoothWithTags(array $outline, array $tags, array $indexes, array $options = []): array
    {
        $outline = array_values($outline);
        $tags = array_values($tags);
        $ratio = (float) ($options['ratio'] ?? 0.25);
        $radius = isset($options['radius']) ? (float) $options['radius'] : null;

        // گوشه‌ها را به شکل نقطه نگه می‌داریم تا جابه‌جا شدن شماره‌ها آزارمان ندهد
        $corners = [];

        foreach (array_values(array_unique(array_map('intval', $indexes))) as $index) {
            if (isset($outline[$index])) {
                $corners[] = ['x' => (float) $outline[$index]['x'], 'y' => (float) $outline[$index]['y']];
            }
        }

        foreach ($corners as $target) {
            $count = count($outline);

            if ($count < 4) {
                break;
            }

            $corner = 0;
            $best = INF;

            for ($i = 0; $i < $count; $i++) {
                $distance = Geometry::distance(['x' => (float) $outline[$i]['x'], 'y' => (float) $outline[$i]['y']], $target);

                if ($distance < $best) {
                    $best = $distance;
                    $corner = $i;
                }
            }

            // مسیر را می‌چرخانیم تا گوشه در جای ۱ بنشیند و بریدن‌ها ساده شود
            $shift = (($corner - 1) % $count + $count) % $count;
            $outline = array_merge(array_slice($outline, $shift), array_slice($outline, 0, $shift));
            $tags = array_merge(array_slice($tags, $shift), array_slice($tags, 0, $shift));

            $lengthIn = Geometry::edgeLength($outline, 0);
            $lengthOut = Geometry::edgeLength($outline, 1);

            if ($lengthIn < 1e-6 || $lengthOut < 1e-6) {
                continue;
            }

            $distance = $radius ?? ($ratio * min($lengthIn, $lengthOut));
            $distance = min($distance, 0.45 * $lengthIn, 0.45 * $lengthOut);

            if ($distance < 1e-6) {
                continue;
            }

            $cornerTag = $tags[0] ?? 'default';
            $control = ['x' => (float) $outline[1]['x'], 'y' => (float) $outline[1]['y']];

            // نقطه پیش از گوشه
            $outline = Geometry::splitEdgeAt($outline, 0, 1 - ($distance / $lengthIn));
            array_splice($tags, 1, 0, [$tags[0] ?? 'default']);

            // نقطه پس از گوشه
            $outline = Geometry::splitEdgeAt($outline, 2, $distance / $lengthOut);
            array_splice($tags, 3, 0, [$tags[2] ?? 'default']);

            // گوشه برداشته و جایش منحنی می‌نشیند
            $outline[3]['curve'] = true;
            $outline[3]['cx'] = round($control['x'], 3);
            $outline[3]['cy'] = round($control['y'], 3);

            array_splice($outline, 2, 1);
            array_splice($tags, 2, 1);
            $tags[1] = $options['tag'] ?? $cornerTag;
        }

        return ['outline' => $outline, 'tags' => array_slice($tags, 0, count($outline))];
    }
}
