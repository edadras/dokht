<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;
use ReflectionMethod;
use Throwable;

/**
 * ابزار مشترک کار با خط یقه.
 *
 * هم سبک‌های «خط یقه» و هم سبک‌های «یقه» به یک چیز نیاز دارند: پیداکردن لبه یقه
 * روی قطعه‌های بالاتنه، اندازه‌گرفتن آن، راه‌رفتن روی آن و ساختن قطعه تازه. همه آن
 * کارها این‌جاست تا در هر دو پوشه یکی باشد.
 *
 * قرارداد لبه‌ها همان قرارداد Geometry است: لبه i از نقطه i به نقطه i+1 می‌رود و
 * برچسب آن در meta.edges[i] است. روی قطعه‌های بالاتنه ترتیب نقطه‌ها همیشه این است:
 *   مرکز جلو/پشت ← (لبه یقه) ← سرگردنِ سرشانه ← (سرشانه) ← نوک سرشانه ← (حلقه) ...
 *
 * اگر پوشه Transform (کار همکار) در دسترس باشد، اندازه‌گیری لبه با PieceOps::walk
 * انجام می‌شود؛ وگرنه با Geometry. در هر دو حالت نتیجه با اندازه‌گیری محلی مقایسه
 * می‌شود تا اگر امضای آن کلاس فرق داشت، عدد بی‌ربط وارد درفت نشود.
 */
trait NeckGeometry
{
    /** فاصله‌ای که کمتر از آن دو طول «برابر» حساب می‌شوند. */
    protected const LENGTH_TOLERANCE = 0.1;

    /* ---------------------------------------------------------------------
     |  ساخت قطعه
     * ------------------------------------------------------------------- */

    /**
     * قطعه تازه با همه کلیدهای استاندارد.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function newPiece(array $attributes): array
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

    /** خط راستای پارچه عمودی. */
    protected function grainline(float $x, float $fromY, float $toY, string $label = 'راستای پارچه'): array
    {
        return [
            'from' => Geometry::point($x, $fromY),
            'to' => Geometry::point($x, $toY),
            'label' => $label,
        ];
    }

    /** خط راستای پارچه دلخواه (برای یقه شال و نوارِ مورب). */
    protected function grainlineBetween(array $from, array $to, string $label = 'راستای پارچه'): array
    {
        return [
            'from' => Geometry::point((float) $from['x'], (float) $from['y']),
            'to' => Geometry::point((float) $to['x'], (float) $to['y']),
            'label' => $label,
        ];
    }

    /** خط نشانه روی قطعه. */
    protected function marker(string $key, string $label, float $fromX, float $fromY, float $toX, float $toY): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'from' => Geometry::point($fromX, $fromY),
            'to' => Geometry::point($toX, $toY),
        ];
    }

    /** علامت جفت‌شدن. */
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

    /** سوراخ مته (برای بندکش کلاه و جای دکمه). */
    protected function drill(float $x, float $y, string $label, array $extra = []): array
    {
        return array_merge([
            'x' => round($x, 2),
            'y' => round($y, 2),
            'label' => $label,
        ], $extra);
    }

    /**
     * لایه چسب یک قطعه: همان شکل، با لایه interfacing.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function interfacingOf(array $piece, ?string $name = null, float $trim = 0.0): array
    {
        $copy = $piece;
        $copy['code'] = $piece['code'].'-interfacing';
        $copy['name'] = $name ?? ($piece['name'].' (لایه چسب)');
        $copy['layer'] = 'interfacing';
        $copy['cut_quantity'] = max(1, (int) ($piece['cut_quantity'] ?? 1) - 1);
        $copy['darts'] = [];
        $copy['drills'] = [];
        $copy['pleats'] = [];
        $copy['meta'] = array_merge($piece['meta'] ?? [], [
            'part' => 'interfacing',
            'interfacing_for' => $piece['code'],
            'interfacing' => false,
        ]);

        if ($trim > 0) {
            $copy['meta']['note'] = 'لایه چسب '.$trim.' سانتی‌متر از لبه بیرونی کوچک‌تر بریده شود.';
        }

        return $copy;
    }

    /* ---------------------------------------------------------------------
     |  بردار و زاویه
     * ------------------------------------------------------------------- */

    protected function pt(array $point): array
    {
        return ['x' => (float) ($point['x'] ?? 0), 'y' => (float) ($point['y'] ?? 0)];
    }

    protected function vec(array $from, array $to): array
    {
        return ['x' => (float) $to['x'] - (float) $from['x'], 'y' => (float) $to['y'] - (float) $from['y']];
    }

    protected function length(array $v): float
    {
        return sqrt(($v['x'] * $v['x']) + ($v['y'] * $v['y']));
    }

    protected function unit(array $v): array
    {
        $length = $this->length($v);

        return $length < 1e-9 ? ['x' => 1.0, 'y' => 0.0] : ['x' => $v['x'] / $length, 'y' => $v['y'] / $length];
    }

    protected function add(array $point, array $v, float $scale = 1.0): array
    {
        return ['x' => (float) $point['x'] + ($v['x'] * $scale), 'y' => (float) $point['y'] + ($v['y'] * $scale)];
    }

    protected function dot(array $a, array $b): float
    {
        return ($a['x'] * $b['x']) + ($a['y'] * $b['y']);
    }

    /** چرخاندن بردار (درجه، در دستگاه y-به-پایین). */
    protected function rotateVector(array $v, float $degrees): array
    {
        $r = deg2rad($degrees);
        $cos = cos($r);
        $sin = sin($r);

        return ['x' => ($v['x'] * $cos) - ($v['y'] * $sin), 'y' => ($v['x'] * $sin) + ($v['y'] * $cos)];
    }

    /** زاویه میان دو بردار، ۰ تا ۱۸۰ درجه. */
    protected function angleBetween(array $a, array $b): float
    {
        $la = $this->length($a);
        $lb = $this->length($b);

        if ($la < 1e-9 || $lb < 1e-9) {
            return 90.0;
        }

        $cos = max(-1.0, min(1.0, $this->dot($a, $b) / ($la * $lb)));

        return round(rad2deg(acos($cos)), 2);
    }

    /** برخورد دو خط (نقطه + جهت)؛ اگر موازی بودند null. */
    protected function intersection(array $p, array $u, array $q, array $v): ?array
    {
        $denominator = ($u['x'] * $v['y']) - ($u['y'] * $v['x']);

        if (abs($denominator) < 1e-9) {
            return null;
        }

        $t = ((($q['x'] - $p['x']) * $v['y']) - (($q['y'] - $p['y']) * $v['x'])) / $denominator;

        return ['x' => (float) $p['x'] + ($u['x'] * $t), 'y' => (float) $p['y'] + ($u['y'] * $t)];
    }

    /** قرینه یک نقطه حول خطی که از a به b می‌رود. */
    protected function reflectOver(array $a, array $b, array $point): array
    {
        $d = $this->unit($this->vec($a, $b));
        $ap = $this->vec($a, $point);
        $along = $this->dot($ap, $d);
        $foot = $this->add($a, $d, $along);

        return [
            'x' => (2 * $foot['x']) - (float) $point['x'],
            'y' => (2 * $foot['y']) - (float) $point['y'],
        ];
    }

    /* ---------------------------------------------------------------------
     |  لبه‌ها
     * ------------------------------------------------------------------- */

    /** @return array<int, string> */
    protected function edgeTags(array $piece): array
    {
        return array_values($piece['meta']['edges'] ?? []);
    }

    /** @return array<int, int> */
    protected function edgesWithTag(array $piece, string $tag): array
    {
        $found = [];

        foreach ($this->edgeTags($piece) as $index => $value) {
            if ($value === $tag) {
                $found[] = $index;
            }
        }

        return $found;
    }

    protected function edgeWithTag(array $piece, string $tag): ?int
    {
        return $this->edgesWithTag($piece, $tag)[0] ?? null;
    }

    protected function hasNeckEdge(array $piece): bool
    {
        return $this->edgesWithTag($piece, 'neck') !== [];
    }

    /** قطعه‌ای که خط یقه دارد و تنه است (نه یقه و نه سجاف). */
    protected function isBodyPiece(array $piece): bool
    {
        $part = (string) ($piece['meta']['part'] ?? '');

        if (in_array($part, ['collar', 'facing', 'interfacing', 'lapel', 'hood', 'binding'], true)) {
            return false;
        }

        return $this->hasNeckEdge($piece);
    }

    /** جلو یا پشت بودن قطعه. */
    protected function sideOf(array $piece): string
    {
        $side = $piece['meta']['side'] ?? null;

        if ($side === 'front' || $side === 'back') {
            return $side;
        }

        $part = (string) ($piece['meta']['part'] ?? '');

        return str_contains($part, 'back') ? 'back' : 'front';
    }

    /** طول لبه‌های یقه یک قطعه. */
    protected function neckLengthOf(array $piece): float
    {
        $edges = $this->edgesWithTag($piece, 'neck');

        if ($edges === []) {
            return 0.0;
        }

        return $this->walkEdges($piece, $edges) + (float) ($piece['meta']['button_stand'] ?? 0);
    }

    /**
     * طول خط یقه ترکیب: جلو، پشت و مجموع (نیم‌یقه، چون قطعه‌ها نیم‌قطعه‌اند).
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{front: float, back: float, half: float, full: float, pieces: int}
     */
    protected function necklineLengths(array $pieces): array
    {
        $front = 0.0;
        $back = 0.0;
        $count = 0;

        foreach ($pieces as $piece) {
            if (! $this->isBodyPiece($piece)) {
                continue;
            }

            $length = $this->neckLengthOf($piece);

            if ($length <= 0) {
                continue;
            }

            $count++;

            if ($this->sideOf($piece) === 'back') {
                $back += $length;
            } else {
                $front += $length;
            }
        }

        return [
            'front' => round($front, 2),
            'back' => round($back, 2),
            'half' => round($front + $back, 2),
            'full' => round(($front + $back) * 2, 2),
            'pieces' => $count,
        ];
    }

    /**
     * راه‌رفتن روی چند لبه و اندازه‌گرفتن طول واقعی آن‌ها.
     *
     * اگر ابزار Transform\PieceOps موجود و هم‌امضا باشد از آن استفاده می‌شود؛ نتیجه
     * با اندازه‌گیری محلی سنجیده می‌شود و اگر بیش از ۵٪ فرق داشت کنار گذاشته می‌شود.
     *
     * @param  array<int, int>  $edges
     */
    protected function walkEdges(array $piece, array $edges): float
    {
        $local = round(Geometry::edgesLength($piece['outline'] ?? [], $edges), 3);
        $class = 'App\\Services\\Pattern\\Transform\\PieceOps';

        if (! class_exists($class) || ! method_exists($class, 'walk')) {
            return $local;
        }

        try {
            $method = new ReflectionMethod($class, 'walk');

            if (! $method->isStatic() || $method->getNumberOfParameters() < 2) {
                return $local;
            }

            $value = $class::walk($piece['outline'] ?? [], $edges);

            if (is_numeric($value) && $value > 0 && abs(((float) $value) - $local) <= max(0.05, $local * 0.05)) {
                return round((float) $value, 3);
            }
        } catch (Throwable) {
            // ابزار همکار امضای دیگری دارد؛ با اندازه‌گیری خودمان ادامه می‌دهیم.
        }

        return $local;
    }

    /**
     * ثبت «گشادی افزوده» روی قطعه (چین، پیلی، دراپ).
     *
     * اگر FullnessRecorder همکار موجود باشد ثبت را به او می‌سپاریم، ولی نتیجه فقط
     * وقتی پذیرفته می‌شود که قطعه سالم برگردد و هندسه‌اش دست‌نخورده مانده باشد.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<string, mixed>  $fullness
     * @return array<string, mixed>
     */
    protected function recordFullness(array $piece, array $fullness): array
    {
        $class = 'App\\Services\\Pattern\\Transform\\FullnessRecorder';

        if (class_exists($class) && method_exists($class, 'record')) {
            try {
                $result = $class::record($piece, $fullness);

                if (is_array($result) && isset($result['outline']) && count($result['outline']) === count($piece['outline'])) {
                    return $result;
                }
            } catch (Throwable) {
                // امضای دیگری دارد؛ خودمان ثبت می‌کنیم.
            }
        }

        $piece['meta']['fullness'][] = $fullness;

        return $piece;
    }

    /**
     * نقطه‌ای که پس از پیمودن این فاصله روی زنجیره لبه‌ها می‌رسیم.
     *
     * @param  array<int, int>  $edges
     * @return array{x: float, y: float, edge: int, t: float, reached: bool}
     */
    protected function pointAtDistance(array $outline, array $edges, float $distance): array
    {
        $remaining = max(0.0, $distance);
        $last = ['x' => 0.0, 'y' => 0.0, 'edge' => $edges[0] ?? 0, 't' => 0.0, 'reached' => false];

        foreach ($edges as $edge) {
            $length = Geometry::edgeLength($outline, $edge);

            if ($length <= 0) {
                continue;
            }

            if ($remaining <= $length) {
                $t = $length < 1e-9 ? 0.0 : $remaining / $length;
                $on = Geometry::pointOnEdge($outline, $edge, $t);

                return ['x' => $on['x'], 'y' => $on['y'], 'edge' => $edge, 't' => $t, 'reached' => true];
            }

            $remaining -= $length;
            $on = Geometry::pointOnEdge($outline, $edge, 1.0);
            $last = ['x' => $on['x'], 'y' => $on['y'], 'edge' => $edge, 't' => 1.0, 'reached' => false];
        }

        return $last;
    }

    /**
     * تبدیل یک مسیر باز (نه چندضلعی بسته) به خط شکسته.
     *
     * @param  array<int, array<string, mixed>>  $points
     * @return array<int, array{x: float, y: float}>
     */
    protected function flattenPath(array $points, int $segments = 10): array
    {
        $points = array_values($points);

        if ($points === []) {
            return [];
        }

        $out = [$this->pt($points[0])];

        for ($i = 1; $i < count($points); $i++) {
            $target = $points[$i];
            $from = $out[count($out) - 1];

            if (Geometry::isCurve($target)) {
                for ($s = 1; $s <= $segments; $s++) {
                    $out[] = Geometry::quadraticAt(
                        $from,
                        ['x' => (float) $target['cx'], 'y' => (float) $target['cy']],
                        $this->pt($target),
                        $s / $segments,
                    );
                }

                continue;
            }

            $out[] = $this->pt($target);
        }

        return $out;
    }

    /** طول یک مسیر باز. */
    protected function pathLength(array $points): float
    {
        $flat = $this->flattenPath($points);
        $total = 0.0;

        for ($i = 1; $i < count($flat); $i++) {
            $total += Geometry::distance($flat[$i - 1], $flat[$i]);
        }

        return round($total, 3);
    }

    /* ---------------------------------------------------------------------
     |  کمان دایره‌ای (یقه تخت، یقه چین‌دار، یقه شال)
     * ------------------------------------------------------------------- */

    /**
     * کمان دایره با چند تکه منحنی درجه‌دو.
     *
     * @return array<int, array<string, mixed>> نقطه‌ها بدون نقطه شروع
     */
    protected function arcPoints(array $center, float $radius, float $fromDeg, float $toDeg, int $segments = 0): array
    {
        $span = $toDeg - $fromDeg;
        $segments = $segments > 0 ? $segments : max(1, (int) ceil(abs($span) / 45));
        $step = $span / $segments;
        $points = [];

        for ($i = 1; $i <= $segments; $i++) {
            $a0 = deg2rad($fromDeg + ($step * ($i - 1)));
            $a1 = deg2rad($fromDeg + ($step * $i));
            $mid = ($a0 + $a1) / 2;
            $controlRadius = $radius / max(0.2, cos(deg2rad(abs($step) / 2)));

            $points[] = Geometry::curve(
                $center['x'] + ($radius * cos($a1)),
                $center['y'] + ($radius * sin($a1)),
                $center['x'] + ($controlRadius * cos($mid)),
                $center['y'] + ($controlRadius * sin($mid)),
            );
        }

        return $points;
    }

    protected function arcStart(array $center, float $radius, float $degrees): array
    {
        return Geometry::point(
            $center['x'] + ($radius * cos(deg2rad($degrees))),
            $center['y'] + ($radius * sin(deg2rad($degrees))),
        );
    }

    /* ---------------------------------------------------------------------
     |  جورکردن طول
     * ------------------------------------------------------------------- */

    /**
     * درفت را چند بار با «دستگیره طول» تکرار می‌کند تا لبه یقه قطعه دقیقاً به
     * اندازه خط یقه برسد. همان کاری که خیاط با پیاده‌کردن خط یقه روی یقه می‌کند.
     *
     * @param  callable(float): array<string, mixed>  $draft  طول ⇒ قطعه
     * @return array{0: array<string, mixed>, 1: float, 2: int} قطعه، طول اندازه‌گیری‌شده، تعداد تکرار
     */
    protected function fitNeckEdge(callable $draft, float $target, ?float $start = null): array
    {
        $knob = max(2.0, $start ?? $target);
        $piece = $draft($knob);
        $measured = $this->neckEdgeLength($piece);
        $rounds = 0;

        for ($i = 0; $i < 40; $i++) {
            if (abs($measured - $target) <= 0.02 || $measured <= 0.01) {
                break;
            }

            $rounds++;
            $knob = max(2.0, min(400.0, $knob * ($target / $measured)));
            $piece = $draft($knob);
            $measured = $this->neckEdgeLength($piece);
        }

        return [$piece, round($measured, 3), $rounds];
    }

    /** طول لبه‌های یقه یک قطعه یقه (بدون اضافه جای دکمه). */
    protected function neckEdgeLength(array $piece): float
    {
        return $this->walkEdges($piece, $this->edgesWithTag($piece, 'neck'));
    }

    /* ---------------------------------------------------------------------
     |  دست‌کاری مسیر قطعه
     * ------------------------------------------------------------------- */

    /**
     * چرخاندن فهرست نقطه‌ها و برچسب لبه‌ها با هم (بدون تغییر شکل قطعه).
     *
     * پس از چرخاندن، نقطه‌ای که پیش‌تر شماره $shift داشت نقطه صفر می‌شود.
     */
    protected function rotateOutline(array $piece, int $shift): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $tags = array_values($piece['meta']['edges'] ?? []);
        $count = count($outline);

        if ($count === 0) {
            return $piece;
        }

        $shift = (($shift % $count) + $count) % $count;

        if ($shift === 0) {
            return $piece;
        }

        $piece['outline'] = array_merge(array_slice($outline, $shift), array_slice($outline, 0, $shift));

        if (count($tags) === $count) {
            $piece['meta']['edges'] = array_merge(array_slice($tags, $shift), array_slice($tags, 0, $shift));
        }

        return $this->reindexAnchors($piece);
    }

    /**
     * جای‌گزینی بالای قطعه (لبه یقه و در صورت لزوم سرشانه) با مسیر تازه.
     *
     * $points از نقطه تازه مرکز جلو/پشت شروع می‌شود و به نقطه پایانی (سرگردن یا
     * نقطه‌ای روی حلقه آستین) ختم می‌شود؛ $tags یکی کمتر از $points است.
     *
     * گزینه‌ها:
     *   consume_before   چند نقطه پیش از لبه یقه هم برداشته شود (برای یقه انگلیسی)
     *   consume_shoulder سرشانه هم برداشته شود (یقه شانه‌افتاده و هالتر)
     *   armhole_t        اگر مسیر روی حلقه آستین تمام می‌شود، نسبت آن نقطه روی حلقه
     *   carry_step       پله اضافه جای دکمه با نقطه مرکز جلو پایین بیاید
     *
     * @param  array<int, array<string, mixed>>  $points
     * @param  array<int, string>  $tags
     * @return array<string, mixed>
     */
    protected function reshapeTop(array $piece, int $neckEdge, array $points, array $tags, array $options = []): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $oldTags = array_values($piece['meta']['edges'] ?? []);
        $count = count($outline);
        $points = array_values($points);
        $tags = array_values($tags);

        if ($count < 4 || count($points) < 2 || count($tags) !== count($points) - 1) {
            return $piece;
        }

        $before = max(0, (int) ($options['consume_before'] ?? 0));
        $consume = ! empty($options['consume_shoulder']) ? 1 : 0;
        $start = $neckEdge - $before;
        $end = $neckEdge + 1 + $consume;

        if ($start < 0 || $end > $count - 1) {
            return $piece; // بدون پیچیدن دور قطعه نمی‌شود؛ پیش از این باید rotateOutline شود
        }

        $head = array_slice($outline, 0, $start);
        $headTags = array_slice($oldTags, 0, $start);
        $tail = array_slice($outline, $end + 1);
        $tailTags = array_slice($oldTags, $end + 1);
        $connect = $oldTags[$end] ?? 'default';

        // اگر مسیر روی حلقه آستین تمام شده، نقطه کنترل باقی‌مانده حلقه اصلاح می‌شود
        $t = $options['armhole_t'] ?? null;

        if ($consume === 1 && $t !== null && $tail !== [] && Geometry::isCurve($tail[0])) {
            $control = ['x' => (float) $tail[0]['cx'], 'y' => (float) $tail[0]['cy']];
            $moved = Geometry::lerp($control, $this->pt($tail[0]), (float) $t);
            $tail[0]['cx'] = round($moved['x'], 3);
            $tail[0]['cy'] = round($moved['y'], 3);
        }

        // پله اضافه جای دکمه (پیراهن و کت) با نقطه مرکز جلو پایین می‌آید
        if (($options['carry_step'] ?? true) && $before === 0 && $start > 0) {
            $stepIndex = $start - 1;
            $oldCenter = $this->pt($outline[$start]);
            $step = $this->pt($head[$stepIndex]);

            if (($oldTags[$stepIndex] ?? '') === 'default' && abs($step['y'] - $oldCenter['y']) < 0.02) {
                $head[$stepIndex]['y'] = round((float) $points[0]['y'], 3);

                if (isset($head[$stepIndex]['cy'])) {
                    $head[$stepIndex]['cy'] = round((float) $head[$stepIndex]['cy'] + ((float) $points[0]['y'] - $oldCenter['y']), 3);
                }
            }
        }

        $piece['outline'] = Geometry::round(array_merge($head, $points, $tail));
        $piece['meta']['edges'] = array_merge($headTags, $tags, [$connect], $tailTags);

        if (count($piece['meta']['edges']) !== count($piece['outline'])) {
            $piece['meta']['edges'] = array_pad(
                array_slice($piece['meta']['edges'], 0, count($piece['outline'])),
                count($piece['outline']),
                'default',
            );
        }

        return $piece;
    }

    /**
     * ساسون‌ها و نشانه‌ها پس از تغییر مسیر دوباره به نزدیک‌ترین لبه بسته می‌شوند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function reindexAnchors(array $piece): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);

        if ($count < 3) {
            return $piece;
        }

        $nearest = function (float $x, float $y) use ($outline, $count) {
            $best = 0;
            $bestDistance = INF;

            for ($edge = 0; $edge < $count; $edge++) {
                for ($step = 0; $step <= 8; $step++) {
                    $on = Geometry::pointOnEdge($outline, $edge, $step / 8);
                    $distance = Geometry::distance($on, ['x' => $x, 'y' => $y]);

                    if ($distance < $bestDistance) {
                        $bestDistance = $distance;
                        $best = $edge;
                    }
                }
            }

            return [$best, $bestDistance];
        };

        foreach ($piece['notches'] ?? [] as $index => $notch) {
            if (isset($notch['x'], $notch['y'])) {
                [$edge] = $nearest((float) $notch['x'], (float) $notch['y']);
                $piece['notches'][$index]['edge'] = $edge;
            }
        }

        foreach ($piece['darts'] ?? [] as $index => $dart) {
            if (isset($dart['center']['x'], $dart['center']['y'])) {
                [$edge, $distance] = $nearest((float) $dart['center']['x'], (float) $dart['center']['y']);
                $piece['darts'][$index]['edge'] = $edge;

                if ($distance > 0.75) {
                    $piece['darts'][$index]['off_edge'] = round($distance, 2);
                }
            }
        }

        return $piece;
    }

    /**
     * نشانه‌ها و ساسون‌هایی که با برش خط یقه بی‌جا مانده‌اند: نشانه سرشانه دوباره
     * وسط سرشانه تازه می‌نشیند و اگر سرشانه‌ای نمانده باشد نشانه حذف می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function refreshShoulderAnchors(array $piece): array
    {
        $shoulder = $this->edgeWithTag($piece, 'shoulder');
        $outline = array_values($piece['outline'] ?? []);

        $notches = [];

        foreach ($piece['notches'] ?? [] as $notch) {
            if (($notch['pair'] ?? null) === 'shoulder') {
                if ($shoulder === null) {
                    continue; // سرشانه‌ای نمانده که نشانه‌اش بماند
                }

                $on = Geometry::pointOnEdge($outline, $shoulder, 0.5);
                $notch['x'] = round($on['x'], 2);
                $notch['y'] = round($on['y'], 2);
                $notch['edge'] = $shoulder;
            }

            $notches[] = $notch;
        }

        $piece['notches'] = array_values($notches);

        // ساسون سرشانه: اگر روی لبه سرشانه بوده، به نسبت پیشین روی سرشانه تازه می‌نشیند
        if ($shoulder !== null) {
            foreach ($piece['darts'] ?? [] as $index => $dart) {
                if (($dart['type'] ?? '') !== 'shoulder') {
                    continue;
                }

                $on = Geometry::pointOnEdge($outline, $shoulder, (float) ($dart['edge_t'] ?? 0.5));
                $shift = $this->vec($dart['center'] ?? $on, $on);
                $piece['darts'][$index]['center'] = Geometry::point($on['x'], $on['y']);
                $piece['darts'][$index]['edge'] = $shoulder;

                if (isset($dart['legs']) && is_array($dart['legs'])) {
                    $piece['darts'][$index]['legs'] = array_map(
                        fn ($leg) => is_array($leg) && isset($leg['x'], $leg['y'])
                            ? Geometry::point((float) $leg['x'] + $shift['x'], (float) $leg['y'] + $shift['y'])
                            : $leg,
                        $dart['legs'],
                    );
                }
            }
        }

        return $this->reindexAnchors($piece);
    }

    /** لبه‌هایی که روی خط مرکز قطعه‌اند (دولا بریده می‌شوند). */
    protected function foldEdgesOnCenter(array $piece): array
    {
        if (empty($piece['on_fold'])) {
            return [];
        }

        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);
        $minX = Geometry::bounds($outline)[0];
        $edges = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $this->pt($outline[$i]);
            $b = $this->pt($outline[($i + 1) % $count]);

            if (abs($a['x'] - $minX) < 0.05 && abs($b['x'] - $minX) < 0.05) {
                $edges[] = $i;
            }
        }

        return $edges;
    }

    /**
     * باز کردن قطعه دولا به قطعه کامل (برای خط یقه یک‌طرفه).
     *
     * لبه تای پارچه حذف می‌شود و نیمه قرینه به مسیر می‌چسبد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function unfoldOnCenter(array $piece): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $tags = array_values($piece['meta']['edges'] ?? []);
        $count = count($outline);

        if ($count < 3 || count($tags) !== $count) {
            return $piece;
        }

        $fold = $piece['meta']['fold_edges'][0] ?? $this->foldEdgesOnCenter($piece)[0] ?? null;

        if ($fold === null) {
            return $piece;
        }

        $axis = Geometry::bounds($outline)[0];

        // مسیر باز: از نقطه پس از لبه تا، دور قطعه، تا نقطه پیش از آن
        $open = [];
        $openTags = [];

        for ($i = 0; $i < $count; $i++) {
            $open[] = $outline[(($fold + 1) + $i) % $count];
        }

        for ($i = 0; $i < $count - 1; $i++) {
            $openTags[] = $tags[(($fold + 1) + $i) % $count];
        }

        $n = count($open);
        $mirrorPoint = fn (array $p) => Geometry::point((2 * $axis) - (float) $p['x'], (float) $p['y']);

        $points = $open;
        $newTags = $openTags;

        // نیمه قرینه، وارونه: نقطه i کنترلِ لبه‌ای را می‌گیرد که پیش‌تر به نقطه i+1 می‌رسید
        for ($i = $n - 2; $i >= 1; $i--) {
            $point = $mirrorPoint($open[$i]);
            $source = $open[$i + 1];

            if (Geometry::isCurve($source)) {
                $point = Geometry::curve(
                    $point['x'],
                    $point['y'],
                    (2 * $axis) - (float) $source['cx'],
                    (float) $source['cy'],
                );
            }

            $points[] = $point;
            $newTags[] = $openTags[$i];
        }

        // لبه بسته‌شدن: از قرینه نقطه ۱ به نقطه ۰
        $first = $points[0];

        if (Geometry::isCurve($open[1])) {
            $first = Geometry::curve(
                (float) $open[0]['x'],
                (float) $open[0]['y'],
                (2 * $axis) - (float) $open[1]['cx'],
                (float) $open[1]['cy'],
            );
        }

        $points[0] = $first;
        $newTags[] = $openTags[0];

        $piece['outline'] = Geometry::round($points);
        $piece['meta']['edges'] = $newTags;
        $piece['on_fold'] = false;
        $piece['mirror'] = false;
        $piece['cut_quantity'] = max(1, (int) ($piece['cut_quantity'] ?? 1));
        $piece['meta']['fold_edges'] = [];
        $piece['meta']['unfolded'] = true;

        // ساسون‌ها و نشانه‌ها قرینه می‌شوند تا هر دو نیمه علامت داشته باشند
        foreach (['darts', 'notches', 'drills'] as $key) {
            $items = $piece[$key] ?? [];
            $mirrored = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $copy = $item;

                foreach (['x'] as $coordinate) {
                    if (isset($copy[$coordinate])) {
                        $copy[$coordinate] = round((2 * $axis) - (float) $copy[$coordinate], 2);
                    }
                }

                foreach (['center', 'apex', 'from', 'to'] as $sub) {
                    if (isset($copy[$sub]['x'])) {
                        $copy[$sub]['x'] = round((2 * $axis) - (float) $copy[$sub]['x'], 2);
                    }
                }

                if (isset($copy['legs']) && is_array($copy['legs'])) {
                    $copy['legs'] = array_map(function ($leg) use ($axis) {
                        if (is_array($leg) && isset($leg['x'])) {
                            $leg['x'] = round((2 * $axis) - (float) $leg['x'], 2);
                        }

                        return $leg;
                    }, $copy['legs']);
                }

                $copy['mirrored'] = true;
                $mirrored[] = $copy;
            }

            $piece[$key] = array_merge(array_values($items), $mirrored);
        }

        return $this->reindexAnchors($piece);
    }

    /**
     * مسیر موازی با فاصله ثابت از یک مسیر (برای سجاف).
     *
     * جهت جابه‌جایی از «مرکز حفره یقه» دور می‌شود تا سجاف به سمت داخل قطعه بیفتد.
     *
     * @param  array<int, array{x: float, y: float}>  $path
     * @return array<int, array{x: float, y: float}>
     */
    protected function offsetPath(array $path, float $width, array $away): array
    {
        $count = count($path);
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $previous = $path[max(0, $i - 1)];
            $next = $path[min($count - 1, $i + 1)];
            $tangent = $this->unit($this->vec($previous, $next));
            $normal = ['x' => -$tangent['y'], 'y' => $tangent['x']];

            if ($this->dot($normal, $this->vec($away, $path[$i])) < 0) {
                $normal = ['x' => -$normal['x'], 'y' => -$normal['y']];
            }

            $out[] = $this->add($path[$i], $normal, $width);
        }

        return $out;
    }
}
