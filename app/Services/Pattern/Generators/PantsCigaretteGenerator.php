<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار سیگاری.
 *
 * پای باریک و صاف که چند سانت بالای قوزک پا تمام می‌شود: از زانو به پایین تقریباً
 * راسته است و فقط کمی جمع می‌شود. جلو بی‌ساسون و صاف است.
 */
class PantsCigaretteGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_cigarette';
    }

    public function label(): string
    {
        return 'شلوار سیگاری';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            $this->legParams(6, 14),
            [
                'crop' => [
                    'label' => 'کوتاهی از قوزک پا', 'min' => 0, 'max' => 20, 'step' => 1,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                    'hint' => 'دم شلوار این‌قدر بالاتر از ته قد داخل پا تمام می‌شود.',
                ],
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 0, 'max' => 14, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
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
        $crop = max(0.0, (float) $this->param($params, 'crop', 7));

        return [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 6),
            'length_offset' => -$crop,
            'hem_vs_knee' => -4.0,
            'front_waist' => 'none',
            'waist_balance' => -1.0,
            'side_share' => 0.5,
            'lean_share' => 0.2,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }
}
