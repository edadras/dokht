<?php

namespace App\Services\Pattern\Style\Sleeve;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\StyleModifier;

/**
 * پایه مشترک آستین‌هایی که با بالاتنه یکی بریده می‌شوند.
 *
 * رگلان، کیمونو، دولمان، بت‌وینگ، شانه‌افتاده و سدل همگی «سبک» هستند نه
 * تولیدکننده، چون هیچ‌کدام بدون دست‌بردن در بالاتنه معنا ندارند: یا سرشانه و حلقه
 * آستین بالاتنه بریده می‌شود، یا حلقه اصلاً حذف می‌شود و آستین از خود تنه در
 * می‌آید. این کلاس کارهای مشترک را انجام می‌دهد:
 *
 *   - پیداکردن قطعه‌های بالاتنه‌ای که لبه «حلقه آستین» دارند و رد کردن مؤدبانه
 *     وقتی چنین قطعه‌ای نیست.
 *   - بریدن و جابه‌جاکردن نقطه‌های مسیر با شماره‌گذاری دوباره نشانه‌ها، ساسون‌ها،
 *     پیلی‌ها و لبه‌های تای پارچه، تا قطعه بعد از جراحی همچنان معتبر بماند.
 *   - کشیدن یک درز با «طول دقیق خواسته‌شده» بین دو نقطه، که هسته جورشدن درزهای
 *     رگلان و سدل با بالاتنه است.
 */
abstract class SleeveBodiceStyle implements StyleModifier
{
    /** قطعه‌هایی که می‌توانند بالاتنه باشند. */
    public const BODICE_PARTS = ['front_bodice', 'back_bodice'];

    public static function group(): string
    {
        return 'sleeve';
    }

    public function supports(array $pieces, array $context): true|string
    {
        return $this->requireArmhole($pieces);
    }

    /** پیام فارسی وقتی بالاتنه‌ای با حلقه آستین در کار نیست. */
    protected function requireArmhole(array $pieces): true|string
    {
        foreach ($pieces as $piece) {
            if ($this->edgeWithTag($piece, 'armhole') !== null) {
                return true;
            }
        }

        return 'این آستین باید با بالاتنه یکی بریده شود، ولی در این الگو قطعه‌ای با لبه «حلقه آستین» '
            .'پیدا نشد؛ اول یک بالاتنه انتخاب کنید.';
    }

    /**
     * شماره قطعه‌های بالاتنه‌ای که حلقه آستین دارند.
     *
     * @return array<int, int>
     */
    protected function armholePieces(array $pieces): array
    {
        $found = [];

        foreach ($pieces as $index => $piece) {
            if ($this->edgeWithTag($piece, 'armhole') !== null) {
                $found[] = $index;
            }
        }

        return $found;
    }

    protected function isFront(array $piece): bool
    {
        return ($piece['meta']['side'] ?? null) === 'front'
            || ($piece['meta']['part'] ?? '') === 'front_bodice';
    }

    /** شماره اولین لبه با این برچسب. */
    protected function edgeWithTag(array $piece, string $tag): ?int
    {
        foreach ($piece['meta']['edges'] ?? [] as $index => $value) {
            if ($value === $tag) {
                return (int) $index;
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     |  جراحی مسیر قطعه
     * ------------------------------------------------------------------- */

    /**
     * جانشین‌کردن چند نقطه پیاپی مسیر با نقطه‌های تازه و شماره‌گذاری دوباره همه چیز.
     *
     * @param  array<int, array<string, mixed>>  $points  نقطه‌های تازه
     * @param  array<int, string>  $tags  برچسب لبه‌هایی که به هر نقطه تازه می‌رسند
     * @return array<string, mixed>
     */
    protected function replacePoints(array $piece, int $from, int $count, array $points, array $tags): array
    {
        $outline = array_values($piece['outline']);
        $edges = array_values($piece['meta']['edges'] ?? []);
        $total = count($outline);

        $head = array_slice($outline, 0, $from);
        $tail = array_slice($outline, $from + $count);
        $headTags = array_slice($edges, 0, $from);
        $tailTags = array_slice($edges, $from + $count);

        $piece['outline'] = array_merge($head, $points, $tail);
        $piece['meta']['edges'] = array_merge($headTags, $tags, $tailTags);

        $shift = count($points) - $count;

        if ($shift !== 0) {
            $piece = $this->shiftReferences($piece, $from + $count - 1, $shift, $total);
        }

        return $piece;
    }

    /**
     * شماره لبه‌ها را در نشانه‌ها، ساسون‌ها، پیلی‌ها و لبه‌های تا جابه‌جا می‌کند.
     *
     * @return array<string, mixed>
     */
    protected function shiftReferences(array $piece, int $after, int $shift, int $total): array
    {
        $map = function (?int $edge) use ($after, $shift, $total): ?int {
            if ($edge === null) {
                return null;
            }

            $edge = (($edge % $total) + $total) % $total;

            return $edge > $after ? $edge + $shift : $edge;
        };

        foreach (['notches', 'darts', 'pleats'] as $key) {
            foreach ($piece[$key] ?? [] as $index => $item) {
                if (! is_array($item) || ! array_key_exists('edge', $item) || $item['edge'] === null) {
                    continue;
                }

                $piece[$key][$index]['edge'] = $map((int) $item['edge']);
            }
        }

        foreach ($piece['meta']['gathers'] ?? [] as $index => $gather) {
            if (isset($gather['edge'])) {
                $piece['meta']['gathers'][$index]['edge'] = $map((int) $gather['edge']);
            }
        }

        $piece['meta']['fold_edges'] = array_values(array_map(
            fn ($edge) => $map((int) $edge),
            $piece['meta']['fold_edges'] ?? [],
        ));

        return $piece;
    }

    /**
     * نشانه‌هایی که روی لبه‌های حذف‌شده بودند برداشته می‌شوند.
     *
     * @return array<string, mixed>
     */
    protected function dropNotches(array $piece, array $pairs): array
    {
        $piece['notches'] = array_values(array_filter(
            $piece['notches'] ?? [],
            fn (array $notch) => ! in_array($notch['pair'] ?? '', $pairs, true),
        ));

        return $piece;
    }

    /** نشانه ساده. */
    protected function notch(float $x, float $y, int $edge, string $label, ?string $pair = null): array
    {
        return array_filter([
            'x' => round($x, 2),
            'y' => round($y, 2),
            'edge' => $edge,
            'label' => $label,
            'pair' => $pair,
        ], fn ($value) => $value !== null);
    }

    /** خط نشانه. */
    protected function marker(string $key, string $label, float $fromX, float $fromY, float $toX, float $toY): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'from' => Geometry::point($fromX, $fromY),
            'to' => Geometry::point($toX, $toY),
        ];
    }

    /**
     * قطعه تازه با کلیدهای کامل.
     *
     * @return array<string, mixed>
     */
    protected function piece(array $attributes): array
    {
        $piece = array_merge([
            'code' => 'piece',
            'name' => 'قطعه',
            'layer' => 'outer',
            'cut_quantity' => 1,
            'on_fold' => false,
            'mirror' => false,
            'outline' => [],
            'grainline' => null,
            'darts' => [],
            'notches' => [],
            'drills' => [],
            'pleats' => [],
            'markers' => [],
            'edge_allowances' => [],
            'meta' => [],
            'sort' => 0,
        ], $attributes);

        $piece['outline'] = Geometry::round($piece['outline']);

        return Geometry::normalizePiece($piece);
    }

    protected function grainline(float $x, float $fromY, float $toY): array
    {
        return [
            'from' => Geometry::point($x, $fromY),
            'to' => Geometry::point($x, $toY),
            'label' => 'راستای پارچه',
        ];
    }

    /* ---------------------------------------------------------------------
     |  ابزار هندسی
     * ------------------------------------------------------------------- */

    /**
     * شکستن یک لبه در نسبت t.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array{x: float, y: float}}
     */
    protected function splitEdge(array $outline, int $edge, float $t): array
    {
        $outline = array_values($outline);
        $count = count($outline);
        $from = $outline[$edge % $count];
        $to = $outline[($edge + 1) % $count];

        $p0 = ['x' => (float) $from['x'], 'y' => (float) $from['y']];
        $p1 = ['x' => (float) $to['x'], 'y' => (float) $to['y']];

        if (! Geometry::isCurve($to)) {
            $mid = Geometry::lerp($p0, $p1, $t);

            return [Geometry::point($mid['x'], $mid['y']), Geometry::point($p1['x'], $p1['y']), $mid];
        }

        $control = ['x' => (float) $to['cx'], 'y' => (float) $to['cy']];
        $q0 = Geometry::lerp($p0, $control, $t);
        $q1 = Geometry::lerp($control, $p1, $t);
        $mid = Geometry::lerp($q0, $q1, $t);

        return [
            Geometry::curve($mid['x'], $mid['y'], $q0['x'], $q0['y']),
            Geometry::curve($p1['x'], $p1['y'], $q1['x'], $q1['y']),
            $mid,
        ];
    }

    /** نسبت t روی یک لبه که تا آنجا طول کمان به مقدار خواسته‌شده می‌رسد. */
    protected function tAtLength(array $outline, int $edge, float $target): float
    {
        $total = Geometry::edgeLength($outline, $edge);

        if ($total <= 0.01 || $target <= 0) {
            return 0.0;
        }

        if ($target >= $total) {
            return 1.0;
        }

        $walk = function (float $t) use ($outline, $edge) {
            $length = 0.0;
            $previous = Geometry::pointOnEdge($outline, $edge, 0.0);

            for ($i = 1; $i <= 24; $i++) {
                $current = Geometry::pointOnEdge($outline, $edge, ($t * $i) / 24);
                $length += Geometry::distance($previous, $current);
                $previous = $current;
            }

            return $length;
        };

        $low = 0.0;
        $high = 1.0;

        for ($i = 0; $i < 24; $i++) {
            $mid = ($low + $high) / 2;

            if ($walk($mid) < $target) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    /** طول یک زنجیره از نقطه‌ها که با یک نقطه شروع می‌شود. */
    protected function chainLength(array $start, array $chain): float
    {
        $outline = array_merge([$start], $chain);
        $indexes = range(0, count($chain) - 1);

        return Geometry::edgesLength($outline, $indexes);
    }

    /**
     * درزی با طول دقیقاً خواسته‌شده بین دو نقطه.
     *
     * نقطه کنترل روی عمودمنصف جابه‌جا می‌شود تا طول کمان به هدف برسد؛ همان کاری
     * که خیاط با «پیاده‌کردن» درز روی الگو انجام می‌دهد.
     *
     * @return array<string, mixed> نقطه پایانی با اطلاعات منحنی
     */
    protected function seamOfLength(array $from, array $to, float $target, int $direction = 1): array
    {
        $chord = Geometry::distance($from, $to);

        if ($target <= $chord + 0.005) {
            return Geometry::point($to['x'], $to['y']);
        }

        $dx = $to['x'] - $from['x'];
        $dy = $to['y'] - $from['y'];
        $nx = ($dy / max(0.0001, $chord)) * $direction;
        $ny = (-$dx / max(0.0001, $chord)) * $direction;
        $mid = Geometry::lerp($from, $to, 0.5);

        $lengthAt = function (float $bulge) use ($from, $to, $mid, $nx, $ny) {
            $control = ['x' => $mid['x'] + (2 * $bulge * $nx), 'y' => $mid['y'] + (2 * $bulge * $ny)];
            $outline = [
                Geometry::point($from['x'], $from['y']),
                Geometry::curve($to['x'], $to['y'], $control['x'], $control['y']),
            ];

            return Geometry::edgeLength($outline, 0);
        };

        $low = 0.0;
        $high = max(1.0, $chord);

        while ($lengthAt($high) < $target && $high < $chord * 8 + 40) {
            $high *= 1.6;
        }

        for ($i = 0; $i < 40; $i++) {
            $bulge = ($low + $high) / 2;

            if ($lengthAt($bulge) < $target) {
                $low = $bulge;
            } else {
                $high = $bulge;
            }
        }

        $bulge = ($low + $high) / 2;

        return Geometry::curve(
            $to['x'],
            $to['y'],
            $mid['x'] + (2 * $bulge * $nx),
            $mid['y'] + (2 * $bulge * $ny),
        );
    }

    /**
     * زنجیره‌ای از درزها با طول‌های دقیق، روی خط راست بین دو سر پخش می‌شود.
     *
     * @param  array<int, array{length: float, direction?: int}>  $segments
     * @return array<int, array<string, mixed>>
     */
    protected function seamChain(array $from, array $to, array $segments): array
    {
        $total = array_sum(array_column($segments, 'length'));

        if ($total <= 0.01) {
            return [Geometry::point($to['x'], $to['y'])];
        }

        $points = [];
        $current = $from;
        $walked = 0.0;

        foreach ($segments as $index => $segment) {
            $walked += (float) $segment['length'];
            $t = $walked / $total;
            $target = $index === count($segments) - 1 ? $to : Geometry::lerp($from, $to, $t);

            $points[] = $this->seamOfLength($current, $target, (float) $segment['length'], (int) ($segment['direction'] ?? 1));
            $current = $target;
        }

        return $points;
    }

    /**
     * همه نقطه‌های یک قطعه را با یک تابع جابه‌جا می‌کند (مسیر، نشانه، ساسون، خط راستا).
     *
     * @param  callable(float, float): array{0: float, 1: float}  $move
     * @return array<string, mixed>
     */
    protected function transformPiece(array $piece, callable $move): array
    {
        $point = function (array $p) use ($move) {
            [$x, $y] = $move((float) ($p['x'] ?? 0), (float) ($p['y'] ?? 0));
            $p['x'] = round($x, 3);
            $p['y'] = round($y, 3);

            if (isset($p['cx'], $p['cy'])) {
                [$cx, $cy] = $move((float) $p['cx'], (float) $p['cy']);
                $p['cx'] = round($cx, 3);
                $p['cy'] = round($cy, 3);
            }

            return $p;
        };

        $piece['outline'] = array_map($point, array_values($piece['outline'] ?? []));

        if (! empty($piece['grainline']['from'])) {
            $piece['grainline']['from'] = $point($piece['grainline']['from']);
            $piece['grainline']['to'] = $point($piece['grainline']['to']);
        }

        foreach (['darts', 'notches', 'drills', 'pleats', 'markers'] as $key) {
            foreach ($piece[$key] ?? [] as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (isset($item['x'], $item['y'])) {
                    $item = $point($item);
                }

                foreach (['apex', 'from', 'to', 'center', 'apex_lower'] as $sub) {
                    if (isset($item[$sub]['x'], $item[$sub]['y'])) {
                        $item[$sub] = $point($item[$sub]);
                    }
                }

                if (isset($item['legs']) && is_array($item['legs'])) {
                    $item['legs'] = array_map(
                        fn ($leg) => is_array($leg) && isset($leg['x'], $leg['y']) ? $point($leg) : $leg,
                        $item['legs'],
                    );
                }

                $piece[$key][$index] = $item;
            }
        }

        return $piece;
    }

    /** اندازه بدن با پیش‌فرض. */
    protected function measurement(array $context, string $key, float $fallback): float
    {
        $value = (float) ($context['measurements'][$key] ?? 0);

        return $value > 0 ? $value : $fallback;
    }

    /** پارامتر عددی سبک. */
    protected function number(array $context, string $key, float $default): float
    {
        $value = $context['params'][$key] ?? $default;

        return is_numeric($value) ? (float) $value : $default;
    }

    /** پارامتر بله/خیر سبک. */
    protected function toggle(array $context, string $key, bool $default): bool
    {
        $value = $context['params'][$key] ?? $default;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
