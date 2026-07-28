<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار پاکتی کمر بلند.
 *
 * پنل به اندازه «بلندی پاکتی» بالاتر از خط کمر ادامه پیدا می‌کند و کش یا بند روی
 * خط کمر بسته می‌شود؛ همان نوار بالای کش است که مثل دهانه پاکت می‌ایستد. چون
 * پارچه بالای باسن باریک نشده، شلوار بدون بست از روی باسن رد می‌شود.
 */
class PantsPaperbagGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_paperbag';
    }

    public function label(): string
    {
        return 'شلوار پاکتی کمر بلند';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            $this->legParams(16, 20),
            [
                'paperbag' => [
                    'label' => 'بلندی نوار پاکتی', 'min' => 2, 'max' => 12, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                    'hint' => 'پنل همین‌قدر بالاتر از خط کمر بریده می‌شود.',
                ],
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 6, 'max' => 28, 'step' => 0.5,
                    'default' => 14, 'unit' => 'سانتی‌متر',
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
            'paperbag' => max(1.0, (float) $this->param($params, 'paperbag', 6)),
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 14),
            'hem_vs_knee' => -5.0,
            'front_waist' => 'gather',
            'waist_balance' => 0.0,
            'band' => 'elastic',
            'band_stretch' => (float) $this->param($params, 'band_stretch', 0.85),
        ];
    }
}
