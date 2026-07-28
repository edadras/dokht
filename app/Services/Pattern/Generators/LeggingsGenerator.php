<?php

namespace App\Services\Pattern\Generators;

/**
 * ساپورت / لگینگ.
 *
 * روی پارچه کشی درفت می‌شود، پس آزادی همه ناحیه‌ها منفی است و الگو از خود بدن
 * کوچک‌تر درمی‌آید؛ کشسانی پارچه فاصله را پر می‌کند. هیچ ساسونی ندارد: کاهش کمر
 * تا باسن به درز پهلو و خوابیدن خط مرکز می‌رود و کش کمر بقیه کار را می‌کند.
 */
class LeggingsGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'leggings';
    }

    public function label(): string
    {
        return 'ساپورت (لگینگ)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            [
                'length_extra' => [
                    'label' => 'تغییر قد پا', 'min' => -50, 'max' => 8, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'knee_ease' => [
                    'label' => 'آزادی دور زانو', 'min' => -8, 'max' => 6, 'step' => 0.5,
                    'default' => -1, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دور مچ پا', 'min' => -8, 'max' => 6, 'step' => 0.5,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => -10, 'max' => 6, 'step' => 0.5,
                    'default' => -2, 'unit' => 'سانتی‌متر',
                ],
                'stretch' => [
                    'label' => 'ضریب پارچه کشی', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                    'default' => 0.92,
                    'hint' => 'الگو این کسر از اندازه بدن بریده می‌شود.',
                ],
                'waistband_height' => [
                    'label' => 'بلندی کش کمر', 'min' => 2, 'max' => 12, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'stretch' => (float) $this->param($params, 'stretch', 0.92),
            'waist_ease' => -2.0,
            'hip_ease' => -4.0,
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', -2),
            'hem_vs_knee' => -8.0,
            'fork_scale' => 0.92,
            'front_waist' => 'none',
            'back_waist' => 'none',
            'waist_balance' => -0.5,
            'side_share' => 0.6,
            'lean_share' => 0.25,
            'lean_hard_max' => 5.0,
            'back_tilt_max' => 6.0,
            'band' => 'elastic',
            'band_stretch' => 0.82,
        ];
    }
}
