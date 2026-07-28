<?php

namespace App\Services\Pattern\Style\Detail;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\StyleModifier;
use App\Support\Format;

/**
 * پایه مشترک سبک‌های «جزئیات»: جیب، مچ، کمربند، پاتلت، لبه و بست.
 *
 * این کلاس هیچ سبکی نیست؛ فقط ابزار مشترکی است که همه آن‌ها لازم دارند:
 * پیداکردن قطعه میزبان، اندازه‌گیری طول واقعی یک لبه (منهای ساسون و چین)،
 * بریدن یا بازکردن میزبان، و ساختن قطعه‌های تازه با همان قرارداد ژنراتورها.
 *
 * قرارداد هندسه همان است که در Geometry نوشته شده: سانتی‌متر، x به راست،
 * y به پایین، مبدأ گوشه بالا-چپ هر قطعه و meta.edges برچسب هر لبه.
 */
abstract class DetailStyle implements StyleModifier
{
    /** قطعه‌هایی که «تنه» به حساب می‌آیند. */
    public const BODY_PARTS = ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back', 'front_leg', 'back_leg'];

    /** قطعه‌های جلو (میزبان طبیعی جیب و دکمه). */
    public const FRONT_PARTS = ['front_bodice', 'skirt_front', 'front_leg'];

    /** قطعه‌های پشت. */
    public const BACK_PARTS = ['back_bodice', 'skirt_back', 'back_leg'];

    public function description(): string
    {
        return '';
    }

    public function paramsSchema(): array
    {
        return [];
    }

    public function supports(array $pieces, array $context): true|string
    {
        return true;
    }

    /* ---------------------------------------------------------------------
     |  پارامترها و اندازه‌ها
     * ------------------------------------------------------------------- */

    /** پارامترهای این سبک روی پیش‌فرض‌های خودش. */
    public function defaultParams(): array
    {
        $defaults = [];

        foreach ($this->paramsSchema() as $key => $field) {
            $defaults[$key] = $field['default'] ?? null;
        }

        return $defaults;
    }

    /** @return array<string, mixed> */
    protected function params(array $context): array
    {
        $given = is_array($context['params'] ?? null) ? $context['params'] : [];

        return array_merge($this->defaultParams(), array_filter(
            $given,
            fn ($value) => $value !== null && $value !== '',
        ));
    }

    protected function num(array $context, string $key, float $default = 0.0): float
    {
        $value = $this->params($context)[$key] ?? $default;

        return is_numeric($value) ? (float) $value : $default;
    }

    protected function flag(array $context, string $key, bool $default = false): bool
    {
        $value = $this->params($context)[$key] ?? $default;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    protected function text(array $context, string $key, string $default = ''): string
    {
        $value = $this->params($context)[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    protected function measurement(array $context, string $key, float $fallback = 0.0): float
    {
        $value = (float) (($context['measurements'][$key] ?? 0) ?: 0);

        return $value > 0 ? $value : $fallback;
    }

    /* ---------------------------------------------------------------------
     |  یادداشت و خروجی
     * ------------------------------------------------------------------- */

    /** @return array{type: string, text: string} */
    protected function note(string $type, string $text): array
    {
        return ['type' => $type, 'text' => $text];
    }

    /**
     * خروجی استاندارد یک سبک.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, array<string, string>>  $notes
     */
    protected function result(array $pieces, array $notes = [], array $meta = []): array
    {
        return [
            'pieces' => array_values($pieces),
            'notes' => array_values($notes),
            'meta' => $meta,
        ];
    }

    /** یادداشت مصرف پارچه؛ هرجا جزئیاتی پارچه می‌خورد باید گفته شود. */
    protected function fabricNote(float $amount, string $what): array
    {
        return $this->note('info', $what.' حدود '.Format::cm($amount).' پارچه بیشتر می‌خواهد؛ در چیدمان حساب کنید.');
    }

    /* ---------------------------------------------------------------------
     |  پیداکردن قطعه‌ها
     * ------------------------------------------------------------------- */

    protected function partOf(array $piece): ?string
    {
        return $piece['meta']['part'] ?? null;
    }

    /**
     * شماره قطعه‌هایی که «قسمت» آن‌ها در فهرست خواسته‌شده است.
     *
     * @return array<int, int>
     */
    protected function indexesOfParts(array $pieces, array $parts): array
    {
        $out = [];

        foreach ($pieces as $index => $piece) {
            if (in_array($this->partOf($piece), $parts, true)) {
                $out[] = (int) $index;
            }
        }

        return $out;
    }

    protected function firstIndexOfParts(array $pieces, array $parts): ?int
    {
        return $this->indexesOfParts($pieces, $parts)[0] ?? null;
    }

    /** شماره قطعه‌هایی که یک لبه با برچسب خواسته‌شده دارند. */
    protected function indexesWithTag(array $pieces, string $tag, ?array $parts = null): array
    {
        $out = [];

        foreach ($pieces as $index => $piece) {
            if ($parts !== null && ! in_array($this->partOf($piece), $parts, true)) {
                continue;
            }

            if ($this->edgeWithTag($piece, $tag) !== null) {
                $out[] = (int) $index;
            }
        }

        return $out;
    }

    /** قطعه‌های «تنه بیرونی» که لبه دامنه دارند (آستین و یقه و آستر کنار می‌روند). */
    protected function hemHostIndexes(array $pieces): array
    {
        $lower = $this->indexesWithTag($pieces, 'hem', ['skirt_front', 'skirt_back', 'front_leg', 'back_leg']);

        if ($lower !== []) {
            return $lower;
        }

        return $this->indexesWithTag($pieces, 'hem', ['front_bodice', 'back_bodice']);
    }

    protected function sleeveIndexes(array $pieces): array
    {
        return $this->indexesOfParts($pieces, ['sleeve']);
    }

    /* ---------------------------------------------------------------------
     |  لبه‌ها و طول‌ها
     * ------------------------------------------------------------------- */

    /** @return array<int, int> */
    protected function edgesWithTag(array $piece, string $tag): array
    {
        $out = [];

        foreach ($piece['meta']['edges'] ?? [] as $index => $value) {
            if ($value === $tag) {
                $out[] = (int) $index;
            }
        }

        return $out;
    }

    protected function edgeWithTag(array $piece, string $tag): ?int
    {
        return $this->edgesWithTag($piece, $tag)[0] ?? null;
    }

    protected function edgeLength(array $piece, int $edge): float
    {
        return Geometry::edgeLength($piece['outline'] ?? [], $edge);
    }

    /** طول واقعی دوخت یک لبه: طول منهای ساسون، پیلی و چینِ روی همان لبه. */
    protected function seamLength(array $piece, int $edge): float
    {
        return round(max(0.0, $this->edgeLength($piece, $edge) - $this->consumedOn($piece, $edge)), 2);
    }

    /** مقداری از یک لبه که با ساسون، پیلی یا چین بسته می‌شود. */
    protected function consumedOn(array $piece, int $edge): float
    {
        $consumed = 0.0;

        foreach ($piece['darts'] ?? [] as $dart) {
            if ((int) ($dart['edge'] ?? -1) === $edge) {
                $consumed += (float) ($dart['intake'] ?? 0);
            }
        }

        foreach ($piece['pleats'] ?? [] as $pleat) {
            if ((int) ($pleat['edge'] ?? $edge) === $edge) {
                $consumed += (float) ($pleat['depth'] ?? 0);
            }
        }

        foreach ($piece['meta']['gathers'] ?? [] as $gather) {
            if ((int) ($gather['edge'] ?? -1) === $edge) {
                $consumed += (float) ($gather['amount'] ?? 0);
            }
        }

        return round($consumed, 2);
    }

    /** چند بار این قطعه دور بدن تکرار می‌شود (روی تای پارچه = دو برابر). */
    protected function repeats(array $piece): int
    {
        return ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));
    }

    /**
     * دور واقعی یک برچسب لبه در همه قطعه‌های داده‌شده (مثلاً کل خط کمر یا کل دم دامن).
     *
     * @param  array<int, int>|null  $only  شماره قطعه‌ها؛ خالی یعنی همه
     */
    protected function girth(array $pieces, string $tag, ?array $only = null): float
    {
        $total = 0.0;

        foreach ($pieces as $index => $piece) {
            if ($only !== null && ! in_array((int) $index, $only, true)) {
                continue;
            }

            foreach ($this->edgesWithTag($piece, $tag) as $edge) {
                $total += $this->repeats($piece) * $this->seamLength($piece, $edge);
            }
        }

        return round($total, 2);
    }

    /** جای خط نشانه‌ای مثل «کمر» یا «سینه» روی یک قطعه. */
    protected function markerY(array $piece, string $key): ?float
    {
        foreach ($piece['markers'] ?? [] as $marker) {
            if (($marker['key'] ?? null) === $key && isset($marker['from']['y'])) {
                return (float) $marker['from']['y'];
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     |  ساخت قطعه
     * ------------------------------------------------------------------- */

    /** @param  array<string, mixed>  $attributes */
    protected function piece(array $attributes): array
    {
        $meta = array_merge(['detail' => static::key()], $attributes['meta'] ?? []);

        $piece = array_merge([
            'code' => static::key(),
            'name' => $this->label(),
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
            'sort' => 0,
        ], $attributes, ['meta' => $meta]);

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

    protected function marker(string $key, string $label, float $fromX, float $fromY, float $toX, ?float $toY = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'from' => Geometry::point($fromX, $fromY),
            'to' => Geometry::point($toX, $toY ?? $fromY),
        ];
    }

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

    protected function drill(float $x, float $y, string $type, string $label): array
    {
        return [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'type' => $type,
            'label' => $label,
        ];
    }

    /** مستطیل ساده. */
    protected function rect(float $width, float $height): array
    {
        return [
            Geometry::point(0, 0),
            Geometry::point($width, 0),
            Geometry::point($width, $height),
            Geometry::point(0, $height),
        ];
    }

    /** مستطیل با گوشه‌های پایین گرد (جیب رودوزی گرد). */
    protected function roundedBottom(float $width, float $height, float $radius): array
    {
        $radius = max(0.0, min($radius, $width / 2, $height / 2));

        if ($radius < 0.1) {
            return $this->rect($width, $height);
        }

        return [
            Geometry::point(0, 0),
            Geometry::point($width, 0),
            Geometry::point($width, $height - $radius),
            Geometry::curve($width - $radius, $height, $width, $height),
            Geometry::point($radius, $height),
            Geometry::curve(0, $height - $radius, 0, $height),
        ];
    }

    /** نوک‌دار (کف جیب V شکل). */
    protected function pointedBottom(float $width, float $height, float $point): array
    {
        return [
            Geometry::point(0, 0),
            Geometry::point($width, 0),
            Geometry::point($width, $height - $point),
            Geometry::point($width / 2, $height),
            Geometry::point(0, $height - $point),
        ];
    }

    /* ---------------------------------------------------------------------
     |  تغییر میزبان
     * ------------------------------------------------------------------- */

    /**
     * جای‌گذاری مسیر تازه با برچسب لبه‌های تازه، و پیداکردن دوباره شماره لبه ساسون‌ها.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @param  array<int, string>  $edges
     */
    protected function replaceOutline(array $piece, array $outline, array $edges): array
    {
        $piece['outline'] = Geometry::round(array_values($outline));
        $piece['meta']['edges'] = array_values($edges);

        if (! empty($piece['on_fold'])) {
            $piece['meta']['fold_edges'] = $this->foldEdges($piece);
        }

        return $this->reindexAnchors($piece);
    }

    /** لبه‌هایی که روی خط تا (کمترین x) هستند. */
    protected function foldEdges(array $piece): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);

        if ($count < 3) {
            return [];
        }

        $minX = Geometry::bounds($outline)[0];
        $edges = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $outline[$i];
            $b = $outline[($i + 1) % $count];

            if (abs(((float) $a['x']) - $minX) < 0.05 && abs(((float) $b['x']) - $minX) < 0.05) {
                $edges[] = $i;
            }
        }

        return $edges;
    }

    /** بعد از تغییر مسیر، شماره لبه ساسون‌ها و نشانه‌ها دوباره پیدا می‌شود. */
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
                    $distance = Geometry::distance(
                        Geometry::pointOnEdge($outline, $edge, $step / 8),
                        ['x' => $x, 'y' => $y],
                    );

                    if ($distance < $bestDistance) {
                        $bestDistance = $distance;
                        $best = $edge;
                    }
                }
            }

            return $best;
        };

        foreach ($piece['notches'] ?? [] as $index => $notch) {
            if (isset($notch['x'], $notch['y'])) {
                $piece['notches'][$index]['edge'] = $nearest((float) $notch['x'], (float) $notch['y']);
            }
        }

        foreach ($piece['darts'] ?? [] as $index => $dart) {
            if (isset($dart['center']['x'], $dart['center']['y'])) {
                $piece['darts'][$index]['edge'] = $nearest((float) $dart['center']['x'], (float) $dart['center']['y']);
            }
        }

        return $piece;
    }

    /**
     * ثبت یک «برش» روی قطعه میزبان: خط باز شدن جیب فیلتابی، زیپ یا چاک.
     *
     * برش واقعی سر چرخ زده می‌شود، پس روی الگو با یک خط نشانه، دو سوراخ مته در
     * دو سر و یک ردیف در meta.openings ثبت می‌شود تا در چاپ و خروجی هم بماند.
     */
    protected function addOpening(
        array $piece,
        float $x,
        float $y,
        float $length,
        float $angle = 0.0,
        string $type = 'welt',
        string $label = 'جای باز شدن جیب',
    ): array {
        $radians = deg2rad($angle);
        $toX = $x + ($length * cos($radians));
        $toY = $y + ($length * sin($radians));

        $piece['markers'][] = $this->marker('opening', $label, $x, $y, $toX, $toY);
        $piece['drills'][] = $this->drill($x, $y, 'opening', $label.' (سر اول)');
        $piece['drills'][] = $this->drill($toX, $toY, 'opening', $label.' (سر دوم)');

        $piece['meta']['openings'][] = [
            'type' => $type,
            'label' => $label,
            'length' => round($length, 2),
            'angle' => round($angle, 2),
            'from' => Geometry::point($x, $y),
            'to' => Geometry::point($toX, $toY),
        ];

        return $piece;
    }

    /**
     * جابه‌جایی یک سر لبه به سمت نقطه همسایه‌اش (کوتاه یا بلندکردن یک درز).
     *
     * برای کوتاه‌کردن دم آستین به اندازه بلندی مچ، یا بالابردن لبه پهلو.
     */
    protected function slideVertex(array $piece, int $index, int $towards, float $amount): array
    {
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $index = (($index % $count) + $count) % $count;
        $towards = (($towards % $count) + $count) % $count;

        $from = ['x' => (float) $outline[$index]['x'], 'y' => (float) $outline[$index]['y']];
        $to = ['x' => (float) $outline[$towards]['x'], 'y' => (float) $outline[$towards]['y']];
        $span = Geometry::distance($from, $to);

        if ($span < 0.01) {
            return $piece;
        }

        $t = max(0.0, min(0.95, $amount / $span));
        $moved = Geometry::lerp($from, $to, $t);

        $outline[$index]['x'] = round($moved['x'], 3);
        $outline[$index]['y'] = round($moved['y'], 3);

        if (isset($outline[$index]['cx'], $outline[$index]['cy'])) {
            $outline[$index]['cx'] = round(((float) $outline[$index]['cx']) + ($moved['x'] - $from['x']) * 0.5, 3);
            $outline[$index]['cy'] = round(((float) $outline[$index]['cy']) + ($moved['y'] - $from['y']) * 0.5, 3);
        }

        $piece['outline'] = $outline;

        return $piece;
    }

    /** دو سر یک لبه را به اندازه داده‌شده به سمت نقطه‌های همسایه می‌کشد (لبه بالا می‌آید). */
    protected function raiseEdge(array $piece, int $edge, float $amount): array
    {
        $count = count($piece['outline']);
        $start = $edge % $count;
        $end = ($edge + 1) % $count;

        $piece = $this->slideVertex($piece, $start, ($start - 1 + $count) % $count, $amount);
        $piece = $this->slideVertex($piece, $end, ($end + 1) % $count, $amount);

        return $piece;
    }

    /**
     * بازکردن قطعه‌ای که روی تای پارچه بریده می‌شود، به یک قطعه کامل.
     *
     * برای لبه نامتقارن یا چاک پشت لازم است؛ نیمه دوم آینه نیمه اول است.
     */
    protected function unfold(array $piece): array
    {
        if (empty($piece['on_fold'])) {
            return $piece;
        }

        $outline = array_values($piece['outline']);
        $count = count($outline);
        $edges = array_values($piece['meta']['edges'] ?? array_fill(0, $count, 'default'));
        $fold = $this->foldEdges($piece)[0] ?? null;

        if ($count < 3 || $fold === null) {
            return $piece;
        }

        // مسیر را طوری می‌چرخانیم که لبه تا، آخرین لبه باشد
        $points = [];
        $tags = [];

        for ($i = 1; $i <= $count; $i++) {
            $index = ($fold + $i) % $count;
            $points[] = $outline[$index];
            $tags[] = $edges[$index];
        }

        // نیمه آینه‌ای: همان مسیر برعکس، حول خط تا
        $axis = Geometry::bounds($outline)[0];
        [$mirrorPoints, $mirrorTags] = $this->mirrorPath($points, $tags, $axis);

        $full = array_merge($points, $mirrorPoints);
        $fullTags = array_merge(array_slice($tags, 0, $count - 1), $mirrorTags, [$tags[0]]);

        $piece['on_fold'] = false;
        $piece['cut_quantity'] = max(1, (int) ($piece['cut_quantity'] ?? 1));
        $piece['meta']['fold_edges'] = [];
        $piece['meta']['unfolded'] = true;
        $piece['meta']['fold_x'] = round($axis, 2);

        $piece = $this->mirrorAnchors($piece, $axis);
        $piece = $this->replaceOutline($piece, $full, array_slice($fullTags, 0, count($full)));

        return Geometry::normalizePiece($piece);
    }

    /** ساسون‌ها، نشانه‌ها و سوراخ‌های نیمه اول روی نیمه دوم هم کپی می‌شوند. */
    protected function mirrorAnchors(array $piece, float $axis): array
    {
        $flip = function (array $point) use ($axis) {
            $point['x'] = round((2 * $axis) - ((float) $point['x']), 2);

            return $point;
        };

        foreach (['darts', 'notches', 'drills'] as $key) {
            foreach (array_values($piece[$key] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (isset($item['x'])) {
                    $item = $flip($item);
                }

                foreach (['apex', 'center', 'apex_lower'] as $sub) {
                    if (isset($item[$sub]['x'])) {
                        $item[$sub] = $flip($item[$sub]);
                    }
                }

                if (isset($item['legs']) && is_array($item['legs'])) {
                    $item['legs'] = array_map(fn ($leg) => is_array($leg) && isset($leg['x']) ? $flip($leg) : $leg, $item['legs']);
                }

                $piece[$key][] = $item;
            }
        }

        foreach (array_values($piece['markers'] ?? []) as $marker) {
            if (in_array($marker['key'] ?? '', ['cf', 'cb'], true) || ! isset($marker['from']['x'], $marker['to']['x'])) {
                continue;
            }

            $marker['from'] = $flip($marker['from']);
            $marker['to'] = $flip($marker['to']);
            $piece['markers'][] = $marker;
        }

        return $piece;
    }

    /**
     * آینه‌کردن یک مسیر باز حول خط عمودی؛ ترتیب نقطه‌ها برعکس می‌شود و نقطه کنترل
     * هر منحنی به نقطه بعدی منتقل می‌شود (چون کنترل به نقطه «رسیدن» می‌چسبد).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    protected function mirrorPath(array $points, array $tags, float $axis): array
    {
        $count = count($points);
        $out = [];
        $outTags = [];

        // از یکی‌مانده‌به‌آخر تا نقطه دوم برمی‌گردیم؛ دو سر روی خط تا تکرار نمی‌شوند
        for ($i = $count - 2; $i >= 1; $i--) {
            $source = $points[$i];
            $arriving = $points[$i + 1];

            $point = Geometry::point((2 * $axis) - ((float) $source['x']), (float) $source['y']);

            if (Geometry::isCurve($arriving)) {
                $point = Geometry::curve(
                    (2 * $axis) - ((float) $source['x']),
                    (float) $source['y'],
                    (2 * $axis) - ((float) $arriving['cx']),
                    (float) $arriving['cy'],
                );
            }

            $out[] = $point;
            $outTags[] = $tags[$i];
        }

        return [$out, $outTags];
    }

    /**
     * افزودن «اضافه جلو» (جای دکمه) به یک قطعه که تا حالا روی تای پارچه بود.
     *
     * لبه مرکز به اندازه $extension به بیرون می‌آید و همان قرارداد ژنراتورها
     * (meta.button_stand) ثبت می‌شود تا اندازه‌گیری خط یقه درست بماند.
     */
    protected function addFrontExtension(array $piece, float $extension): array
    {
        $extension = round(max(0.5, $extension), 2);
        $already = (float) ($piece['meta']['button_stand'] ?? 0);

        if ($already >= $extension - 0.01) {
            return $piece;
        }

        $add = round($extension - $already, 2);
        $piece = Geometry::translatePiece($piece, $add, 0);

        $outline = array_values($piece['outline']);
        $count = count($outline);
        $edges = array_values($piece['meta']['edges'] ?? array_fill(0, $count, 'default'));

        $topY = (float) $outline[0]['y'];
        $bottomY = (float) $outline[$count - 1]['y'];
        $bottomTag = $edges[$count - 2] ?? 'hem';

        $points = array_merge(
            [Geometry::point(0, $topY)],
            $outline,
            [Geometry::point(0, $bottomY)],
        );

        $newEdges = array_merge(
            ['default'],
            array_slice($edges, 0, $count - 1),
            [$bottomTag, 'default'],
        );

        // شماره لبه ساسون‌ها و نشانه‌ها یکی جلو می‌رود
        foreach (['darts', 'notches'] as $key) {
            foreach ($piece[$key] ?? [] as $index => $item) {
                if (isset($item['edge']) && is_numeric($item['edge'])) {
                    $piece[$key][$index]['edge'] = ((int) $item['edge']) + 1;
                }
            }
        }

        $piece['on_fold'] = false;
        $piece['mirror'] = true;
        $piece['cut_quantity'] = 2;
        $piece['meta']['fold_edges'] = [];
        $piece['meta']['button_stand'] = $extension;
        $piece['meta']['center_x'] = $extension;

        $piece = $this->replaceOutline($piece, $points, $newEdges);

        $piece['markers'][] = $this->marker('cf', 'خط مرکز جلو', $extension, $topY, $extension, $bottomY);

        return $piece;
    }

    /** خط مرکز جلوی یک قطعه (فاصله از لبه بیرونی). */
    protected function centerX(array $piece): float
    {
        return (float) ($piece['meta']['center_x'] ?? $piece['meta']['button_stand'] ?? 0);
    }

    /**
     * ثبت چین روی یک لبه: هندسه دست نمی‌خورد، پارچه سر چرخ جمع می‌شود.
     */
    protected function recordGather(array $piece, int $edge, float $amount, string $label): array
    {
        if ($amount <= 0.05) {
            return $piece;
        }

        $piece['meta']['gathers'][] = [
            'edge' => $edge,
            'amount' => round($amount, 2),
            'label' => $label,
        ];

        return $piece;
    }

    /** نوار دولا (کمربند، مچ، پاتلت): مستطیل با خط تای وسط. */
    protected function foldedBand(
        float $length,
        float $height,
        string $code,
        string $name,
        array $meta = [],
        int $cut = 1,
    ): array {
        $full = max(1.0, $height) * 2;

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => $cut,
            'outline' => $this->rect($length, $full),
            'grainline' => $this->grainline($length * 0.5, 0.8, $full - 0.8),
            'markers' => [
                $this->marker('fold', 'خط تای وسط', 0, $height, $length),
            ],
            'meta' => array_merge([
                'edges' => ['waist', 'side', 'waist', 'side'],
                'fold_edges' => [],
                'interfacing' => true,
                'finished_height' => round($height, 2),
                'finished_length' => round($length, 2),
            ], $meta),
        ]);
    }
}
