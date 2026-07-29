<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن راسته: بلوک پایه دامن؛ از باسن به پایین بدون کم و زیاد شدن می‌آید.
 *
 * همه دامن‌های دیگر همین درفت را با یک تغییر شکل ادامه می‌دهند، پس این مدل هم
 * لباس کامل است و هم نقطه شروع تمرین.
 */
class SkirtStraightGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_straight';
    }

    public function label(): string
    {
        return 'دامن راسته ساده';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(60),
            [
                'hem_change' => [
                    'label' => 'تغییر دم دامن', 'min' => -6, 'max' => 12, 'step' => 0.5, 'default' => 0,
                    'unit' => 'سانتی‌متر',
                    'hint' => 'مثبت یعنی هر پهلو این‌قدر باز می‌شود، منفی یعنی جمع می‌شود.',
                ],
                'vent_length' => [
                    'label' => 'بلندی چاک پشت', 'min' => 0, 'max' => 40, 'step' => 1, 'default' => 16,
                    'unit' => 'سانتی‌متر',
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1, 'default' => 2,
                ],
            ],
            $this->waistParams(0.5),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 60);
        $hem = (float) $this->param($params, 'hem_change', 0);

        $pieces = [
            $this->blockPanel($mx, [
                'side' => 'front', 'length' => $length, 'hem_delta' => $hem, 'dart_count' => 1,
            ]),
            $this->blockPanel($mx, [
                'side' => 'back',
                'length' => $length,
                'hem_delta' => $hem,
                'dart_count' => (int) $this->param($params, 'back_darts', 2),
                'vent' => (float) $this->param($params, 'vent_length', 16),
            ]),
        ];

        return $this->finishSkirt(array_merge($pieces, $this->bandPieces($mx, $params)), $params);
    }
}
