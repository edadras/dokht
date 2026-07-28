<?php

namespace App\Services\Pattern\Style\Hem;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * لبه چاک‌دار: درز پهلو (یا مرکز پشت) از یک نشانه به پایین باز می‌ماند.
 *
 * برای دامن راسته و کت لازم است، وگرنه راه رفتن سخت می‌شود. پشت چاک یک نوار
 * سجاف می‌خورد تا لبه باز، تمیز و سنگین بماند.
 */
class VentedHem extends BaseHem
{
    public static function key(): string
    {
        return 'hem_vented';
    }

    public function label(): string
    {
        return 'لبه چاک‌دار';
    }

    public function description(): string
    {
        return 'درز از یک نشانه به پایین باز می‌ماند تا لباس راحت باشد؛ پشت چاک سجاف می‌خورد.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => [
                'label' => 'بلندی چاک', 'min' => 5, 'max' => 45, 'step' => 0.5, 'default' => 16,
                'unit' => 'سانتی‌متر', 'hint' => 'از دم لباس به بالا اندازه می‌شود.',
            ],
            'place' => [
                'label' => 'جای چاک', 'type' => 'select', 'default' => 'side',
                'options' => ['side' => 'درز پهلو (دو طرف)', 'center_back' => 'مرکز پشت'],
            ],
            'facing' => ['label' => 'سجاف پشت چاک', 'type' => 'toggle', 'default' => true],
            'allowance' => $this->allowanceParam(3.0),
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $height = $this->num($context, 'height', 16);
        $place = $this->text($context, 'place', 'side');
        $allowance = $this->num($context, 'allowance', 3);
        $marked = [];

        foreach ($this->hemHostIndexes($pieces) as $index) {
            $piece = $pieces[$index];
            $isBack = ($piece['meta']['side'] ?? 'front') === 'back';

            if ($place === 'center_back' && ! $isBack) {
                continue;
            }

            $hemEdge = $this->edgeWithTag($piece, 'hem');
            $ends = $hemEdge === null ? null : $this->hemEnds($piece, $hemEdge);

            if ($ends === null) {
                continue;
            }

            $outline = array_values($piece['outline']);
            $anchor = $place === 'center_back' ? $ends['center'] : $ends['side'];
            $x = (float) $outline[$anchor]['x'];
            $y = (float) $outline[$anchor]['y'];

            $piece['notches'][] = $this->notch($x, $y - $height, $hemEdge, 'سر چاک', 'vent');
            $piece['markers'][] = $this->marker('vent', 'چاک', $x, $y - $height, $x, $y);
            $piece['meta']['openings'][] = [
                'type' => 'vent',
                'label' => $place === 'center_back' ? 'چاک مرکز پشت' : 'چاک درز پهلو',
                'length' => round($height, 2),
                'from' => Geometry::point($x, $y - $height),
                'to' => Geometry::point($x, $y),
            ];
            $piece['meta']['vent'] = round($height, 2);

            $piece = $this->setHemAllowance($piece, $allowance + 1);
            $piece['meta']['hem_style'] = static::key();

            $pieces[$index] = $piece;
            $marked[] = $piece['name'];
        }

        if ($marked === []) {
            return $this->result($pieces, [$this->note('warning', 'جای مناسبی برای چاک پیدا نشد.')]);
        }

        $extra = [];
        $notes = [
            $this->note('tip', 'چاک '.Format::cm($height).'ی روی '
                .($place === 'center_back' ? 'مرکز پشت' : 'درز پهلو').' علامت خورد ('.implode('، ', $marked).').'),
            $this->note('warning', 'درز از نشانه «سر چاک» به پایین دوخته نمی‌شود؛ لبه چاک را '
                .Format::cm($allowance).' تو بگذارید.'),
        ];

        if ($this->flag($context, 'facing', true)) {
            $extra[] = $this->piece([
                'code' => 'vent-facing',
                'name' => 'سجاف پشت چاک',
                'layer' => 'interfacing',
                'cut_quantity' => count($marked),
                'outline' => $this->rect($allowance + 2, $height + 4),
                'grainline' => $this->grainline(($allowance + 2) * 0.5, 1, $height + 3),
                'meta' => [
                    'part' => 'vent_facing',
                    'edges' => ['default', 'side', 'hem', 'default'],
                    'fold_edges' => [],
                    'interfacing' => true,
                    'hem_style' => static::key(),
                ],
            ]);

            $notes[] = $this->note('info', 'یک نوار لایی '.Format::cm($allowance + 2).'×'
                .Format::cm($height + 4).' پشت هر چاک می‌خورد تا لبه صاف بماند.');
        }

        return $this->result(array_merge($pieces, $extra), $notes, ['height' => round($height, 2)]);
    }
}
