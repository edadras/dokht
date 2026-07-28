<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * آستین حلقه‌ای (کپ).
 *
 * فقط بالای بازو را می‌پوشاند: سرآستین برای حلقه کامل درفت می‌شود ولی پایین آن
 * بریده می‌شود، پس آستین تنها روی بخش بالایی حلقه دوخته می‌شود و باقی حلقه با
 * نوار یا سجاف تمام می‌شود. درصد پوشش حلقه پارامتر است.
 */
class SleeveCapGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_cap';
    }

    public function label(): string
    {
        return 'آستین حلقه‌ای (کپ)';
    }

    public function paramsSchema(): array
    {
        $schema = $this->commonSleeveSchema(1.5, 20);
        unset($schema['length_percent'], $schema['hem_ease']);

        return $schema + [
            'cap_drop' => [
                'label' => 'بلندی آستین از سرشانه', 'min' => 5, 'max' => 22, 'step' => 0.5, 'default' => 11,
                'unit' => 'سانتی‌متر',
            ],
            'armhole_cover' => [
                'label' => 'پوشش حلقه آستین', 'min' => 40, 'max' => 100, 'step' => 5, 'default' => 70,
                'unit' => 'درصد', 'hint' => 'باقی حلقه با نوار یا سجاف تمام می‌شود.',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        return [
            'code' => 'sleeve-cap',
            'name' => 'آستین حلقه‌ای',
            'family' => 'set_in',
            'ease_band' => [1.0, 2.5],
            'length' => (float) $this->param($params, 'cap_drop', 11) + 4,
        ];
    }

    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        $cover = max(0.4, min(1.0, ((float) $this->param($params, 'armhole_cover', 70)) / 100));
        $drop = max(5.0, (float) $this->param($params, 'cap_drop', 11));

        $cap = $frame['cap'];
        $capLength = (float) $frame['cap_length'];
        $cut = ($capLength * (1 - $cover)) / 2;

        // نقطه بریدن روی هر نیمه سرآستین، به اندازه کمان از زیر بغل
        $frontT = $this->tAtLength($cap, 0, $cut);
        $backT = $this->tAtLength($cap, 3, Geometry::edgeLength($cap, 3) - $cut);

        [, $frontUpper, $sf] = $this->splitCurve($cap[0], $cap[1], $frontT);
        [$backUpper, , $sb] = $this->splitCurve($cap[3], $cap[4], $backT);

        $mid = ['x' => (float) $frame['center'], 'y' => $drop];
        $control = [
            'x' => (2 * $mid['x']) - (($sf['x'] + $sb['x']) / 2),
            'y' => (2 * $mid['y']) - (($sf['y'] + $sb['y']) / 2),
        ];

        $outline = [
            Geometry::curve($sf['x'], $sf['y'], $control['x'], $control['y']),
            $frontUpper,
            $cap[2],
            $cap[3],
            $backUpper,
        ];

        $edges = ['armhole', 'armhole', 'armhole', 'armhole', 'hem'];

        return [$this->finishSleeve($outline, $edges, $frame, array_merge($spec, [
            'cap_edges' => [0, 1, 2, 3],
            'armhole_share' => $cover,
            'replace_markers' => true,
            'markers' => [
                $this->marker('sleeve_center', 'خط میانی آستین', (float) $frame['center'], 0, (float) $frame['center'], $drop),
            ],
            'notes' => [
                'آستین '.round($cover * 100).' درصد حلقه را می‌پوشاند؛ باقی حلقه با نوار یا سجاف تمام می‌شود.',
            ],
        ]))];
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
}
