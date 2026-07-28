<?php

namespace App\Services\Pattern\Generators;

/**
 * کولوت.
 *
 * کولوت شلوار است نه دامن: منحنی فاق، پنجه فاق و درز داخل پا سر جایشان هستند و
 * فقط پا آن‌قدر کوتاه و گشاد می‌شود که ایستاده مثل دامن دیده شود. برای همین دور
 * دم پای هر پا از دور زانو خیلی بزرگ‌تر گرفته می‌شود و پیلی جلو به آن حجم می‌دهد.
 */
class PantsCulottesGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_culottes';
    }

    public function label(): string
    {
        return 'کولوت';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            [
                'length_ratio' => [
                    'label' => 'قد پا نسبت به قد داخل پا', 'min' => 0.25, 'max' => 0.8, 'step' => 0.05,
                    'default' => 0.55,
                    'hint' => 'حدود نیم یعنی کمی پایین‌تر از زانو.',
                ],
                'length_extra' => [
                    'label' => 'تغییر قد پا', 'min' => -20, 'max' => 20, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'knee_ease' => [
                    'label' => 'آزادی دور زانو', 'min' => 6, 'max' => 50, 'step' => 1,
                    'default' => 26, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دور دم پا', 'min' => 10, 'max' => 70, 'step' => 1,
                    'default' => 34, 'unit' => 'سانتی‌متر',
                ],
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 6, 'max' => 30, 'step' => 0.5,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
                'front_pleats' => [
                    'label' => 'تعداد پیلی جلو', 'min' => 1, 'max' => 3, 'step' => 1, 'default' => 2,
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1, 'default' => 2,
                ],
            ],
            $this->bandParams(5),
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        $ratio = min(0.85, max(0.2, (float) $this->param($params, 'length_ratio', 0.55)));

        return [
            'leg_length' => max(12.0, $this->m($measurements, 'inseam', 75) * $ratio),
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 16),
            'hem_vs_knee' => 4.0,
            'front_waist' => 'pleat',
            'pleat_count' => (int) $this->param($params, 'front_pleats', 2),
            'waist_balance' => 0.5,
            'side_share' => 0.3,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }
}
