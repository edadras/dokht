<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\DraftsSkirt;

/** دامن A: از باسن به پایین باز می‌شود؛ ساده‌ترین دامن برای شروع. */
class ALineSkirtGenerator extends BaseGenerator
{
    use DraftsSkirt;

    public function label(): string
    {
        return 'دامن A';
    }

    public function paramsSchema(): array
    {
        return [
            'length' => [
                'label' => 'بلندی دامن', 'min' => 25, 'max' => 120, 'step' => 1, 'default' => 60, 'unit' => 'سانتی‌متر',
            ],
            'flare' => [
                'label' => 'گشادی دم دامن', 'min' => 0, 'max' => 45, 'step' => 1, 'default' => 9, 'unit' => 'سانتی‌متر',
                'hint' => 'اندازه بازشدن هر پنل در پایین.',
            ],
            'dart_share' => [
                'label' => 'سهم ساسون از کاهش کمر', 'min' => 0, 'max' => 0.9, 'step' => 0.05, 'default' => 0.5,
            ],
            'waistband' => [
                'label' => 'کمربند داشته باشد', 'type' => 'toggle', 'default' => true,
            ],
            'zip' => [
                'label' => 'زیپ', 'type' => 'select', 'default' => 'side',
                'options' => [
                    'side' => 'زیپ مخفی روی درز پهلوی چپ',
                    'back' => 'زیپ مرکز پشت',
                    'none' => 'بدون زیپ (کمر کشی یا رویهم)',
                ],
                'hint' => 'دامن کمرگرفته بدون زیپ از باسن رد نمی‌شود.',
            ],
            'waistband_height' => [
                'label' => 'بلندی کمربند', 'min' => 2, 'max' => 10, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $length = (float) $this->param($params, 'length', 60);
        $flare = (float) $this->param($params, 'flare', 9);

        $pieces = [
            $this->skirtPanel($measurements, $ease, $params, [
                'side' => 'front', 'length' => $length, 'hem_delta' => $flare, 'dart_count' => 1,
            ]),
            $this->skirtPanel($measurements, $ease, $params, [
                'side' => 'back', 'length' => $length, 'hem_delta' => $flare, 'dart_count' => 1,
            ]),
        ];

        if ($this->flag($params, 'waistband', true)) {
            $pieces[] = $this->waistbandPiece($measurements, $ease, $params);
        }

        return $this->finishSkirt($pieces, $params);
    }
}
