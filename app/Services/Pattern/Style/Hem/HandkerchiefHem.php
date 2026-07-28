<?php

namespace App\Services\Pattern\Style\Hem;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * لبه دستمالی: دم لباس به جای خط صاف، نوک‌نوک می‌شود.
 *
 * هر نوک به اندازه «بلندی نوک» پایین‌تر از خط دم می‌آید و بین نوک‌ها لبه بالا
 * می‌رود؛ نقطه پهلو و مرکز سر جایشان می‌مانند تا درزها به هم بخورند.
 */
class HandkerchiefHem extends BaseHem
{
    public static function key(): string
    {
        return 'hem_handkerchief';
    }

    public function label(): string
    {
        return 'لبه دستمالی';
    }

    public function description(): string
    {
        return 'دم لباس نوک‌نوک می‌شود؛ برای پارچه‌های نرم و لباس مجلسی.';
    }

    public function paramsSchema(): array
    {
        return [
            'points' => [
                'label' => 'تعداد نوک در هر نیم‌قطعه', 'min' => 1, 'max' => 5, 'step' => 1, 'default' => 2,
            ],
            'depth' => [
                'label' => 'بلندی نوک', 'min' => 3, 'max' => 30, 'step' => 0.5, 'default' => 12, 'unit' => 'سانتی‌متر',
            ],
            'allowance' => $this->allowanceParam(0.8),
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $count = max(1, (int) $this->num($context, 'points', 2));
        $depth = $this->num($context, 'depth', 12);
        $allowance = $this->num($context, 'allowance', 0.8);
        $names = [];

        foreach ($this->hemHostIndexes($pieces) as $index) {
            $piece = $pieces[$index];
            $edge = $this->edgeWithTag($piece, 'hem');

            if ($edge === null) {
                continue;
            }

            $outline = array_values($piece['outline']);
            $total = count($outline);
            $start = $edge % $total;

            // نقطه‌های تازه روی همان لبه: هر نوک پایین و بین دو نوک بالا
            $inserted = [];
            $steps = $count * 2;

            for ($i = 1; $i < $steps; $i++) {
                $on = Geometry::pointOnEdge($outline, $edge, $i / $steps);
                $isPoint = $i % 2 === 1;

                $inserted[] = Geometry::point($on['x'], $on['y'] + ($isPoint ? $depth : 0.0));
            }

            $points = array_merge(
                array_slice($outline, 0, $start + 1),
                $inserted,
                array_slice($outline, $start + 1),
            );

            $edges = array_values($piece['meta']['edges']);
            $newEdges = array_merge(
                array_slice($edges, 0, $start),
                array_fill(0, count($inserted) + 1, 'hem'),
                array_slice($edges, $start + 1),
            );

            $piece = $this->replaceOutline($piece, $points, $newEdges);
            $piece = $this->setHemAllowance($piece, ($allowance * 2) + 0.3);
            $piece['meta']['hem_style'] = static::key();

            $pieces[$index] = Geometry::normalizePiece($piece);
            $names[] = $piece['name'];
        }

        return $this->result($pieces, [
            $this->note('tip', 'دم لباس دستمالی شد: '.$count.' نوک '.Format::cm($depth)
                .'ی روی هر نیم‌قطعه ('.implode('، ', $names).').'),
            $this->note('warning', 'لبه نوک‌دار را خیلی باریک ('.Format::cm($allowance)
                .') تو بگذارید یا با سردوز تمام کنید؛ نوک‌ها با لبه پهن جمع می‌شوند.'),
            $this->fabricNote($depth, 'نوک‌های لبه دستمالی'),
        ], ['points' => $count, 'depth' => round($depth, 2)]);
    }
}
