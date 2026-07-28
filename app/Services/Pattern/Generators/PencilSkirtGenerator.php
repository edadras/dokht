<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\DraftsSkirt;

/** دامن راسته (مدادی): کمر گرفته با دو ساسون پشت و چاک پشت برای راحتی راه رفتن. */
class PencilSkirtGenerator extends BaseGenerator
{
    use DraftsSkirt;

    public function label(): string
    {
        return 'دامن راسته';
    }

    public function paramsSchema(): array
    {
        return [
            'length' => [
                'label' => 'بلندی دامن', 'min' => 30, 'max' => 120, 'step' => 1, 'default' => 58, 'unit' => 'سانتی‌متر',
            ],
            'taper' => [
                'label' => 'تنگی دم دامن', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'dart_share' => [
                'label' => 'سهم ساسون از کاهش کمر', 'min' => 0, 'max' => 0.9, 'step' => 0.05, 'default' => 0.6,
            ],
            'vent_length' => [
                'label' => 'بلندی چاک پشت', 'min' => 0, 'max' => 35, 'step' => 1, 'default' => 18, 'unit' => 'سانتی‌متر',
            ],
            'back_darts' => [
                'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1, 'default' => 2,
            ],
            'waistband' => [
                'label' => 'کمربند داشته باشد', 'type' => 'toggle', 'default' => true,
            ],
            'waistband_height' => [
                'label' => 'بلندی کمربند', 'min' => 2, 'max' => 10, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $length = (float) $this->param($params, 'length', 58);
        $taper = (float) $this->param($params, 'taper', 2);
        $vent = min((float) $this->param($params, 'vent_length', 18), $length - 10);

        $pieces = [
            $this->skirtPanel($measurements, $ease, $params, [
                'side' => 'front', 'length' => $length, 'hem_delta' => -$taper, 'dart_count' => 1,
            ]),
            $this->skirtPanel($measurements, $ease, $params, [
                'side' => 'back',
                'length' => $length,
                'hem_delta' => -$taper,
                'dart_count' => (int) $this->param($params, 'back_darts', 2),
                'vent' => max(0, $vent),
            ]),
        ];

        if ($this->flag($params, 'waistband', true)) {
            $pieces[] = $this->waistbandPiece($measurements, $ease, $params);
        }

        return $this->finish($pieces);
    }
}
