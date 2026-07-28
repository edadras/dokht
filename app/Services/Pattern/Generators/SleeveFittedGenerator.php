<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین تنگ (جذب) با ساسون آرنج.
 *
 * آستین قالب بازو: آزادی بازو کم، دم آستین نزدیک دور مچ. چون درز پشت آستین باید
 * از جلو بلندتر باشد تا آرنج راحت خم شود، همان اضافه با ساسون آرنج روی درز پشت
 * جمع می‌شود و دو درز دقیقاً هم‌اندازه می‌شوند.
 */
class SleeveFittedGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_fitted';
    }

    public function label(): string
    {
        return 'آستین تنگ با ساسون آرنج';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(2.0, 100, 3) + [
            'bicep_ease' => [
                'label' => 'آزادی بازو', 'min' => 1, 'max' => 8, 'step' => 0.5, 'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'elbow_dart' => [
                'label' => 'ساسون آرنج', 'min' => 0, 'max' => 5, 'step' => 0.5, 'default' => 2.5, 'unit' => 'سانتی‌متر',
                'hint' => 'صفر یعنی به جای ساسون، آرنج فقط با منحنی درز گرفته شود.',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $dart = (float) $this->param($params, 'elbow_dart', 2.5);
        $long = (float) $this->param($params, 'length_percent', 100) >= 60;

        return [
            'code' => 'sleeve-fitted',
            'name' => 'آستین تنگ',
            'family' => 'set_in',
            'ease_band' => [2.0, 4.0],
            'bicep_ease' => (float) $this->param($params, 'bicep_ease', 2.5),
            'hem_ease' => (float) $this->param($params, 'hem_ease', 3),
            'side_bulge' => 0.35,
            'elbow_dart' => $long ? $dart : 0.0,
            'back_seam_extra' => $long ? $dart : 0.0,
            'back_seam_edge' => 4,
            'notes' => $long
                ? []
                : ['آستین کوتاه‌تر از آرنج است، پس ساسون آرنج لازم ندارد.'],
        ];
    }
}
