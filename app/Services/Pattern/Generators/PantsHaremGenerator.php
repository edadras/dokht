<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار هارمی.
 *
 * فاق عمداً پایین‌تر از فاق بدن می‌افتد تا پارچه بین دو پا آویزان بماند. برای
 * اینکه قد شلوار عوض نشود، هرچقدر فاق پایین می‌رود همان‌قدر از قد پا کم می‌شود.
 * کمر چین‌دار و دم پا جمع‌شده است و هر دو چین ثبت می‌شوند.
 */
class PantsHaremGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_harem';
    }

    public function label(): string
    {
        return 'شلوار هارمی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            $this->legParams(24, 22),
            [
                'crotch_drop' => [
                    'label' => 'پایین افتادن فاق', 'min' => 4, 'max' => 30, 'step' => 1,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                    'hint' => 'همین‌قدر از قد پا کم می‌شود تا دم شلوار سر جایش بماند.',
                ],
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 10, 'max' => 40, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
                'cuff_ease' => [
                    'label' => 'آزادی مچ دم پا', 'min' => 2, 'max' => 16, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'band_stretch' => [
                    'label' => 'کشش نوار کمر', 'min' => 0.6, 'max' => 0.95, 'step' => 0.01, 'default' => 0.85,
                ],
                'waistband_height' => [
                    'label' => 'بلندی نوار کمر', 'min' => 2, 'max' => 10, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'crotch_drop' => (float) $this->param($params, 'crotch_drop', 14),
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 24),
            'fork_scale' => 1.15,
            'hem_vs_knee' => -6.0,
            'front_waist' => 'gather',
            'waist_balance' => 0.0,
            'band' => 'elastic',
            'band_stretch' => (float) $this->param($params, 'band_stretch', 0.85),
            'hem_gather' => $this->m($measurements, 'ankle', 23.5) + (float) $this->param($params, 'cuff_ease', 8),
            'hem_band_height' => 4.0,
            'length_offset' => -4.0,
        ];
    }
}
