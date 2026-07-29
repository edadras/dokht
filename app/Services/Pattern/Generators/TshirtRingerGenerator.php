<?php

namespace App\Services\Pattern\Generators;

/**
 * رینگر تی‌شرت.
 *
 * تی‌شرتی که نوار یقه و لبهٔ آستینش از پارچهٔ رنگ دیگری است. کل تفاوتش همین
 * دو نوار است، ولی همان دو نوار یک قاعده را ناگزیر می‌کنند: نوارها باید از
 * پارچهٔ کشباف باشند و کشش‌شان با تنه یکی نباشد مهم نیست — ولی *اندازه*شان
 * باید کوتاه‌تر از لبه‌ای باشد که رویش می‌نشیند.
 *
 * برای همین در این الگو نوار آستین هم مثل نوار یقه با ضریب کشش بریده می‌شود،
 * نه به اندازهٔ خودِ لبه. نوارِ هم‌اندازه، لبه را موج می‌اندازد.
 */
class TshirtRingerGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'tshirt_ringer';
    }

    public function label(): string
    {
        return 'رینگر تی‌شرت';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['sleeve_length' => 18, 'body_length' => 14, 'neck_width_extra' => 1.5],
            [
                'band_height' => [
                    'label' => 'بلندی نوارها', 'min' => 1.5, 'max' => 6, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
                'band_stretch' => [
                    'label' => 'کشش نوارها', 'min' => 0.6, 'max' => 1, 'step' => 0.05, 'default' => 0.85,
                    'hint' => 'نوار هم‌اندازهٔ لبه، لبه را موج می‌اندازد.',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);
        $stretch = (float) $this->param($params, 'band_stretch', 0.85);
        $height = (float) $this->param($params, 'band_height', 2.5);

        [$front, $back] = $this->shirtBody($g, $params, ['prefix' => 'ringer']);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'no_cuff' => true,
            'sleeve_name' => 'آستین',
        ]);

        $neck = (($front['meta']['neck_length'] ?? 12) + ($back['meta']['neck_length'] ?? 9)) * 2;
        $bicep = ((float) ($measurements['bicep'] ?? 28)) + 6;

        $neckBand = $this->ribBand($neck, $height, 'ringer-neckband', 'نوار یقه (رنگ متضاد)', $stretch);
        $sleeveBand = $this->ribBand($bicep, $height, 'ringer-sleeve-band', 'نوار آستین (رنگ متضاد)', $stretch, 2);

        $neckBand['meta']['contrast'] = true;
        $sleeveBand['meta']['contrast'] = true;
        $sleeveBand['meta']['part'] = 'trim';

        return $this->finish($this->noteOn(
            array_merge([$front, $back], $sleeves, [$neckBand, $sleeveBand]),
            [
                'نوار یقه و نوار آستین از پارچهٔ رنگ دیگری بریده می‌شوند؛ در صورت مواد دو پارچه بخواهید.',
                'هر دو نوار '.$this->fa(round((1 - $stretch) * 100)).' درصد کوتاه‌تر از لبه‌شان‌اند و کشیده دوخته می‌شوند.',
            ],
        ));
    }
}
