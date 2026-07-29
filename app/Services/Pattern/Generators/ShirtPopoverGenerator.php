<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن نیمه‌دکمه (پاپ‌اوور).
 *
 * دکمه‌ها فقط تا وسط سینه می‌آیند و بقیهٔ جلو یک‌تکه است؛ پیراهن از سر پوشیده
 * می‌شود، نه از جلو.
 *
 * یک عدد کل این مدل را تعیین می‌کند و بیشترِ اشتباه‌ها همان‌جاست: بلندی چاک
 * دکمه. اگر کوتاه باشد سر از یقه رد نمی‌شود. اندازهٔ لازم از دور سر می‌آید نه
 * از سلیقه، و این‌جا خودکار بررسی می‌شود: دور یقه به‌علاوهٔ دو برابر چاک باید
 * از دور سر بیشتر باشد.
 */
class ShirtPopoverGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'shirt_popover';
    }

    public function label(): string
    {
        return 'پیراهن نیمه‌دکمه (پاپ‌اوور)';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['sleeve_length' => 24, 'fit' => 'loose', 'neck_width_extra' => 1.5],
            array_merge([
                'placket_length' => [
                    'label' => 'بلندی چاک دکمه', 'min' => 12, 'max' => 40, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                    'hint' => 'اگر کوتاه باشد سر از یقه رد نمی‌شود.',
                ],
                'placket_width' => [
                    'label' => 'پهنای پاتلت', 'min' => 2, 'max' => 6, 'step' => 0.5,
                    'default' => 3.5, 'unit' => 'سانتی‌متر',
                ],
                'collar' => [
                    'label' => 'یقه', 'type' => 'select', 'default' => 'camp',
                    'options' => ['camp' => 'یقه باز (کمپ)', 'band' => 'یقه ایستاده', 'shirt' => 'یقه پیراهنی'],
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه', 'min' => 3, 'max' => 12, 'step' => 0.5,
                    'default' => 7.5, 'unit' => 'سانتی‌متر',
                ],
            ], $this->pocketParam(true)),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);

        // جلو روی تای پارچه است: پیراهن از جلو باز نمی‌شود
        [$front, $back, $extras] = $this->shirtBody($g, $params, ['prefix' => 'popover']);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'no_cuff' => true,
            'sleeve_name' => 'آستین',
        ]);

        $neckHalf = ($front['meta']['neck_length'] ?? 12) + ($back['meta']['neck_length'] ?? 9);
        $height = (float) $this->param($params, 'collar_height', 7.5);

        $collar = match ((string) $this->param($params, 'collar', 'camp')) {
            'band' => $this->bandCollar($neckHalf, min(5.0, $height), 0),
            'shirt' => $this->shirtCollar($neckHalf, $height),
            default => $this->campCollar($neckHalf, $height),
        };

        $slit = (float) $this->param($params, 'placket_length', 24);
        $placket = $this->placket(
            (float) $this->param($params, 'placket_width', 3.5) / 2,
            $slit,
            'popover-placket',
            'پاتلت نیمه‌دکمه',
            spacing: 7.0,
        );

        $pieces = array_merge([$front, $back], $extras, $sleeves, [$collar, $placket]);

        if ($this->flag($params, 'chest_pocket', true)) {
            $pieces[] = $this->patchPocket(12, 13.5, ['name' => 'جیب سینه', 'radius' => 2]);
        }

        // بررسی رد شدن سر: دور یقه + دو برابر چاک باید از دور سر بیشتر باشد.
        // دور سر در دفترچهٔ اندازه نیست، پس از دور گردن تخمین زده می‌شود
        // (قاعدهٔ سرانگشتی: دور سر حدود یک‌ونیم برابر دور گردن) تا دست‌کم با
        // بدن همان آدم بخواند، نه با عددی ثابت.
        $head = (float) ($measurements['head'] ?? (((float) ($measurements['neck'] ?? 37)) * 1.5));
        $opening = ($neckHalf * 2) + ($slit * 2);

        $notes = ['پیراهن از سر پوشیده می‌شود؛ جلو یک‌تکه است و فقط تا '.$this->fa($slit).' سانتی‌متر چاک دارد.'];

        if ($opening < $head + 4) {
            $notes[] = 'هشدار: بازشدگی یقه با این چاک حدود '.$this->fa(round($opening))
                .' سانتی‌متر است و دور سر '.$this->fa(round($head))
                .'؛ چاک را بلندتر کنید وگرنه سر رد نمی‌شود.';
        }

        return $this->finish($this->noteOn($pieces, $notes));
    }
}
