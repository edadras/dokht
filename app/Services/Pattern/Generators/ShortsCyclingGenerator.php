<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوارک دوچرخه‌سواری.
 *
 * روی پارچه کشی و با آزادی منفی درفت می‌شود تا روی ران بایستد و بالا نرود. بدون
 * ساسون است و کمرش نوار کش دارد؛ فاق کمی گودتر گرفته می‌شود چون خم شدن روی زین
 * پشت را می‌کشد.
 */
class ShortsCyclingGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'shorts_cycling';
    }

    public function label(): string
    {
        return 'شلوارک دوچرخه‌سواری';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high', 1),
            [
                'leg_length' => [
                    'label' => 'قد پا از خط فاق', 'min' => 8, 'max' => 35, 'step' => 1,
                    'default' => 20, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دور دم پا', 'min' => -8, 'max' => 8, 'step' => 0.5,
                    'default' => -2, 'unit' => 'سانتی‌متر',
                ],
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => -10, 'max' => 6, 'step' => 0.5,
                    'default' => -3, 'unit' => 'سانتی‌متر',
                ],
                'stretch' => [
                    'label' => 'ضریب پارچه کشی', 'min' => 0.7, 'max' => 1, 'step' => 0.01, 'default' => 0.92,
                ],
                'waistband_height' => [
                    'label' => 'بلندی کش کمر', 'min' => 2, 'max' => 10, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'leg_length' => max(6.0, (float) $this->param($params, 'leg_length', 20)),
            'stretch' => (float) $this->param($params, 'stretch', 0.92),
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', -3),
            'waist_ease' => -1.0,
            'hip_ease' => -3.0,
            'fork_scale' => 0.95,
            'front_waist' => 'none',
            'back_waist' => 'none',
            'waist_balance' => -0.5,
            'side_share' => 0.6,
            'lean_share' => 0.25,
            'lean_hard_max' => 5.0,
            'back_tilt_max' => 6.0,
            'band' => 'elastic',
            'band_stretch' => 0.84,
        ];
    }
}
