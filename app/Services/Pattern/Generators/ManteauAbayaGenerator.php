<?php

namespace App\Services\Pattern\Generators;

/**
 * مانتو عبایی.
 *
 * تنه و آستین یک‌سره بریده می‌شوند: زیر بغل پایین می‌آید و آستین از خود تنه
 * بیرون می‌زند، پس حلقه آستین دوخته نمی‌شود. برشی که با کم‌ترین درز، بیشترین
 * راحتی را می‌دهد و روی پارچه‌های نرم زیبا می‌افتد.
 */
class ManteauAbayaGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'manteau_abaya';
    }

    public function label(): string
    {
        return 'مانتو عبایی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 4, 'neck_width_extra' => 2]),
            $this->garmentLengthParam(70, 30, 125),
            $this->openingParam('open', 0),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'underarm_drop' => [
                    'label' => 'پایین آمدن زیر بغل', 'min' => 2, 'max' => 20, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'sleeve_length' => [
                    'label' => 'بلندی آستین از زیر بغل', 'min' => 10, 'max' => 60, 'step' => 1,
                    'default' => 32, 'unit' => 'سانتی‌متر',
                ],
                'cuff_width' => [
                    'label' => 'پهنای دم آستین', 'min' => 10, 'max' => 32, 'step' => 0.5,
                    'default' => 18, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(false, 15, 17),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 4);
        $length = (float) $this->param($params, 'length', 70);

        $shared = [
            'grow' => $grow,
            'length' => $length,
            'underarm_drop' => (float) $this->param($params, 'underarm_drop', 8),
            'sleeve_length' => (float) $this->param($params, 'sleeve_length', 32),
            'cuff_width' => (float) $this->param($params, 'cuff_width', 18),
            'hem_flare' => (float) $this->param($params, 'hem_flare', 8),
        ];

        $front = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'front',
            'on_fold' => false,
            'cut' => 2,
            'code' => 'abaya-manteau-front',
            'name' => 'تنه و آستین جلو',
        ]));

        $back = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'abaya-manteau-back',
            'name' => 'تنه و آستین پشت',
        ]));

        $pieces = array_merge(
            [$front, $back],
            $this->facingSet($g, 0.0, $g['front_waist_y'] + $length, ['prefix' => 'abaya-manteau-', 'width' => 8]),
            $this->pocketSet($params, ['prefix' => 'abaya-manteau-']),
        );

        return $this->finishBlock($pieces, $g, $grow);
    }
}
