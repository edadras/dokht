<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * جیب مورب (جیب جینی): گوشه بین خط کمر و درز پهلو از خود لباس بریده می‌شود.
 *
 * این تنها جیبی است که واقعاً مسیر قطعه میزبان را عوض می‌کند. آنچه از میزبان کم
 * می‌شود با «کیسه رو» — همان تکه‌ای که در درز کمر و پهلو گرفتار می‌شود — برمی‌گردد،
 * پس دور کمر و طول درز پهلو دست‌نخورده می‌ماند.
 */
class SlashPocket extends BasePocket
{
    public static function key(): string
    {
        return 'pocket_slash';
    }

    public function label(): string
    {
        return 'جیب مورب';
    }

    public function description(): string
    {
        return 'دهانه اریب از خط کمر تا درز پهلو؛ گوشه لباس بریده و با کیسه رو برگردانده می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'host' => [
                'label' => 'روی کدام طرف', 'type' => 'select', 'default' => 'front',
                'options' => ['front' => 'جلو', 'back' => 'پشت'],
            ],
            'from_waist' => [
                'label' => 'دهانه روی خط کمر', 'min' => 3, 'max' => 16, 'step' => 0.5, 'default' => 8,
                'unit' => 'سانتی‌متر', 'hint' => 'از درز پهلو به سمت مرکز.',
            ],
            'down_side' => [
                'label' => 'دهانه روی درز پهلو', 'min' => 5, 'max' => 24, 'step' => 0.5, 'default' => 15, 'unit' => 'سانتی‌متر',
            ],
            'curved' => ['label' => 'دهانه گرد باشد', 'type' => 'toggle', 'default' => true],
            'bag_depth' => ['label' => 'عمق کیسه', 'min' => 10, 'max' => 30, 'step' => 0.5, 'default' => 20, 'unit' => 'سانتی‌متر'],
        ];
    }

    protected function supportsPocket(array $pieces, array $context): true|string
    {
        if ($this->corner($pieces, $context) === null) {
            return 'جیب مورب به گوشه‌ای بین خط کمر و درز پهلو نیاز دارد؛ این لباس چنین گوشه‌ای ندارد.';
        }

        return true;
    }

    /**
     * گوشه‌ای که بریده می‌شود: لبه کمر و لبه پهلویی که پشت سر هم می‌آیند.
     *
     * @return array{index: int, waist: int, side: int}|null
     */
    protected function corner(array $pieces, array $context): ?array
    {
        foreach ($this->indexesOfParts($pieces, $this->hostParts($context)) as $index) {
            $piece = $pieces[$index];
            $count = count($piece['outline'] ?? []);
            $edges = $piece['meta']['edges'] ?? [];

            foreach ($this->edgesWithTag($piece, 'waist') as $waist) {
                $next = ($waist + 1) % max(1, $count);

                if (($edges[$next] ?? null) === 'side') {
                    return ['index' => (int) $index, 'waist' => $waist, 'side' => $next];
                }
            }
        }

        return null;
    }

    public function apply(array $pieces, array $context): array
    {
        $corner = $this->corner($pieces, $context);

        if ($corner === null) {
            return $this->result($pieces, [$this->note('warning', 'گوشه کمر و پهلو برای جیب مورب پیدا نشد.')]);
        }

        $host = $pieces[$corner['index']];
        $outline = array_values($host['outline']);
        $count = count($outline);
        $edges = array_values($host['meta']['edges']);

        $waistEdge = $corner['waist'];
        $sideEdge = $corner['side'];
        $cornerIndex = ($waistEdge + 1) % $count;

        $waistLength = Geometry::edgeLength($outline, $waistEdge);
        $sideLength = Geometry::edgeLength($outline, $sideEdge);

        $fromWaist = min($this->num($context, 'from_waist', 8), $waistLength * 0.55);
        $downSide = min($this->num($context, 'down_side', 15), $sideLength * 0.8);
        $curved = $this->flag($context, 'curved', true);

        // نقطه شروع دهانه روی خط کمر و نقطه پایان روی درز پهلو
        $a = Geometry::pointOnEdge($outline, $waistEdge, max(0.0, 1 - ($fromWaist / max(0.01, $waistLength))));
        $tSide = min(0.95, $downSide / max(0.01, $sideLength));
        $b = Geometry::pointOnEdge($outline, $sideEdge, $tSide);

        $cornerPoint = ['x' => (float) $outline[$cornerIndex]['x'], 'y' => (float) $outline[$cornerIndex]['y']];
        $middle = Geometry::lerp($a, $b, 0.5);
        $control = Geometry::lerp($cornerPoint, $middle, 0.35);

        // مسیر تازه: گوشه برداشته می‌شود و به جایش دهانه A→B می‌نشیند
        $points = [];

        foreach ($outline as $i => $point) {
            if ($i === $cornerIndex) {
                $points[] = Geometry::point($a['x'], $a['y']);
                $points[] = $curved
                    ? Geometry::curve($b['x'], $b['y'], $control['x'], $control['y'])
                    : Geometry::point($b['x'], $b['y']);

                continue;
            }

            $points[] = $point;
        }

        // نیمه باقی‌مانده درز پهلو نقطه کنترل خودش را کوتاه‌شده نگه می‌دارد
        $after = ($cornerIndex + 1) % $count;

        if (Geometry::isCurve($outline[$after])) {
            $rest = Geometry::lerp(
                ['x' => (float) $outline[$after]['cx'], 'y' => (float) $outline[$after]['cy']],
                ['x' => (float) $outline[$after]['x'], 'y' => (float) $outline[$after]['y']],
                $tSide,
            );

            $position = ($cornerIndex + 2) % ($count + 1);
            $points[$position]['cx'] = round($rest['x'], 3);
            $points[$position]['cy'] = round($rest['y'], 3);
        }

        // فقط یک لبه اضافه شده است: خود دهانه، درست جای گوشه قدیمی
        $newEdges = $edges;
        array_splice($newEdges, $cornerIndex, 0, ['default']);

        $total = count($points);
        $aIndex = $cornerIndex;

        $host = $this->replaceOutline($host, $points, $newEdges);
        $host['meta']['pockets'][] = [
            'key' => static::key(),
            'label' => 'جیب مورب',
            'x' => round($a['x'], 2),
            'y' => round($a['y'], 2),
            'width' => round($fromWaist, 2),
            'height' => round($downSide, 2),
        ];
        $host['notches'][] = $this->notch($a['x'], $a['y'], $aIndex, 'سر دهانه جیب مورب', 'slash_pocket');
        $host['notches'][] = $this->notch($b['x'], $b['y'], ($aIndex + 1) % $total, 'ته دهانه جیب مورب', 'slash_pocket');
        $host = Geometry::normalizePiece($host);

        $pieces[$corner['index']] = $host;

        // کیسه رو: همان گوشه‌ای که بریده شد؛ دوباره در درز کمر و پهلو گرفتار می‌شود
        $openingLength = round($this->edgeLength($host, $aIndex), 2);

        // کیسه رو همان مثلث بریده‌شده است: A روی کمر، گوشه، و B روی پهلو
        $localCorner = ['x' => $fromWaist, 'y' => 0.0];
        $localMiddle = Geometry::lerp(['x' => 0.0, 'y' => 0.0], ['x' => $fromWaist, 'y' => $downSide], 0.5);
        $localControl = Geometry::lerp($localCorner, $localMiddle, 0.35);

        $sideOutline = [
            $curved
                ? Geometry::curve(0, 0, $localControl['x'], $localControl['y'])
                : Geometry::point(0, 0),
            Geometry::point($fromWaist, 0),
            Geometry::point($fromWaist, $downSide),
        ];

        $facing = $this->piece([
            'code' => 'pocket-slash-side',
            'name' => 'کیسه رو (پیش‌جیب)',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $sideOutline,
            'grainline' => $this->grainline($fromWaist * 0.5, 1, $downSide - 1),
            'meta' => [
                'part' => 'pocket_facing',
                'edges' => ['waist', 'side', 'default'],
                'fold_edges' => [],
                'pocket' => static::key(),
                'restores' => ['waist' => round($fromWaist, 2), 'side' => round($downSide, 2)],
            ],
        ]);

        $bag = $this->bagPiece(
            $fromWaist + 8,
            $this->num($context, 'bag_depth', 20),
            'pocket-slash-bag',
            'کیسه جیب مورب',
            4,
        );

        $notes = [
            $this->note('tip', 'گوشه کمر و پهلو بریده شد: '.Format::cm($fromWaist).' از خط کمر و '
                .Format::cm($downSide).' از درز پهلو؛ دهانه جیب '.Format::cm($openingLength).' شد.'),
            $this->note('info', 'کیسه رو دقیقاً همان اندازه بریده‌شده را در درز کمر و درز پهلو برمی‌گرداند، '
                .'پس دور کمر و طول درز پهلو تغییری نمی‌کند.'),
            $this->note('warning', 'دهانه اریب کش می‌آید؛ روی خط دهانه نوار لایی بچسبانید.'),
        ];

        return $this->result(array_merge($pieces, [$facing, $bag]), $notes, [
            'opening' => $openingLength,
            'from_waist' => round($fromWaist, 2),
            'down_side' => round($downSide, 2),
        ]);
    }
}
