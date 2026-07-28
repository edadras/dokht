<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین شیپوری.
 *
 * تا نزدیک آرنج یا ساعد به دست می‌چسبد و از یک نقطه مشخص یک‌باره باز می‌شود؛
 * درست مثل شیپور. جای شروع گشادی و اندازه دهانه هر دو پارامتر است.
 */
class SleeveTrumpetGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_trumpet';
    }

    public function label(): string
    {
        return 'آستین شیپوری';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.5, 100, 3) + [
            'flare_start' => [
                'label' => 'شروع گشادی', 'min' => 30, 'max' => 85, 'step' => 5, 'default' => 65,
                'unit' => 'درصد بلندی آستین',
            ],
            'flare' => [
                'label' => 'گشادی دهانه', 'min' => 30, 'max' => 200, 'step' => 5, 'default' => 80,
                'unit' => 'درصد',
            ],
            'hem_curve' => [
                'label' => 'گردی لبه دم آستین', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $flare = max(0.2, ((float) $this->param($params, 'flare', 110)) / 100);
        $start = max(0.3, min(0.85, ((float) $this->param($params, 'flare_start', 65)) / 100));
        $forearm = $this->m($m, 'elbow', 25.8) * 0.82 + (float) $this->param($params, 'hem_ease', 3);

        return [
            'code' => 'sleeve-trumpet',
            'name' => 'آستین شیپوری',
            'family' => 'flared',
            'ease_band' => [1.5, 4.0],
            'bicep_ease' => 3.5,
            'flare_start' => $start,
            'flare_start_width' => $forearm,
            'hem_ratio' => 1 + $flare,
            'hem_drop' => (float) $this->param($params, 'hem_curve', 3),
            'side_bulge' => 0.3,
            'notes' => [
                'تا '.round($start * 100).' درصد بلندی آستین تنگ می‌ماند ('.round($forearm, 1)
                    .' سانتی‌متر) و از آنجا یک‌باره باز می‌شود.',
            ],
        ];
    }
}
