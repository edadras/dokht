<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\DraftsPants;

/** شلوار گشاد: همان درفت شلوار با زانو و دم پای بازتر و پیلی جلو. */
class WideLegPantsGenerator extends BaseGenerator
{
    use DraftsPants;

    public function label(): string
    {
        return 'شلوار گشاد';
    }

    public function paramsSchema(): array
    {
        return [
            'rise_extra' => [
                'label' => 'تغییر گودی فاق', 'min' => -5, 'max' => 12, 'step' => 0.5, 'default' => 1, 'unit' => 'سانتی‌متر',
            ],
            'length_extra' => [
                'label' => 'تغییر قد پا', 'min' => -55, 'max' => 10, 'step' => 1, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'knee_ease' => [
                'label' => 'آزادی دور زانو', 'min' => 6, 'max' => 45, 'step' => 1, 'default' => 20, 'unit' => 'سانتی‌متر',
            ],
            'hem_ease' => [
                'label' => 'آزادی دور دم پا', 'min' => 8, 'max' => 70, 'step' => 1, 'default' => 34, 'unit' => 'سانتی‌متر',
            ],
            'front_pleat' => [
                'label' => 'پیلی جلو', 'min' => 0, 'max' => 10, 'step' => 0.5, 'default' => 3, 'unit' => 'سانتی‌متر',
            ],
            'back_darts' => [
                'label' => 'تعداد ساسون پشت', 'min' => 0, 'max' => 2, 'step' => 1, 'default' => 1,
            ],
            'waistband' => [
                'label' => 'کمربند داشته باشد', 'type' => 'toggle', 'default' => true,
            ],
            'waistband_height' => [
                'label' => 'بلندی کمربند', 'min' => 2, 'max' => 12, 'step' => 0.5, 'default' => 5, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        return $this->finish($this->pantsPieces($measurements, $ease, $params));
    }
}
