<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Support\Measurements;

/**
 * سایزبندی الگو.
 *
 * قاعده کار همان قاعده استاندارد سایزبندی است:
 *   - رشد افقی از اختلاف دور سینه/کمر/باسن جدول سایز می‌آید و روی یک‌چهارم قطعه
 *     پخش می‌شود؛ هر نقطه به نسبت فاصله‌اش از خط مرکزی جابه‌جا می‌شود.
 *   - رشد عمودی از اختلاف قد می‌آید و به نسبت فاصله نقطه از لبه بالای قطعه اعمال می‌شود.
 *   - ناحیه هر نقطه با مقایسه y آن با خطوط نشانه سینه/کمر/باسن همان قطعه مشخص
 *     می‌شود و بین دو ناحیه، مقدار رشد به صورت خطی میان‌یابی می‌شود.
 */
class GradingService
{
    public function __construct(protected SeamAllowanceService $seams = new SeamAllowanceService) {}

    /**
     * سایزبندی به چند سایز.
     *
     * @param  array<int, string>  $sizes
     * @return array<string, array<int, array<string, mixed>>>  سایز ⇒ قطعه‌های سایزبندی‌شده
     */
    public function grade(Pattern $pattern, array $sizes): array
    {
        $result = [];

        foreach ($sizes as $size) {
            $size = (string) $size;

            if (! isset(Measurements::SIZE_TABLE[$size])) {
                continue;
            }

            $result[$size] = $this->gradeToSize($pattern, $size);
        }

        return $result;
    }

    /**
     * قطعه‌های الگو در یک سایز دیگر.
     *
     * @return array<int, array<string, mixed>>
     */
    public function gradeToSize(Pattern $pattern, string $size): array
    {
        $deltas = $this->deltas($pattern->base_size, $size);

        return $pattern->pieces
            ->map(fn (PatternPiece $piece) => $this->gradePiece($piece, $deltas))
            ->values()
            ->all();
    }

    /** ساخت یک الگوی تازه در سایز خواسته‌شده (الگوی مبنا دست‌نخورده می‌ماند). */
    public function createGradedPattern(Pattern $pattern, string $size): Pattern
    {
        $pieces = $this->gradeToSize($pattern, $size);
        $measurements = array_merge(
            $pattern->measurements ?? [],
            Measurements::fromSize($size),
        );

        $graded = Pattern::create([
            'workshop_id' => $pattern->workshop_id,
            'garment_type_id' => $pattern->garment_type_id,
            'pattern_template_id' => $pattern->pattern_template_id,
            'name' => $pattern->name.' — سایز '.$size,
            'base_size' => $size,
            'measurements' => Measurements::complete($measurements),
            'ease' => $pattern->ease,
            'seam_allowances' => $pattern->seam_allowances,
            'params' => $pattern->params,
            'sewing_relations' => $pattern->sewing_relations,
            'version' => 1,
            'notes' => 'سایزبندی‌شده از الگوی «'.$pattern->name.'» (سایز '.$pattern->base_size.').',
        ]);

        foreach ($pieces as $piece) {
            $graded->pieces()->create($piece);
        }

        $this->seams->apply($graded);

        return $graded->load('pieces');
    }

    /**
     * اختلاف اندازه‌های جدول سایز بین دو سایز.
     *
     * @return array<string, float>
     */
    public function deltas(string $from, string $to): array
    {
        $base = Measurements::SIZE_TABLE[$from] ?? Measurements::SIZE_TABLE['40'];
        $target = Measurements::SIZE_TABLE[$to] ?? Measurements::SIZE_TABLE['40'];

        return [
            'bust' => (float) ($target['bust'] - $base['bust']),
            'waist' => (float) ($target['waist'] - $base['waist']),
            'hip' => (float) ($target['hip'] - $base['hip']),
            'height' => (float) ($target['height'] - $base['height']),
            'shoulder_width' => (float) ($target['shoulder_width'] - $base['shoulder_width']),
            'arm_length' => (float) ($target['arm_length'] - $base['arm_length']),
            'base_height' => (float) $base['height'],
        ];
    }

    /**
     * یک قطعه سایزبندی‌شده، آماده ذخیره.
     *
     * @param  array<string, float>  $deltas
     * @return array<string, mixed>
     */
    protected function gradePiece(PatternPiece $piece, array $deltas): array
    {
        [$minX, $minY, $maxX, $maxY] = $piece->bounds();
        $width = max(0.5, $maxX - $minX);
        $height = max(0.5, $maxY - $minY);

        $zones = $this->zones($piece, $minY, $maxY);
        $verticalGrowth = $deltas['height'] / max(1, $deltas['base_height']);

        $move = function (array $point) use ($minX, $minY, $width, $height, $zones, $deltas, $verticalGrowth) {
            $x = (float) ($point['x'] ?? 0);
            $y = (float) ($point['y'] ?? 0);

            $growth = $this->growthAt($y, $zones, $deltas);
            $ratioX = ($x - $minX) / $width;
            $ratioY = ($y - $minY) / $height;

            $point['x'] = round($x + ($ratioX * $growth), 2);
            $point['y'] = round($y + ($ratioY * $height * $verticalGrowth), 2);

            return $point;
        };

        $moveOutline = fn (array $outline) => array_map(function (array $point) use ($move) {
            $moved = $move($point);

            if (isset($point['cx'], $point['cy'])) {
                $control = $move(['x' => $point['cx'], 'y' => $point['cy']]);
                $moved['cx'] = $control['x'];
                $moved['cy'] = $control['y'];
            }

            return $moved;
        }, array_values($outline));

        $moveNested = function ($items) use ($move) {
            if (! is_array($items)) {
                return $items;
            }

            return array_map(function ($item) use ($move) {
                if (! is_array($item)) {
                    return $item;
                }

                if (isset($item['x'], $item['y'])) {
                    $item = $move($item);
                }

                foreach (['apex', 'apex_lower', 'from', 'to', 'center'] as $key) {
                    if (isset($item[$key]['x'], $item[$key]['y'])) {
                        $item[$key] = $move($item[$key]);
                    }
                }

                if (isset($item['legs']) && is_array($item['legs'])) {
                    $item['legs'] = array_map(
                        fn ($leg) => is_array($leg) && isset($leg['x'], $leg['y']) ? $move($leg) : $leg,
                        $item['legs'],
                    );
                }

                if (isset($item['intake'], $item['legs'][0]['x'], $item['legs'][1]['x'])) {
                    $item['intake'] = round(Geometry::distance($item['legs'][0], $item['legs'][1]), 2);
                }

                return $item;
            }, $items);
        };

        $grainline = $piece->grainline ?? null;

        if (isset($grainline['from']['x'], $grainline['to']['x'])) {
            $grainline['from'] = $move($grainline['from']);
            $grainline['to'] = $move($grainline['to']);
        }

        $graded = [
            'code' => $piece->code,
            'name' => $piece->name,
            'layer' => $piece->layer,
            'cut_quantity' => $piece->cut_quantity,
            'on_fold' => $piece->on_fold,
            'mirror' => $piece->mirror,
            'outline' => $moveOutline($piece->outline ?? []),
            'grainline' => $grainline,
            'darts' => $moveNested($piece->darts ?? []),
            'notches' => $moveNested($piece->notches ?? []),
            'drills' => $moveNested($piece->drills ?? []),
            'pleats' => $moveNested($piece->pleats ?? []),
            'markers' => $moveNested($piece->markers ?? []),
            'edge_allowances' => $piece->edge_allowances ?? [],
            'meta' => array_merge($piece->meta ?? [], ['graded_from' => $piece->id]),
            'sort' => $piece->sort,
        ];

        return Geometry::normalizePiece($graded);
    }

    /**
     * خط سینه/کمر/باسن قطعه؛ اگر نشانه‌ای نبود از نسبت‌های تقریبی استفاده می‌شود.
     *
     * @return array<string, float>
     */
    protected function zones(PatternPiece $piece, float $minY, float $maxY): array
    {
        $markers = collect($piece->markers ?? [])->keyBy(fn ($marker) => $marker['key'] ?? '');
        $meta = $piece->meta ?? [];

        $read = function (string $key) use ($markers, $meta): ?float {
            if (isset($markers[$key]['from']['y'])) {
                return (float) $markers[$key]['from']['y'];
            }

            return isset($meta[$key.'_y']) ? (float) $meta[$key.'_y'] : null;
        };

        return array_filter([
            'bust' => $read('bust'),
            'waist' => $read('waist'),
            'hip' => $read('hip'),
        ], fn ($value) => $value !== null && $value >= $minY - 0.5 && $value <= $maxY + 0.5);
    }

    /**
     * رشد افقی (بر روی یک‌چهارم) در ارتفاع y.
     *
     * @param  array<string, float>  $zones
     * @param  array<string, float>  $deltas
     */
    protected function growthAt(float $y, array $zones, array $deltas): float
    {
        $quarter = fn (string $key) => ($deltas[$key] ?? 0) / 4;

        if ($zones === []) {
            return $quarter('bust');
        }

        $stops = [];

        foreach (['bust', 'waist', 'hip'] as $key) {
            if (isset($zones[$key])) {
                $stops[] = ['y' => (float) $zones[$key], 'growth' => $quarter($key)];
            }
        }

        if ($stops === []) {
            return $quarter('bust');
        }

        usort($stops, fn ($a, $b) => $a['y'] <=> $b['y']);

        if ($y <= $stops[0]['y']) {
            return $stops[0]['growth'];
        }

        $last = $stops[count($stops) - 1];

        if ($y >= $last['y']) {
            return $last['growth'];
        }

        for ($i = 0; $i < count($stops) - 1; $i++) {
            $a = $stops[$i];
            $b = $stops[$i + 1];

            if ($y >= $a['y'] && $y <= $b['y']) {
                $span = max(0.001, $b['y'] - $a['y']);
                $t = ($y - $a['y']) / $span;

                return $a['growth'] + (($b['growth'] - $a['growth']) * $t);
            }
        }

        return $last['growth'];
    }
}
