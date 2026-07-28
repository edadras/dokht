<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار پیلی‌دار جلو.
 *
 * همه کاهش کمر جلو به‌جای ساسون به یک یا دو پیلی می‌رود که از خط اتوی پا شروع
 * می‌شوند و تا نزدیک خط باسن اتو می‌شوند. چون پیلی همان‌قدر پارچه می‌خورد که ساسون
 * می‌خورد، دور کمر تمام‌شده عوض نمی‌شود؛ فقط شکم جلو راحت‌تر می‌شود.
 */
class PantsPleatedGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_pleated';
    }

    public function label(): string
    {
        return 'شلوار پیلی‌دار جلو';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            $this->legParams(14, 24),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 6, 'max' => 26, 'step' => 0.5,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'front_pleats' => [
                    'label' => 'تعداد پیلی جلو', 'min' => 1, 'max' => 3, 'step' => 1, 'default' => 2,
                ],
                'pleat_style' => [
                    'label' => 'گونه پیلی', 'type' => 'select', 'default' => 'knife',
                    'options' => ['knife' => 'تیغه‌ای', 'box' => 'جعبه‌ای', 'inverted' => 'جعبه‌ای برعکس'],
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1, 'default' => 2,
                ],
            ],
            $this->bandParams(4.5),
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 14),
            'hem_vs_knee' => -5.0,
            'front_waist' => 'pleat',
            'pleat_count' => (int) $this->param($params, 'front_pleats', 2),
            'pleat_style' => (string) $this->param($params, 'pleat_style', 'knife'),
            'waist_balance' => 0.5,
            'side_share' => 0.26,
            'lean_share' => 0.12,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }
}
