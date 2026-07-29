<?php

namespace App\Services\Pattern\Generators;

/**
 * هنلی.
 *
 * تی‌شرتی با پاتلت دکمه ولی بدون یقه — پولو منهای یقه.
 *
 * چون یقه ندارد، خط یقه خودش باید تمام‌شده و تمیز باشد و همان‌جا هم سخت‌ترین
 * قسمت کار است: نوار یقه باید از بالای پاتلت رد شود و دو سرش زیر پاتلت پنهان
 * شود. اگر نوار به اندازهٔ کل دور یقه بریده شود، دو سرش بیرون می‌ماند؛ برای
 * همین در این الگو طول نوار به اندازهٔ پهنای پاتلت کوتاه‌تر حساب شده.
 */
class HenleyShirtGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'henley_shirt';
    }

    public function label(): string
    {
        return 'هنلی (تی‌شرت دکمه‌دار)';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['sleeve_length' => 22, 'body_length' => 16, 'neck_width_extra' => 1.5],
            [
                'placket_length' => [
                    'label' => 'بلندی پاتلت', 'min' => 10, 'max' => 26, 'step' => 0.5,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
                'placket_width' => [
                    'label' => 'پهنای پاتلت', 'min' => 2.5, 'max' => 6, 'step' => 0.5,
                    'default' => 3.5, 'unit' => 'سانتی‌متر',
                ],
                'buttons' => [
                    'label' => 'تعداد دکمه', 'min' => 2, 'max' => 5, 'step' => 1, 'default' => 3,
                ],
                'neckband_height' => [
                    'label' => 'بلندی نوار یقه', 'min' => 1.5, 'max' => 5, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);

        [$front, $back] = $this->shirtBody($g, $params, ['prefix' => 'henley']);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'no_cuff' => true,
            'sleeve_name' => 'آستین هنلی',
        ]);

        $width = (float) $this->param($params, 'placket_width', 3.5);
        $neck = (($front['meta']['neck_length'] ?? 12) + ($back['meta']['neck_length'] ?? 9)) * 2;

        // دو سر نوار زیر پاتلت پنهان می‌شود، پس به همان اندازه کوتاه‌تر است
        $band = $this->ribBand(max(20.0, $neck - $width), (float) $this->param($params, 'neckband_height', 2), 'henley-neckband', 'نوار یقه', 0.88);
        $band['meta']['notes'][] = 'طول نوار به اندازهٔ پهنای پاتلت کوتاه‌تر گرفته شده تا دو سرش زیر پاتلت پنهان شود.';

        $buttons = max(2, (int) $this->param($params, 'buttons', 3));
        $slit = (float) $this->param($params, 'placket_length', 16);

        $placket = $this->placket(
            $width / 2,
            $slit,
            'henley-placket',
            'پاتلت هنلی',
            spacing: max(3.0, ($slit - 6) / max(1, $buttons - 1)),
        );

        return $this->finish($this->noteOn(
            array_merge([$front, $back], $sleeves, [$band, $placket]),
            ['هنلی یقه ندارد، پس خط یقه خودش باید تمام‌شده باشد؛ نوار یقه از بالای پاتلت رد می‌شود.'],
        ));
    }
}
