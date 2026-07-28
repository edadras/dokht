<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار باریک‌شونده.
 *
 * از باسن تا زانو راحت است و از زانو تا دم پا کم‌کم جمع می‌شود، پس دور دم پا از
 * دور زانو کمتر درمی‌آید. یک ساسون جلو و دو ساسون پشت، همان چیزی که این فرم را
 * روی کمر می‌نشاند.
 */
class PantsTaperedGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_tapered';
    }

    public function label(): string
    {
        return 'شلوار باریک‌شونده';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('mid'),
            $this->legParams(9, 12),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 2, 'max' => 20, 'step' => 0.5,
                    'default' => 9, 'unit' => 'سانتی‌متر',
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
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 9),
            'hem_vs_knee' => -4.0,
            'front_waist' => 'dart',
            'waist_balance' => 0.5,
            'side_share' => 0.32,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }
}
