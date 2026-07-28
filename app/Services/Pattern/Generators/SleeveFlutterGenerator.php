<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین چین‌دار پروانه‌ای.
 *
 * آستینی کوتاه و خیلی گشاد که مثل بال پروانه می‌ریزد: دم آستین چند برابر دور بازو
 * بریده می‌شود و لبه‌اش گرد است تا موج بخورد. کمی چین روی سرآستین اختیاری است.
 */
class SleeveFlutterGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_flutter';
    }

    public function label(): string
    {
        return 'آستین چین‌دار پروانه‌ای';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.0, 25) + [
            'flare' => [
                'label' => 'گشادی دم آستین', 'min' => 40, 'max' => 250, 'step' => 10, 'default' => 120,
                'unit' => 'درصد',
            ],
            'cap_gather' => [
                'label' => 'چین سرشانه', 'min' => 0, 'max' => 60, 'step' => 5, 'default' => 15, 'unit' => 'درصد',
            ],
            'hem_curve' => [
                'label' => 'گردی لبه دم آستین', 'min' => 0, 'max' => 10, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $flare = max(0.3, ((float) $this->param($params, 'flare', 120)) / 100);
        $gather = max(0.0, ((float) $this->param($params, 'cap_gather', 15)) / 100);

        return [
            'code' => 'sleeve-flutter',
            'name' => 'آستین پروانه‌ای',
            'family' => 'flutter',
            'ease_band' => [1.0, 12.0],
            'cap_fullness' => $gather,
            'gather_cap' => $gather > 0.02,
            'hem_ratio' => 1 + $flare,
            'hem_drop' => (float) $this->param($params, 'hem_curve', 4),
            'side_bulge' => 1.5,
            'side_bulge_at' => 0.6,
            'notes' => [
                'لبه دم آستین گرد بریده می‌شود تا آستین موج بخورد؛ برای پارچه سبک مثل حریر و ژرسه.',
            ],
        ];
    }
}
