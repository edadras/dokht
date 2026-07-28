<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار تنگ (اسکینی).
 *
 * تنگی اسکینی از دم پا شروع می‌شود نه از باسن: خط باسن دست نمی‌خورد و همان
 * (باسن + آزادی) ÷ ۴ می‌ماند، ولی دور ران، زانو و دم پا با آزادی کم — و روی
 * پارچه کشی حتی با آزادی منفی — بریده می‌شوند. جلو بی‌ساسون است و کاهش کمر جلو
 * به درز پهلو و خوابیدن فاق می‌رود.
 */
class PantsSkinnyGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_skinny';
    }

    public function label(): string
    {
        return 'شلوار تنگ (اسکینی)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('mid'),
            $this->legParams(4, 10),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => -6, 'max' => 12, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                    'hint' => 'برای پارچه کشی می‌تواند منفی باشد.',
                ],
                'stretch' => [
                    'label' => 'ضریب پارچه کشی', 'min' => 0.8, 'max' => 1, 'step' => 0.01,
                    'default' => 0.96,
                    'hint' => 'کمتر از یک یعنی الگو کوچک‌تر از بدن بریده و روی تن کشیده می‌شود.',
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1, 'default' => 2,
                ],
            ],
            $this->bandParams(3.5),
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'stretch' => (float) $this->param($params, 'stretch', 0.96),
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 3),
            'hem_vs_knee' => -3.0,
            'front_waist' => 'none',
            'waist_balance' => -1.0,
            'side_share' => 0.55,
            'lean_share' => 0.22,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }
}
