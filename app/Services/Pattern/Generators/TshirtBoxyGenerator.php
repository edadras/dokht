<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * تی‌شرت باکسی (اورسایز).
 *
 * تنه‌ای که از کمر تنگ نمی‌شود و سرشانه‌ای که روی بازو می‌افتد. برخلاف تصور،
 * «باکسی» یعنی نسبت، نه اندازه: تنه باید پهن و کوتاه باشد. تی‌شرت گشادِ بلند،
 * باکسی نیست؛ فقط بزرگ است.
 *
 * برای همین بلندی پیش‌فرض این مدل کوتاه‌تر از تی‌شرت معمولی است و آزادی‌اش
 * بیشتر — و هرچه سرشانه بیشتر بیفتد، حلقه هم گودتر می‌شود تا آستین بنشیند.
 */
class TshirtBoxyGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'tshirt_boxy';
    }

    public function label(): string
    {
        return 'تی‌شرت باکسی (اورسایز)';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['fit' => 'boxy', 'drop_shoulder' => 6, 'body_length' => 8, 'sleeve_length' => 20, 'armhole_depth_extra' => 4, 'neck_width_extra' => 2],
            [
                'neckband_height' => [
                    'label' => 'بلندی نوار یقه', 'min' => 1.5, 'max' => 6, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
                'neckband_stretch' => [
                    'label' => 'کشش نوار یقه', 'min' => 0.6, 'max' => 1, 'step' => 0.05, 'default' => 0.85,
                ],
                'hem_band' => ['label' => 'نوار کشباف دم لباس', 'type' => 'toggle', 'default' => false],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $drop = (float) $this->param($params, 'drop_shoulder', 6);
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);

        $g['shoulder_half'] += $drop;
        $g['across_back'] += $drop * 0.6;
        $g['across_chest'] += $drop * 0.6;

        [$front, $back] = $this->shirtBody($g, $params, ['prefix' => 'boxy']);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'no_cuff' => true,
            'sleeve_name' => 'آستین باکسی',
        ]);

        $neck = (($front['meta']['neck_length'] ?? 12) + ($back['meta']['neck_length'] ?? 9)) * 2;

        $pieces = array_merge([$front, $back], $sleeves, [
            $this->ribBand(
                $neck,
                (float) $this->param($params, 'neckband_height', 2.5),
                'neckband',
                'نوار یقه',
                (float) $this->param($params, 'neckband_stretch', 0.85),
            ),
        ]);

        if ($this->flag($params, 'hem_band', false)) {
            $width = Geometry::width($front['outline']);
            $pieces[] = $this->ribBand($width * 4, 4, 'hem-band', 'نوار کشباف دم', 0.9);
        }

        return $this->finish($this->noteOn($pieces, [
            'باکسی یعنی نسبت، نه اندازه: تنه پهن و کوتاه است. تی‌شرت گشادِ بلند باکسی نیست، فقط بزرگ است.',
            'سرشانه '.$this->fa($drop).' سانتی‌متر افتاده و حلقه به همان نسبت گودتر شده.',
        ]));
    }
}
