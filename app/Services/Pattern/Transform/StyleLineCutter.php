<?php

namespace App\Services\Pattern\Transform;

use App\Services\Pattern\Geometry;
use InvalidArgumentException;

/**
 * برش «سبک‌خط»: بریدن یک قطعه به دو قطعه در امتداد یک خط دلخواه.
 *
 * هرجا مدلی درزی می‌خواهد که در بلوک پایه نیست، کارِ خیاط همین است: خطی روی
 * الگو می‌کشد و آن را می‌برد — یوکِ پشت پیراهن، برش رنگی، خط سینه (امپایر)،
 * کمر افتاده، دامن ترک‌دار، جیب‌های برشی و مانند این‌ها.
 *
 * برش:
 *   ۱. لبه‌هایی که خط از آن‌ها رد می‌شود شکسته می‌شوند؛ منحنی درجه‌دو با دو
 *      کاستلژو نصف می‌شود تا خمیدگی لبه سالم بماند.
 *   ۲. دو قطعه تازه برچسب لبه کامل می‌گیرند و لبه برش برچسب دلخواه می‌گیرد.
 *   ۳. ساسون، نشانه و خط‌های نشانه به قطعه‌ای می‌روند که واقعاً داخلش هستند.
 *   ۴. روی هر دو درز تازه نشانه‌های جفت گذاشته می‌شود تا دوباره درست دوخته شوند.
 *   ۵. تای پارچه (on_fold) و لبه‌های تا دوباره حساب می‌شود.
 */
final class StyleLineCutter
{
    /**
     * بریدن قطعه به دو قطعه در امتداد یک مسیر.
     *
     * $path فهرست نقطه است؛ نقطه اول و آخر روی محیط قطعه می‌نشینند (اگر دقیق
     * روی محیط نباشند به نزدیک‌ترین جای محیط چسبانده می‌شوند) و نقطه‌های میانی
     * شکل خط برش را می‌سازند. نقطه میانی می‌تواند منحنی باشد
     * (['x','y','curve'=>true,'cx','cy']).
     *
     * گزینه‌ها:
     *   tag        برچسب لبه‌های برش (پیش‌فرض default)
     *   codes      [کد قطعه اول، کد قطعه دوم]
     *   names      [نام قطعه اول، نام قطعه دوم]
     *   notches    نشانه‌های جفت روی درز تازه (پیش‌فرض true) یا فهرست نسبت‌ها
     *   pair       کلید جفت‌شدن نشانه‌ها
     *   normalize  جابه‌جا کردن هر قطعه به مبدأ خودش (پیش‌فرض true)
     *
     * @param  array<string, mixed>  $piece
     * @param  array<int, array<string, mixed>>  $path
     * @return array<int, array<string, mixed>> دو قطعه
     */
    public static function cut(array $piece, array $path, array $options = []): array
    {
        $path = array_values($path);

        if (count($path) < 2) {
            throw new InvalidArgumentException('خط برش دست‌کم دو نقطه می‌خواهد.');
        }

        $outline = array_values($piece['outline'] ?? []);

        if (count($outline) < 3) {
            throw new InvalidArgumentException('قطعه‌ای با مسیر ناقص بریده نمی‌شود.');
        }

        $tags = Geometry::edgeTags($piece);
        $startAnchor = Outline::anchor($piece, ['at' => $path[0]]);
        $endAnchor = Outline::anchor($piece, ['at' => $path[count($path) - 1]]);

        if ($startAnchor['edge'] === $endAnchor['edge'] && abs($startAnchor['t'] - $endAnchor['t']) < 1e-4) {
            throw new InvalidArgumentException('دو سر خط برش روی یک نقطه‌اند؛ برشی انجام نمی‌شود.');
        }

        $inserted = Outline::insertAnchors($outline, $tags, [
            'start' => ['edge' => $startAnchor['edge'], 't' => $startAnchor['t']],
            'end' => ['edge' => $endAnchor['edge'], 't' => $endAnchor['t']],
        ]);

        $outline = $inserted['outline'];
        $tags = $inserted['tags'];
        $count = count($outline);
        $startIndex = $inserted['index']['start'];
        $endIndex = $inserted['index']['end'];

        if ($startIndex === $endIndex) {
            throw new InvalidArgumentException('دو سر خط برش روی یک رأس افتادند؛ برشی انجام نمی‌شود.');
        }

        // مسیر برش با نقطه‌های دقیقِ روی محیط
        $cutPath = $path;
        $cutPath[0] = Outline::straighten($outline[$startIndex]);
        $cutPath[count($cutPath) - 1] = array_merge($cutPath[count($cutPath) - 1], [
            'x' => (float) $outline[$endIndex]['x'],
            'y' => (float) $outline[$endIndex]['y'],
        ]);

        $tag = (string) ($options['tag'] ?? 'default');
        $pathTags = array_fill(0, count($cutPath) - 1, $tag);

        $chainOne = Outline::chain($outline, $startIndex, $endIndex);
        $chainTwo = Outline::chain($outline, $endIndex, $startIndex);

        $tagsOne = [];

        for ($i = 0; $i < count($chainOne) - 1; $i++) {
            $tagsOne[$i] = $tags[($startIndex + $i) % $count] ?? 'default';
        }

        $tagsTwo = [];

        for ($i = 0; $i < count($chainTwo) - 1; $i++) {
            $tagsTwo[$i] = $tags[($endIndex + $i) % $count] ?? 'default';
        }

        $reversedPath = Outline::reverse($cutPath);

        $first = Outline::join([
            ['points' => $chainOne, 'tags' => $tagsOne],
            ['points' => $reversedPath, 'tags' => array_reverse($pathTags)],
        ], $tag);

        $second = Outline::join([
            ['points' => $chainTwo, 'tags' => $tagsTwo],
            ['points' => $cutPath, 'tags' => $pathTags],
        ], $tag);

        $segments = [];

        for ($i = 0; $i < count($cutPath) - 1; $i++) {
            $segments[] = [
                ['x' => (float) $cutPath[$i]['x'], 'y' => (float) $cutPath[$i]['y']],
                ['x' => (float) $cutPath[$i + 1]['x'], 'y' => (float) $cutPath[$i + 1]['y']],
            ];
        }

        $pair = (string) ($options['pair'] ?? 'style-'.substr(md5(json_encode([$piece['code'] ?? '', $cutPath])), 0, 8));
        $codes = $options['codes'] ?? [($piece['code'] ?? 'piece').'-a', ($piece['code'] ?? 'piece').'-b'];
        $names = $options['names'] ?? [($piece['name'] ?? 'قطعه').' ۱', ($piece['name'] ?? 'قطعه').' ۲'];

        $pieces = [];

        foreach ([[$first, 0], [$second, 1]] as [$built, $side]) {
            $half = $piece;
            $half['code'] = (string) ($codes[$side] ?? 'piece');
            $half['name'] = (string) ($names[$side] ?? 'قطعه');
            $half['meta']['cut_of'] = $piece['code'] ?? null;
            $half['meta']['cut_side'] = $side === 0 ? 'a' : 'b';
            $half['meta']['cut_pair'] = $pair;

            $half = Outline::apply($half, $built['outline'], $built['tags'], normalize: false);
            $half = self::keepInside($half, $piece);
            $half['meta']['cut_edges'] = Outline::refoldEdges($half, $segments);
            $half['meta']['fold_edges'] = self::foldEdges($piece, $half);
            $half['on_fold'] = $half['meta']['fold_edges'] !== [];

            $pieces[] = $half;
        }

        $pieces = self::pairNotches($pieces, $options, $pair);

        if ($options['normalize'] ?? true) {
            $pieces = array_map(fn (array $half) => Geometry::normalizePiece($half), $pieces);
        }

        return $pieces;
    }

    /**
     * بریدن افقی: همان کاری که برای خط سینه، کمر افتاده و یوک لازم است.
     *
     * قطعه بالایی اول برمی‌گردد و قطعه پایینی دوم.
     *
     * @param  array<string, mixed>  $piece
     * @return array<int, array<string, mixed>>
     */
    public static function cutHorizontal(array $piece, float $y, array $options = []): array
    {
        $crossings = self::crossings($piece['outline'] ?? [], $y);

        if (count($crossings) < 2) {
            throw new InvalidArgumentException('خط افقی داده‌شده از قطعه رد نمی‌شود.');
        }

        usort($crossings, fn ($a, $b) => $a['x'] <=> $b['x']);
        $left = $crossings[0];
        $right = $crossings[count($crossings) - 1];

        $pieces = self::cut($piece, [
            ['x' => $left['x'], 'y' => $left['y']],
            ['x' => $right['x'], 'y' => $right['y']],
        ], $options);

        usort($pieces, fn ($a, $b) => Geometry::centroid($a['outline'])['y'] <=> Geometry::centroid($b['outline'])['y']);

        return array_values($pieces);
    }

    /**
     * بریدن روی یک خط نشانه (خط سینه، خط کمر، خط باسن…).
     *
     * اگر خط نشانه افقی باشد برش افقی انجام می‌شود، وگرنه خودِ خط نشانه به محیط
     * قطعه چسبانده و همان‌جا بریده می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<int, array<string, mixed>>
     */
    public static function cutAtMarker(array $piece, string $markerKey, array $options = []): array
    {
        $marker = null;

        foreach ($piece['markers'] ?? [] as $candidate) {
            if (($candidate['key'] ?? null) === $markerKey) {
                $marker = $candidate;
                break;
            }
        }

        if ($marker === null || ! isset($marker['from']['x'], $marker['to']['x'])) {
            throw new InvalidArgumentException("خط نشانه «{$markerKey}» روی این قطعه نیست.");
        }

        $from = ['x' => (float) $marker['from']['x'], 'y' => (float) $marker['from']['y']];
        $to = ['x' => (float) $marker['to']['x'], 'y' => (float) $marker['to']['y']];

        if (abs($from['y'] - $to['y']) < 0.05) {
            return self::cutHorizontal($piece, ($from['y'] + $to['y']) / 2, $options);
        }

        $outline = $piece['outline'] ?? [];

        return self::cut($piece, [
            Geometry::nearestEdge($outline, $from)['point'],
            Geometry::nearestEdge($outline, $to)['point'],
        ], $options);
    }

    /**
     * نقطه‌های برخورد محیط قطعه با یک خط افقی.
     *
     * @return array<int, array{x: float, y: float}>
     */
    public static function crossings(array $outline, float $y): array
    {
        $outline = array_values($outline);
        $count = count($outline);
        $found = [];

        for ($edge = 0; $edge < $count; $edge++) {
            $samples = Geometry::isCurve($outline[($edge + 1) % $count]) ? Geometry::CURVE_SEGMENTS * 2 : 1;
            $previous = Geometry::pointOnEdge($outline, $edge, 0.0);

            for ($s = 1; $s <= $samples; $s++) {
                $current = Geometry::pointOnEdge($outline, $edge, $s / $samples);

                if (($previous['y'] - $y) * ($current['y'] - $y) <= 0 && abs($current['y'] - $previous['y']) > 1e-9) {
                    $ratio = ($y - $previous['y']) / ($current['y'] - $previous['y']);
                    $found[] = [
                        'x' => round($previous['x'] + (($current['x'] - $previous['x']) * $ratio), 4),
                        'y' => round($y, 4),
                    ];
                }

                $previous = $current;
            }
        }

        // نقطه‌های خیلی نزدیک به هم یکی حساب می‌شوند
        $unique = [];

        foreach ($found as $point) {
            $duplicate = false;

            foreach ($unique as $kept) {
                if (Geometry::distance($kept, $point) < 0.05) {
                    $duplicate = true;
                    break;
                }
            }

            if (! $duplicate) {
                $unique[] = $point;
            }
        }

        return $unique;
    }

    /**
     * ساسون، نشانه، مته و خط نشانه‌ای که بیرون این نیمه افتاده‌اند حذف می‌شوند.
     *
     * @param  array<string, mixed>  $half
     * @return array<string, mixed>
     */
    protected static function keepInside(array $half, array $source): array
    {
        $outline = $half['outline'];
        $inside = function (?array $point) use ($outline): bool {
            if ($point === null || ! isset($point['x'], $point['y'])) {
                return false;
            }

            $at = ['x' => (float) $point['x'], 'y' => (float) $point['y']];

            return Geometry::containsPoint($outline, $at) || Geometry::nearestEdge($outline, $at)['distance'] < 0.15;
        };

        $half['darts'] = array_values(array_filter(
            $source['darts'] ?? [],
            fn ($dart) => $inside($dart['center'] ?? null) && $inside($dart['apex'] ?? null),
        ));

        $half['notches'] = array_values(array_filter(
            $source['notches'] ?? [],
            fn ($notch) => $inside($notch),
        ));

        $half['drills'] = array_values(array_filter(
            $source['drills'] ?? [],
            fn ($drill) => $inside($drill),
        ));

        $half['markers'] = array_values(array_filter(
            $source['markers'] ?? [],
            fn ($marker) => $inside($marker['from'] ?? null) || $inside($marker['to'] ?? null),
        ));

        if (! empty($source['grainline']['from']) && ! $inside($source['grainline']['from'])) {
            $bounds = Geometry::bounds($outline);
            $half['grainline'] = [
                'from' => Geometry::point(($bounds[0] + $bounds[2]) / 2, $bounds[1] + 1),
                'to' => Geometry::point(($bounds[0] + $bounds[2]) / 2, $bounds[3] - 1),
                'label' => 'راستای پارچه',
            ];
        }

        foreach (FullnessRecorder::KINDS as $kind) {
            unset($half['meta'][$kind]);
        }

        foreach (FullnessRecorder::KINDS as $kind) {
            foreach ($source['meta'][$kind] ?? [] as $entry) {
                $half = self::carryFullness($half, $entry, $kind);
            }
        }

        return Outline::reindex($half);
    }

    /**
     * گشادی ثبت‌شده روی لبه‌ای که هنوز در این نیمه هست را با آن می‌برد.
     *
     * @param  array<string, mixed>  $half
     * @return array<string, mixed>
     */
    protected static function carryFullness(array $half, array $entry, string $kind): array
    {
        $tag = $entry['tag'] ?? null;
        $edges = $tag !== null ? Geometry::edgesWithTag($half, $tag) : [];

        if ($edges === []) {
            return $half;
        }

        $half['meta'][$kind][] = array_merge($entry, ['edge' => $edges[0]]);

        return $half;
    }

    /**
     * لبه‌های روی تای پارچه بعد از برش.
     *
     * @return array<int, int>
     */
    protected static function foldEdges(array $source, array $half): array
    {
        $outline = array_values($source['outline'] ?? []);
        $count = count($outline);
        $segments = [];

        foreach ($source['meta']['fold_edges'] ?? [] as $edge) {
            $edge = (int) $edge;

            if ($edge >= 0 && $edge < $count) {
                $segments[] = [
                    ['x' => (float) $outline[$edge]['x'], 'y' => (float) $outline[$edge]['y']],
                    ['x' => (float) $outline[($edge + 1) % $count]['x'], 'y' => (float) $outline[($edge + 1) % $count]['y']],
                ];
            }
        }

        return Outline::refoldEdges($half, $segments);
    }

    /**
     * نشانه‌های جفت روی درز تازه، تا دو قطعه دوباره درست به هم بنشینند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected static function pairNotches(array $pieces, array $options, string $pair): array
    {
        $wanted = $options['notches'] ?? true;

        if ($wanted === false) {
            return $pieces;
        }

        $fractions = is_array($wanted) ? array_map('floatval', $wanted) : [0.5];
        $edgesA = $pieces[0]['meta']['cut_edges'] ?? [];

        if ($edgesA === []) {
            return $pieces;
        }

        $length = PieceOps::edgeLength($pieces[0], $edgesA);

        foreach ($fractions as $position => $fraction) {
            $on = PieceOps::pointAlong($pieces[0], $edgesA, $fraction * $length);

            foreach ($pieces as $index => $half) {
                // همان نقطه روی درز قطعه دوم پیدا می‌شود، پس دو نشانه دقیقاً روبه‌روی هم می‌افتند
                $near = Geometry::nearestEdge($half['outline'], ['x' => $on['x'], 'y' => $on['y']]);

                $half['notches'][] = [
                    'x' => round($near['point']['x'], 2),
                    'y' => round($near['point']['y'], 2),
                    'edge' => $near['edge'],
                    'label' => 'نشانه درز برش',
                    'pair' => $pair.'-'.$position,
                ];

                $pieces[$index] = $half;
            }
        }

        return $pieces;
    }
}
