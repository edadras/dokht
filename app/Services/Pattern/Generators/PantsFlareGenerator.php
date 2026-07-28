<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار فون.
 *
 * همان بوت‌کات است با دست بازتر: زانو تنگ‌تر و دم پا خیلی گشادتر. چون اختلاف
 * زانو و دم پا زیاد است، منحنی باز شدن پررنگ‌تر گرفته می‌شود تا درز پهلو و درز
 * داخل پا هر دو نرم بمانند.
 */
class PantsFlareGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_flare';
    }

    public function label(): string
    {
        return 'شلوار فون';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            $this->legParams(2, 34),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 0, 'max' => 16, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'flare_curve' => [
                    'label' => 'نرمی منحنی باز شدن', 'min' => 0, 'max' => 6, 'step' => 0.25,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
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
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 5),
            'hem_flare' => (float) $this->param($params, 'flare_curve', 2.5),
            'hem_vs_knee' => 16.0,
            'front_waist' => 'none',
            'waist_balance' => -0.5,
            'side_share' => 0.45,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }
}
