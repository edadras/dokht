<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوارک برمودا.
 *
 * تا یک وجب بالای زانو می‌آید و پای راسته دارد. دور دم پا از روی دور پا در همان
 * تراز حساب می‌شود — بین دور ران و دور مچ پا درون‌یابی — نه از روی مچ پا، وگرنه
 * شلوارک روی ران گیر می‌کند.
 */
class ShortsBermudaGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'shorts_bermuda';
    }

    public function label(): string
    {
        return 'شلوارک برمودا';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('mid'),
            [
                'leg_length' => [
                    'label' => 'قد پا از خط فاق', 'min' => 12, 'max' => 45, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دور دم پا', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 4, 'max' => 24, 'step' => 0.5,
                    'default' => 11, 'unit' => 'سانتی‌متر',
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1, 'default' => 2,
                ],
            ],
            $this->bandParams(4),
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'leg_length' => max(6.0, (float) $this->param($params, 'leg_length', 24)),
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 11),
            'front_waist' => 'dart',
            'waist_balance' => 0.5,
            'side_share' => 0.32,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }
}
