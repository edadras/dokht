<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار جاگر (دم‌پا کش).
 *
 * کمر کشی، پای باریک‌شونده و دم پا که در یک مچ کشی جمع می‌شود. هم چین کمر و هم
 * چین دم پا ثبت می‌شوند تا برش‌کار بداند پارچه از دور تمام‌شده بیشتر است.
 */
class PantsJoggerGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_jogger';
    }

    public function label(): string
    {
        return 'شلوار جاگر (دم‌پا کش)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('mid'),
            $this->legParams(12, 14),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 6, 'max' => 26, 'step' => 0.5,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'cuff_ease' => [
                    'label' => 'آزادی مچ دم پا', 'min' => 1, 'max' => 14, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                    'hint' => 'دور تمام‌شده مچ = دور مچ پا + همین.',
                ],
                'cuff_height' => [
                    'label' => 'بلندی مچ', 'min' => 2, 'max' => 10, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'band_stretch' => [
                    'label' => 'کشش نوار کمر', 'min' => 0.6, 'max' => 0.95, 'step' => 0.01, 'default' => 0.85,
                ],
                'waistband_height' => [
                    'label' => 'بلندی نوار کمر', 'min' => 2, 'max' => 10, 'step' => 0.5,
                    'default' => 4.5, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        $cuff = $this->m($measurements, 'ankle', 23.5) + (float) $this->param($params, 'cuff_ease', 6);

        return [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 12),
            'hem_vs_knee' => -8.0,
            'front_waist' => 'gather',
            'waist_balance' => 0.0,
            'band' => 'elastic',
            'band_stretch' => (float) $this->param($params, 'band_stretch', 0.85),
            'hem_gather' => $cuff,
            'hem_band_height' => (float) $this->param($params, 'cuff_height', 5),
            'length_offset' => -(float) $this->param($params, 'cuff_height', 5),
        ];
    }
}
