<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوارک کوتاه.
 *
 * چند سانت پایین‌تر از خط فاق تمام می‌شود، پس دم پا تقریباً روی خود دور ران
 * می‌افتد و دیگر «زانو» ندارد؛ پنل با چهار تراز (کمر، باسن، فاق، دم) بریده
 * می‌شود. لبه دم به پهلو شیب برگردان می‌گیرد تا نشستن راحت باشد.
 */
class ShortsShortGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'shorts_short';
    }

    public function label(): string
    {
        return 'شلوارک کوتاه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            [
                'leg_length' => [
                    'label' => 'قد پا از خط فاق', 'min' => 4, 'max' => 20, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دور دم پا', 'min' => 0, 'max' => 24, 'step' => 1,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 3, 'max' => 20, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
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
            'leg_length' => max(4.0, (float) $this->param($params, 'leg_length', 8)),
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 8),
            'front_waist' => 'none',
            'waist_balance' => -0.5,
            'side_share' => 0.45,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }
}
