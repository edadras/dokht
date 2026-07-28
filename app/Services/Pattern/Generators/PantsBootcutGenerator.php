<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار دم‌پا گشاد (بوت‌کات).
 *
 * زانو کمی تنگ گرفته می‌شود و از زانو به پایین به‌اندازه‌ای باز می‌شود که روی
 * چکمه بیفتد. باز شدن با یک منحنی ملایم انجام می‌شود نه با خط شکسته، وگرنه درز
 * پهلو سر زانو زاویه می‌گیرد.
 */
class PantsBootcutGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_bootcut';
    }

    public function label(): string
    {
        return 'شلوار دم‌پا گشاد (بوت‌کات)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('mid'),
            $this->legParams(4, 20),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 0, 'max' => 16, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'flare_curve' => [
                    'label' => 'نرمی منحنی باز شدن', 'min' => 0, 'max' => 4, 'step' => 0.25,
                    'default' => 1, 'unit' => 'سانتی‌متر',
                    'hint' => 'هرچه بیشتر، باز شدن دم پا نرم‌تر و دیرتر شروع می‌شود.',
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
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 6),
            'hem_flare' => (float) $this->param($params, 'flare_curve', 1),
            'hem_vs_knee' => 5.0,
            'front_waist' => 'none',
            'waist_balance' => -0.5,
            'side_share' => 0.45,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }
}
