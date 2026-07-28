<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین زنگوله‌ای.
 *
 * سرآستین دست‌نخورده می‌ماند و آستین از خط بازو به پایین باز می‌شود؛ دم آستین
 * چین نمی‌خورد، فقط گشاد می‌ریزد. لبه پایین کمی گرد بریده می‌شود تا وقتی آستین
 * می‌افتد، دم آستین صاف بایستد.
 */
class SleeveBellGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_bell';
    }

    public function label(): string
    {
        return 'آستین زنگوله‌ای';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.5, 75) + [
            'flare' => [
                'label' => 'گشادی دم آستین', 'min' => 20, 'max' => 160, 'step' => 5, 'default' => 70,
                'unit' => 'درصد', 'hint' => 'درصد گشادتر شدن دم آستین نسبت به دور بازو.',
            ],
            'hem_curve' => [
                'label' => 'گردی لبه دم آستین', 'min' => 0, 'max' => 6, 'step' => 0.5, 'default' => 2,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $flare = max(0.1, ((float) $this->param($params, 'flare', 70)) / 100);

        return [
            'code' => 'sleeve-bell',
            'name' => 'آستین زنگوله‌ای',
            'family' => 'flared',
            'ease_band' => [1.5, 4.0],
            'hem_ratio' => 1 + $flare,
            'hem_drop' => (float) $this->param($params, 'hem_curve', 2),
            'side_bulge' => 1.2,
            'side_bulge_at' => 0.65,
            'notes' => [
                'دم آستین بدون چین گشاد می‌ریزد؛ درزهای دو طرف هم‌اندازه‌اند پس آستین کج نمی‌افتد.',
            ],
        ];
    }
}
