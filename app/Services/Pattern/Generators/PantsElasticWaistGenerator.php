<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار کمر کشی.
 *
 * بالای خط باسن هیچ کاهشی داده نمی‌شود: پنل از خط باسن مستقیم بالا می‌رود، پس
 * شلوار بدون بست از روی باسن رد می‌شود و کش آن را روی کمر جمع می‌کند. اختلاف
 * پهنای باسن و دور کمر همان «چین کمر» است و با FullnessRecorder ثبت می‌شود.
 */
class PantsElasticWaistGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_elastic_waist';
    }

    public function label(): string
    {
        return 'شلوار کمر کشی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            $this->legParams(12, 14),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 6, 'max' => 26, 'step' => 0.5,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'band_stretch' => [
                    'label' => 'کشش نوار کمر', 'min' => 0.6, 'max' => 0.95, 'step' => 0.01,
                    'default' => 0.85,
                    'hint' => 'نوار کش این کسر از دور کمر بریده و روی آن کشیده می‌شود.',
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
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 12),
            'hem_vs_knee' => -6.0,
            'front_waist' => 'gather',
            'waist_balance' => 0.0,
            'band' => 'elastic',
            'band_stretch' => (float) $this->param($params, 'band_stretch', 0.85),
        ];
    }
}
