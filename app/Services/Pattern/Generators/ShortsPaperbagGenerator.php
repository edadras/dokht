<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوارک پاکتی.
 *
 * همان کمر پاکتی شلوار روی یک پای کوتاه و گشاد: پنل به اندازه بلندی پاکتی بالاتر
 * از خط کمر ادامه دارد، کش یا بند روی خط کمر بسته می‌شود و بالای آن مثل دهانه
 * پاکت می‌ایستد.
 */
class ShortsPaperbagGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'shorts_paperbag';
    }

    public function label(): string
    {
        return 'شلوارک پاکتی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            [
                'leg_length' => [
                    'label' => 'قد پا از خط فاق', 'min' => 6, 'max' => 30, 'step' => 1,
                    'default' => 13, 'unit' => 'سانتی‌متر',
                ],
                'paperbag' => [
                    'label' => 'بلندی نوار پاکتی', 'min' => 2, 'max' => 12, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دور دم پا', 'min' => 2, 'max' => 30, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 6, 'max' => 26, 'step' => 0.5,
                    'default' => 13, 'unit' => 'سانتی‌متر',
                ],
                'band_stretch' => [
                    'label' => 'کشش کش کمر', 'min' => 0.6, 'max' => 0.95, 'step' => 0.01, 'default' => 0.85,
                ],
                'waistband_height' => [
                    'label' => 'بلندی کش کمر', 'min' => 1.5, 'max' => 6, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'leg_length' => max(5.0, (float) $this->param($params, 'leg_length', 13)),
            'paperbag' => max(1.0, (float) $this->param($params, 'paperbag', 5)),
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 13),
            'front_waist' => 'gather',
            'waist_balance' => 0.0,
            'band' => 'elastic',
            'band_stretch' => (float) $this->param($params, 'band_stretch', 0.85),
        ];
    }
}
