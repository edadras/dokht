<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * دامن پاکتی (راپ).
 *
 * دو پنل جلو که روی هم می‌افتند و با بند بسته می‌شوند. هم‌پوشانی جزو دور کمر نیست:
 * دور کمر تمام‌شده همان کمر بدن به‌علاوه آزادی است و هم‌پوشانی روی آن اضافه می‌شود.
 */
class SkirtWrapGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_wrap';
    }

    public function label(): string
    {
        return 'دامن پاکتی (راپ)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(62, 30, 115),
            [
                'overlap' => [
                    'label' => 'هم‌پوشانی جلو', 'min' => 8, 'max' => 40, 'step' => 1, 'default' => 20,
                    'unit' => 'سانتی‌متر',
                    'hint' => 'هر پنل جلو این‌قدر از خط مرکز جلو رد می‌شود.',
                ],
                'flare' => [
                    'label' => 'گشادی دم دامن', 'min' => 0, 'max' => 30, 'step' => 1, 'default' => 8,
                    'unit' => 'سانتی‌متر',
                ],
                'tie_length' => [
                    'label' => 'بلندی بند کمر', 'min' => 25, 'max' => 130, 'step' => 5, 'default' => 70,
                    'unit' => 'سانتی‌متر',
                ],
                'tie_width' => [
                    'label' => 'پهنای بند کمر', 'min' => 2, 'max' => 10, 'step' => 0.5, 'default' => 4,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 62);
        $flare = (float) $this->param($params, 'flare', 8);
        $overlap = max(2.0, (float) $this->param($params, 'overlap', 20));

        $pieces = [
            $this->blockPanel($mx, [
                'side' => 'front',
                'length' => $length,
                'hem_delta' => $flare,
                'dart_count' => 1,
                'overlap' => $overlap,
                'overlap_hem' => -$overlap,
                'code' => 'wrap-front',
                'name' => 'پنل جلوی پاکتی',
            ]),
            $this->blockPanel($mx, [
                'side' => 'back',
                'length' => $length,
                'hem_delta' => $flare,
                'dart_count' => 2,
                'name' => 'دامن پاکتی پشت',
            ]),
            $this->tiePiece(
                (float) $this->param($params, 'tie_length', 70),
                (float) $this->param($params, 'tie_width', 4),
            ),
        ];

        return $this->finish(array_merge($pieces, $this->bandPieces($mx, $params, [
            'overlap' => $overlap / 2,
            'notes' => ['کمربند به اندازه دور کمر به‌علاوه نیمِ هم‌پوشانی بریده می‌شود تا سر آن از جلو رد شود.'],
        ])));
    }

    /** بند کمر: نوار دولا. */
    protected function tiePiece(float $length, float $width): array
    {
        $length = max(20.0, $length);
        $width = max(1.5, $width);

        return $this->piece([
            'code' => 'wrap-tie',
            'name' => 'بند کمر',
            'cut_quantity' => 2,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($length, 0),
                Geometry::point($length, $width * 2),
                Geometry::point(0, $width * 2),
            ],
            'grainline' => $this->grainline($length * 0.5, 1, ($width * 2) - 1),
            'markers' => [$this->marker('fold', 'خط تای بند', 0, $width, $length)],
            'meta' => [
                'part' => 'tie',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'tie_length' => round($length, 2),
            ],
        ]);
    }
}
