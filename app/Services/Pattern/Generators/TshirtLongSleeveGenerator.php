<?php

namespace App\Services\Pattern\Generators;

/**
 * تی‌شرت آستین‌بلند.
 *
 * تنه‌اش همان تی‌شرت است و تفاوتش در آستین: آستین بلندِ کشباف باید در مچ تنگ
 * شود، وگرنه سرِ آستین روی دست می‌افتد.
 *
 * تنگ شدن مچ در پارچهٔ کشی با دو راه انجام می‌شود و هر دو این‌جا هست: یا خودِ
 * آستین به سمت مچ باریک می‌شود، یا نوار کشباف مچ که کوتاه‌تر از دور آستین
 * بریده می‌شود. دومی محکم‌تر است و شکلش را نگه می‌دارد.
 */
class TshirtLongSleeveGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'tshirt_long_sleeve';
    }

    public function label(): string
    {
        return 'تی‌شرت آستین‌بلند';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['sleeve_length' => 58, 'body_length' => 16, 'neck_width_extra' => 1.5, 'front_neck_depth_extra' => 1.5],
            [
                'neckband_height' => [
                    'label' => 'بلندی نوار یقه', 'min' => 1.5, 'max' => 6, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
                'neckband_stretch' => [
                    'label' => 'کشش نوار یقه', 'min' => 0.6, 'max' => 1, 'step' => 0.05, 'default' => 0.85,
                ],
                'wrist_band' => ['label' => 'نوار کشباف مچ', 'type' => 'toggle', 'default' => true],
                'wrist_height' => [
                    'label' => 'بلندی نوار مچ', 'min' => 2, 'max' => 9, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);

        [$front, $back] = $this->shirtBody($g, $params, ['prefix' => 'ls-tee']);

        $band = $this->flag($params, 'wrist_band', true);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'no_cuff' => true,
            'sleeve_name' => 'آستین بلند',
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

        $notes = [];

        if ($band) {
            $wrist = (float) ($measurements['wrist'] ?? 16) + 4;
            $pieces[] = $this->ribBand(
                $wrist * 2,
                (float) $this->param($params, 'wrist_height', 5),
                'wrist-band',
                'نوار کشباف مچ',
                0.8,
                2,
            );

            $notes[] = 'نوار مچ بیست درصد کوتاه‌تر از دور آستین بریده می‌شود؛ همین است که سرِ آستین را نگه می‌دارد.';
        } else {
            $notes[] = 'بدون نوار مچ، خودِ آستین باید به سمت مچ باریک شود وگرنه سرِ آستین روی دست می‌افتد.';
        }

        return $this->finish($this->noteOn($pieces, $notes));
    }
}
