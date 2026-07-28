<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین دولا (لایه‌ای).
 *
 * دو آستین گشاد با بلندی متفاوت که هر دو روی یک حلقه دوخته می‌شوند؛ لایه رو
 * کوتاه‌تر و گشادتر است و لایه زیر بلندتر، پس آستین دو پله می‌ریزد. هر دو لایه
 * سرآستین کامل دارند و با هم روی حلقه پیاده می‌شوند.
 */
class SleeveLayeredGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_layered';
    }

    public function label(): string
    {
        return 'آستین دولا (لایه‌ای)';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.0, 45) + [
            'top_layer_percent' => [
                'label' => 'بلندی لایه رو', 'min' => 25, 'max' => 95, 'step' => 5, 'default' => 55,
                'unit' => 'درصد لایه زیر',
            ],
            'flare' => [
                'label' => 'گشادی لایه‌ها', 'min' => 20, 'max' => 160, 'step' => 5, 'default' => 60,
                'unit' => 'درصد',
            ],
            'hem_curve' => [
                'label' => 'گردی لبه دم آستین', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $flare = max(0.1, ((float) $this->param($params, 'flare', 60)) / 100);

        return [
            'code' => 'sleeve-layer-under',
            'name' => 'آستین لایه زیر',
            'family' => 'layered',
            'ease_band' => [1.0, 3.0],
            'hem_ratio' => 1 + $flare,
            'hem_drop' => (float) $this->param($params, 'hem_curve', 3),
            'side_bulge' => 1.0,
            'side_bulge_at' => 0.65,
        ];
    }

    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        $share = max(0.25, min(0.95, ((float) $this->param($params, 'top_layer_percent', 55)) / 100));
        $capHeight = (float) $frame['cap_height'];
        $length = (float) $frame['length'];

        $under = $this->sleevePiece($frame, array_merge($spec, [
            'code' => 'sleeve-layer-under',
            'name' => 'آستین لایه زیر',
            'meta' => ['layer_role' => 'under'],
            'notes' => ['لایه زیر بلندتر است و از زیر لایه رو بیرون می‌زند.'],
        ]));

        $topLength = max($capHeight + 3, $capHeight + (($length - $capHeight) * $share));
        $topWidth = (float) $frame['hem_width'] * 0.9;

        $top = $this->sleevePiece(array_merge($frame, [
            'length' => $topLength,
            'hem_width' => $topWidth,
            'elbow_y' => $topLength + 1,
        ]), array_merge($spec, [
            'code' => 'sleeve-layer-top',
            'name' => 'آستین لایه رو',
            'layer' => 'outer',
            'meta' => ['layer_role' => 'top'],
            'notes' => [
                'لایه رو '.round($topLength - $capHeight, 1).' سانتی‌متر از خط بازو پایین می‌آید؛ '
                    .'هر دو لایه با هم روی حلقه دوخته می‌شوند.',
            ],
        ]));

        return [$top, $under];
    }
}
