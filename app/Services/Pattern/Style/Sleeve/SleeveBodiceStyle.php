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
        $armhole = $this->requireArmhole($pieces);

        if ($armhole !== true) {
            return $armhole;
        }

        $sleeve = $this->existingSleeve($pieces);

        if ($sleeve !== null) {
            return 'این لباس همین حالا آستین جداگانه («'.$sleeve.'») دارد و «'.$this->label().'» بالاتنه را '
                .'دوباره می‌بُرد؛ اول آستین دوخته‌شده را بردارید تا دو آستین روی هم نیفتد.';
        }

        foreach ($this->armholePieces($pieces) as $index) {
            if ($this->bodiceAnchors($pieces[$index]) === null) {
                return 'در «'.($pieces[$index]['name'] ?? 'قطعه').'» ترتیب یقه، سرشانه و حلقه آستین دنبال هم نیست، '
                    .'پس نمی‌شود روی آن سرشانه و حلقه را دوباره برید.';
            }
        }

        return $this->supportsSleeve($pieces, $context);
    }

    /** بررسی ویژه هر سبک؛ پیش‌فرض پذیرش. */
    protected function supportsSleeve(array $pieces, array $context): true|string
    {
        return true;
    }

    /** نام آستینی که همین حالا در الگو هست (اگر باشد). */
    protected function existingSleeve(array $pieces): ?string
    {
        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if ($part === 'sleeve' || str_ends_with($part, '_sleeve')) {
                return (string) ($piece['name'] ?? 'آستین');
            }
        }

        return null;
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

    /* ---------------------------------------------------------------------
     |  نقطه‌های تکیه بالاتنه
     * ------------------------------------------------------------------- */

    /**
     * چهار نقطه‌ای که هر آستینِ یکی‌بریده به آن‌ها تکیه می‌کند: مرکز یقه، سرگردن،
     * نوک سرشانه و زیر بغل — به شرطی که لبه‌های یقه، سرشانه و حلقه پشت سر هم باشند.
     *
     * @return array<string, mixed>|null
     */
    protected function bodiceAnchors(array $piece): ?array
    {
        $tags = Geometry::edgeTags($piece);
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);
        $neck = null;

        foreach ($tags as $index => $tag) {
            if ($tag === 'neck' && ($tags[$index + 1] ?? null) === 'shoulder' && ($tags[$index + 2] ?? null) === 'armhole') {
                $neck = (int) $index;

                break;
            }
        }

        // نقطه‌های ۰ تا ۳ پس از لبه یقه باید بدون پیچیدن دور قطعه در دسترس باشند
        if ($neck === null || $neck + 4 > $count || $count < 5) {
            return null;
        }

        return [
            'side' => $this->isFront($piece) ? 'front' : 'back',
            'neck_edge' => $neck,
            'shoulder_edge' => $neck + 1,
            'armhole_edge' => $neck + 2,
            'side_edge' => $neck + 3,
            'cf' => $this->at($outline, $neck),
            'snp' => $this->at($outline, $neck + 1),
            'tip' => $this->at($outline, $neck + 2),
            'underarm' => $this->at($outline, $neck + 3),
            'neck_length' => round(Geometry::edgeLength($outline, $neck), 3),
            'shoulder_length' => round(Geometry::edgeLength($outline, $neck + 1), 3),
            'armhole_length' => round(Geometry::edgeLength($outline, $neck + 2), 3),
            'side_length' => round(Geometry::edgeLength($outline, $neck + 3), 3),
            'centroid' => Geometry::centroid($outline),
        ];
    }

    /** مختصات ساده یک نقطه مسیر (بدون اطلاعات منحنی). */
    protected function at(array $outline, int $index): array
    {
        $point = array_values($outline)[$index % max(1, count($outline))] ?? ['x' => 0, 'y' => 0];

        return ['x' => (float) $point['x'], 'y' => (float) $point['y']];
    }

    /* ---------------------------------------------------------------------
     |  بردار و مثلث‌بندی
     * ------------------------------------------------------------------- */

    protected function vec(array $from, array $to): array
    {
        return ['x' => (float) $to['x'] - (float) $from['x'], 'y' => (float) $to['y'] - (float) $from['y']];
    }

    protected function len(array $v): float
    {
        return sqrt(($v['x'] * $v['x']) + ($v['y'] * $v['y']));
    }

    protected function unit(array $v): array
    {
        $length = $this->len($v);

        return $length < 1e-9 ? ['x' => 1.0, 'y' => 0.0] : ['x' => $v['x'] / $length, 'y' => $v['y'] / $length];
    }

    protected function dot(array $a, array $b): float
    {
        return ($a['x'] * $b['x']) + ($a['y'] * $b['y']);
    }

    protected function move(array $point, array $direction, float $distance): array
    {
        return [
            'x' => (float) $point['x'] + ($direction['x'] * $distance),
            'y' => (float) $point['y'] + ($direction['y'] * $distance),
        ];
    }

    /** چرخاندن یک بردار به اندازه زاویه (درجه)؛ چون محور y به پایین است، مثبت ساعت‌گرد دیده می‌شود. */
    protected function rotateVector(array $v, float $degrees): array
    {
        $radians = deg2rad($degrees);
        $cos = cos($radians);
        $sin = sin($radians);

        return ['x' => ($v['x'] * $cos) - ($v['y'] * $sin), 'y' => ($v['x'] * $sin) + ($v['y'] * $cos)];
    }

    /** زاویه بین دو بردار، بر حسب درجه و همیشه بین ۰ و ۱۸۰. */
    protected function angleBetween(array $a, array $b): float
    {
        $lengths = $this->len($a) * $this->len($b);

        if ($lengths < 1e-9) {
            return 0.0;
        }

        return rad2deg(acos(max(-1.0, min(1.0, $this->dot($a, $b) / $lengths))));
    }

    /**
     * نقطه‌ای که از $a به فاصله $ra و از $b به فاصله $rb باشد.
     *
     * همان کاری که خیاط با پرگار می‌کند: دو کمان می‌زند و محل برخوردشان را برمی‌دارد.
     * $toward می‌گوید کدام‌یک از دو جواب را می‌خواهیم (جوابِ نزدیک‌تر به آن نقطه).
     * اگر دو دایره به هم نرسند، نزدیک‌ترین حالت ممکن روی خط $a$b برگردانده می‌شود و
     * کلید reached نادرست می‌ماند.
     *
     * @return array{x: float, y: float, reached: bool}
     */
    protected function triangulate(array $a, float $ra, array $b, float $rb, array $toward): array
    {
        $d = $this->len($this->vec($a, $b));

        if ($d < 1e-6) {
            return ['x' => (float) $a['x'] + $ra, 'y' => (float) $a['y'], 'reached' => false];
        }

        $u = $this->unit($this->vec($a, $b));
        $normal = ['x' => -$u['y'], 'y' => $u['x']];

        if ($d > $ra + $rb || $d < abs($ra - $rb)) {
            // دایره‌ها به هم نمی‌رسند: نقطه را به نسبت شعاع‌ها روی خط می‌گذاریم
            $ratio = $ra / max(1e-6, $ra + $rb);

            return [
                'x' => (float) $a['x'] + ($u['x'] * $d * $ratio),
                'y' => (float) $a['y'] + ($u['y'] * $d * $ratio),
                'reached' => false,
            ];
        }

        $along = ((($d * $d) + ($ra * $ra)) - ($rb * $rb)) / (2 * $d);
        $height = sqrt(max(0.0, ($ra * $ra) - ($along * $along)));
        $foot = $this->move($a, $u, $along);

        $first = $this->move($foot, $normal, $height);
        $second = $this->move($foot, $normal, -$height);

        $pick = $this->len($this->vec($first, $toward)) <= $this->len($this->vec($second, $toward)) ? $first : $second;

        return ['x' => $pick['x'], 'y' => $pick['y'], 'reached' => true];
    }

    /**
     * درزی با طول دقیق که شکمش به سمت یک نقطه معین باد می‌کند.
     *
     * @return array<string, mixed> نقطه پایانی مسیر
     */
    protected function bowSeam(array $from, array $to, float $target, array $toward): array
    {
        $chord = $this->len($this->vec($from, $to));

        if ($target <= $chord + 0.005 || $chord < 1e-6) {
            return Geometry::point((float) $to['x'], (float) $to['y']);
        }

        $u = $this->unit($this->vec($from, $to));
        $normal = ['x' => -$u['y'], 'y' => $u['x']];
        $mid = ['x' => ((float) $from['x'] + (float) $to['x']) / 2, 'y' => ((float) $from['y'] + (float) $to['y']) / 2];
        $direction = $this->dot($normal, $this->vec($mid, $toward)) >= 0 ? 1 : -1;

        return $this->seamOfLength($from, $to, $target, $direction);
    }

    /**
     * منحنی‌ای با «شکم» به اندازه خواسته‌شده، که به سمت یک نقطه باد می‌کند.
     *
     * برخلاف seamOfLength که طول را هدف می‌گیرد، این‌جا عمق خمیدگی داده می‌شود؛
     * همان کاری که خیاط با خط‌کش منحنی می‌کند: «یک سانت از خط راست بگیر تو».
     *
     * @return array<string, mixed> نقطه پایانی با اطلاعات منحنی
     */
    protected function curveTo(array $from, array $to, float $depth, array $toward): array
    {
        $chord = $this->len($this->vec($from, $to));

        if ($depth < 0.02 || $chord < 0.05) {
            return Geometry::point((float) $to['x'], (float) $to['y']);
        }

        $u = $this->unit($this->vec($from, $to));
        $normal = ['x' => -$u['y'], 'y' => $u['x']];
        $mid = ['x' => ((float) $from['x'] + (float) $to['x']) / 2, 'y' => ((float) $from['y'] + (float) $to['y']) / 2];
        $sign = $this->dot($normal, $this->vec($mid, $toward)) >= 0 ? 1 : -1;

        return Geometry::curve(
            (float) $to['x'],
            (float) $to['y'],
            $mid['x'] + (2 * $depth * $normal['x'] * $sign),
            $mid['y'] + (2 * $depth * $normal['y'] * $sign),
        );
    }

    /**
     * نقطه‌ای روی یک لبه، به فاصله کمانی معین از سر یا ته آن.
     *
     * @return array{x: float, y: float, t: float}
     */
    protected function alongEdge(array $outline, int $edge, float $distance, bool $fromEnd = false): array
    {
        $length = Geometry::edgeLength($outline, $edge);
        $target = max(0.0, min($length, $fromEnd ? $length - $distance : $distance));
        $t = $this->tAtLength($outline, $edge, $target);
        $point = Geometry::pointOnEdge($outline, $edge, $t);

        return ['x' => (float) $point['x'], 'y' => (float) $point['y'], 't' => $t];
    }

    /**
     * ساسون: دو پا و یک نوک.
     *
     * @return array<string, mixed>
     */
    protected function dart(string $type, string $label, ?int $edge, array $legA, array $legB, array $apex, float $intake): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'edge' => $edge,
            'axis' => 'x',
            'intake' => round($intake, 2),
            'center' => Geometry::point(
                ((float) $legA['x'] + (float) $legB['x']) / 2,
                ((float) $legA['y'] + (float) $legB['y']) / 2,
            ),
            'apex' => Geometry::point((float) $apex['x'], (float) $apex['y']),
            'legs' => [
                Geometry::point((float) $legA['x'], (float) $legA['y']),
                Geometry::point((float) $legB['x'], (float) $legB['y']),
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  پارامترها
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای پاک‌شده: مقدار کاربر یا پیش‌فرض، در بازه مجاز.
     *
     * @return array<string, mixed>
     */
    protected function params(array $context): array
    {
        $given = is_array($context['params'] ?? null) ? $context['params'] : [];
        $clean = [];

        foreach ($this->paramsSchema() as $key => $field) {
            $value = $given[$key] ?? $field['default'];
            $type = $field['type'] ?? 'number';

            if ($type === 'toggle') {
                $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $field['default'];

                continue;
            }

            if ($type === 'select') {
                $clean[$key] = array_key_exists((string) $value, $field['options'] ?? []) ? (string) $value : $field['default'];

                continue;
            }

            $number = is_numeric($value) ? (float) $value : (float) $field['default'];

            if (isset($field['min'])) {
                $number = max((float) $field['min'], $number);
            }

            if (isset($field['max'])) {
                $number = min((float) $field['max'], $number);
            }

            $clean[$key] = $number;
        }

        return $clean;
    }

    /** پارامتر مشترک همه آستین‌ها: تغییر بلندی. */
    protected function lengthField(float $default = 0): array
    {
        return [
            'label' => 'تغییر بلندی آستین', 'min' => -50, 'max' => 12, 'step' => 0.5, 'default' => $default,
            'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی تا مچ؛ منفی یعنی کوتاه‌تر.',
        ];
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
