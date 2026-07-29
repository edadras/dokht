<?php

namespace App\Services\Pattern\Generators;

/**
 * پولوشرت.
 *
 * تی‌شرتی با یقهٔ کشباف و پاتلت کوتاه دو یا سه دکمه. دقیقاً میان تی‌شرت و
 * پیراهن ایستاده و همین «میان» بودن، جای اشتباهش است.
 *
 * دو چیز که پولو را از تی‌شرتِ یقه‌دار جدا می‌کند:
 *
 *   ۱. یقهٔ پولو بافته نمی‌شود، کشباف است — تکه‌ای جدا از پارچهٔ ریب که خودش
 *      شکل می‌گیرد. اگر از پارچهٔ تنه بریده شود، یقه می‌خوابد و بالا نمی‌ایستد.
 *   ۲. پشت پولو بلندتر از جلوست (دم‌کتی). دلیلش قدیمی و کاربردی است: وقتی
 *      خم می‌شوید پشتِ لباس بالا می‌رود، و این اضافه جبرانش می‌کند.
 */
class PoloShirtGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'polo_shirt';
    }

    public function label(): string
    {
        return 'پولوشرت';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['sleeve_length' => 20, 'body_length' => 16, 'neck_width_extra' => 1],
            [
                'placket_length' => [
                    'label' => 'بلندی پاتلت', 'min' => 8, 'max' => 22, 'step' => 0.5,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'placket_width' => [
                    'label' => 'پهنای پاتلت', 'min' => 2.5, 'max' => 6, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'buttons' => [
                    'label' => 'تعداد دکمه', 'min' => 2, 'max' => 4, 'step' => 1, 'default' => 3,
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه کشباف', 'min' => 4, 'max' => 10, 'step' => 0.5,
                    'default' => 6.5, 'unit' => 'سانتی‌متر',
                ],
                'back_drop' => [
                    'label' => 'بلندتر بودن پشت (دم‌کتی)', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'sleeve_band' => ['label' => 'نوار کشباف سرِ آستین', 'type' => 'toggle', 'default' => true],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);

        [$front, $back] = $this->shirtBody($g, $params, ['prefix' => 'polo']);

        // پشت بلندتر: هنگام خم شدن، پشتِ لباس بالا می‌رود
        $drop = (float) $this->param($params, 'back_drop', 4);
        $back = $this->lengthenHem($back, $drop);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'no_cuff' => true,
            'sleeve_name' => 'آستین پولو',
        ]);

        $neck = (($front['meta']['neck_length'] ?? 12) + ($back['meta']['neck_length'] ?? 9)) * 2;

        $collar = $this->ribBand(
            $neck,
            (float) $this->param($params, 'collar_height', 6.5),
            'polo-collar',
            'یقه کشباف',
            0.9,
        );
        $collar['meta']['notes'][] = 'یقهٔ پولو از پارچهٔ ریب بریده می‌شود، نه از پارچهٔ تنه؛'
            .' یقهٔ بریده‌شده از تنه می‌خوابد و بالا نمی‌ایستد.';

        $buttons = max(2, (int) $this->param($params, 'buttons', 3));
        $slit = (float) $this->param($params, 'placket_length', 14);

        $placket = $this->placket(
            (float) $this->param($params, 'placket_width', 4) / 2,
            $slit,
            'polo-placket',
            'پاتلت پولو',
            spacing: max(3.0, ($slit - 6) / max(1, $buttons - 1)),
        );

        $pieces = array_merge([$front, $back], $sleeves, [$collar, $placket]);

        if ($this->flag($params, 'sleeve_band', true)) {
            $bicep = ((float) ($measurements['bicep'] ?? 28)) + 6;
            $pieces[] = $this->ribBand($bicep, 3, 'polo-sleeve-band', 'نوار سرِ آستین', 0.88, 2);
        }

        return $this->finish($this->noteOn($pieces, [
            'پشت پولو '.$this->fa($drop).' سانتی‌متر بلندتر از جلوست؛ وقتی خم می‌شوید پشتِ لباس بالا می‌رود و این اضافه جبرانش می‌کند.',
        ]));
    }

    /** بلندتر کردن دم یک قطعه، فقط روی مرکز. */
    protected function lengthenHem(array $piece, float $extra): array
    {
        if ($extra < 0.1) {
            return $piece;
        }

        $outline = array_values($piece['outline']);
        $tags = array_values($piece['meta']['edges'] ?? []);
        $count = count($outline);
        $centre = null;
        $centreX = INF;

        for ($i = 0; $i < $count; $i++) {
            if (($tags[$i] ?? '') !== 'hem') {
                continue;
            }

            foreach ([$i, ($i + 1) % $count] as $index) {
                if ((float) $outline[$index]['x'] < $centreX) {
                    $centreX = (float) $outline[$index]['x'];
                    $centre = $index;
                }
            }
        }

        if ($centre === null) {
            return $piece;
        }

        $outline[$centre]['y'] = round(((float) $outline[$centre]['y']) + $extra, 2);

        if (isset($outline[$centre]['cy'])) {
            $outline[$centre]['cy'] = round(((float) $outline[$centre]['cy']) + ($extra * 0.55), 2);
        }

        $piece['outline'] = $outline;

        return $piece;
    }
}
