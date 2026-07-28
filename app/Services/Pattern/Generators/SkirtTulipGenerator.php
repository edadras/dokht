<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن لاله‌ای.
 *
 * جلوی دامن از دو گلبرگ ساخته می‌شود که روی هم می‌افتند و دم دامن را جمع می‌کنند؛
 * حجم بالای دامن از پیلی کمر می‌آید و دم آن از باسن تنگ‌تر است. هم‌پوشانی گلبرگ‌ها
 * جزو دور کمر حساب نمی‌شود و در meta.fullness جدا ثبت شده است.
 */
class SkirtTulipGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_tulip';
    }

    public function label(): string
    {
        return 'دامن لاله‌ای';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(52, 30, 90),
            [
                'overlap' => [
                    'label' => 'هم‌پوشانی گلبرگ‌ها در کمر', 'min' => 4, 'max' => 25, 'step' => 1, 'default' => 12,
                    'unit' => 'سانتی‌متر',
                ],
                'petal_curve' => [
                    'label' => 'برگشت لبه گلبرگ در دم', 'min' => 0, 'max' => 20, 'step' => 1, 'default' => 7,
                    'unit' => 'سانتی‌متر',
                ],
                'waist_pleat' => [
                    'label' => 'عمق کل پیلی کمر', 'min' => 0, 'max' => 10, 'step' => 0.5, 'default' => 4,
                    'unit' => 'سانتی‌متر',
                ],
                'hem_taper' => [
                    'label' => 'تنگی دم دامن', 'min' => 0, 'max' => 14, 'step' => 0.5, 'default' => 5,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 52);
        $taper = -abs((float) $this->param($params, 'hem_taper', 5));

        $pieces = [
            $this->blockPanel($mx, [
                'side' => 'front',
                'length' => $length,
                'hem_delta' => $taper,
                'dart_count' => 1,
                'overlap' => (float) $this->param($params, 'overlap', 12),
                'overlap_hem' => (float) $this->param($params, 'petal_curve', 7),
                'waist_pleat' => (float) $this->param($params, 'waist_pleat', 4),
                'code' => 'tulip-petal',
                'name' => 'گلبرگ جلو',
            ]),
            $this->blockPanel($mx, [
                'side' => 'back',
                'length' => $length,
                'hem_delta' => $taper,
                'dart_count' => 2,
                'vent' => min(14.0, $length * 0.28),
                'name' => 'دامن لاله‌ای پشت',
            ]),
        ];

        return $this->finish(array_merge($pieces, $this->bandPieces($mx, $params)));
    }
}
