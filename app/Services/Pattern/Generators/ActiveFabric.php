<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * قرارداد مشترک پارچه در لباس ورزشی.
 *
 * دستهٔ ورزشی دو نیمهٔ کاملاً جدا دارد و قاطی‌کردنشان همان‌قدر بد است که قاطی‌کردن
 * مایو با پالتو:
 *
 *   لایهٔ اول (چسبان)   سوتین ورزشی، تایت و رکابی: پارچهٔ کشیِ پرکشش، آزادی
 *                       *منفی*. الگو با ضریب کشسانی کوچک‌تر از بدن بریده می‌شود و
 *                       همان تنگی است که لباس را سر جایش نگه می‌دارد. هر قطعهٔ
 *                       پوسته meta.stretch_ratio می‌گیرد تا هیچ اندازه‌گیری آن را
 *                       با لباس بافته اشتباه نگیرد.
 *   لایهٔ دوم (رویی)    گرمکن و شلوار گرمکن: پارچهٔ بافته یا کشیِ سنگین، آزادی
 *                       *مثبت*. روی لایهٔ اول پوشیده می‌شود، پس باید از آن گشادتر
 *                       باشد؛ این‌ها هیچ ضریب کشسانی اعلام نمی‌کنند.
 *
 * برای همین هر مدل ورزشی صریحاً می‌گوید در کدام نیمه است، و ابزار مشترک آن نیمه
 * را از این‌جا برمی‌دارد.
 */
trait ActiveFabric
{
    /**
     * پارامتر ضریب کشسانی — فقط برای لایهٔ چسبان.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function stretchParam(float $default = 0.88, string $hint = ''): array
    {
        return [
            'stretch' => [
                'label' => 'ضریب کشسانی پارچه', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                'default' => $default, 'unit' => 'نسبت',
                'hint' => $hint !== '' ? $hint
                    : 'الگو به این نسبت از دور بدن کوچک‌تر بریده می‌شود. ۰٫۹ برای جرسی ورزشی معمولی،'
                        .' ۰٫۸ برای پارچهٔ فشاری (کمپرشن).',
            ],
        ];
    }

    /**
     * پارامتر کش لبه‌ها.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function elasticParam(float $default = 0.9): array
    {
        return [
            'elastic_ratio' => [
                'label' => 'کوتاهی کش نسبت به لبه', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                'default' => $default,
                'hint' => 'کش هر لبه این‌قدر کوتاه‌تر بریده می‌شود؛ همین کوتاهی لبه را روی تن نگه می‌دارد.',
            ],
        ];
    }

    /** ضریب کشسانی خوانده‌شده و مهارشده. */
    protected function activeStretch(array $params, float $default = 0.88): float
    {
        return min(1.0, max(0.7, (float) $this->param($params, 'stretch', $default)));
    }

    /**
     * آزادی منفی بر پایهٔ ضریب کشسانی.
     *
     * @param  array<string, float>  $ease
     * @return array<string, float>
     */
    protected function activeEase(array $ease, array $m, float $stretch): array
    {
        $shrink = fn (string $key, float $fallback) => -((float) ($m[$key] ?? $fallback)) * (1 - $stretch);

        return array_merge($ease, [
            'bust' => $shrink('bust', 92),
            'waist' => $shrink('waist', 74),
            'hip' => $shrink('hip', 98),
            'bicep' => 0.0,
        ]);
    }

    /**
     * مهر «این الگو با آزادی منفی بریده شده» روی قطعه‌های پوسته.
     *
     * کلید عمداً از meta.stretch جداست: آن یکی روی نوار کشباف یعنی «نوار این‌قدر
     * کوتاه‌تر از لبه بریده شده»، و این یکی یعنی «کل لباس این‌قدر کوچک‌تر از بدن
     * بریده شده». یکی گرفتنشان بررسی اندازه را گمراه می‌کند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, string>  $parts
     * @return array<int, array<string, mixed>>
     */
    protected function stampStretch(array $pieces, float $stretch, array $parts): array
    {
        if ($stretch >= 0.999) {
            return $pieces;
        }

        foreach ($pieces as $index => $piece) {
            if (! in_array((string) ($piece['meta']['part'] ?? ''), $parts, true)) {
                continue;
            }

            $pieces[$index]['meta']['girth_role'] = $piece['meta']['girth_role'] ?? 'shell';
            $pieces[$index]['meta']['stretch_ratio'] = round($stretch, 3);
        }

        return $pieces;
    }

    /**
     * ثبت کش یک لبه روی قطعه.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function edgeElastic(array $piece, string $tag, string $label, array $params): array
    {
        $ratio = min(1.0, max(0.7, (float) $this->param($params, 'elastic_ratio', 0.9)));
        $length = 0.0;

        foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
            $length += Geometry::edgeLength($piece['outline'], $edge);
        }

        if ($length < 1.0) {
            return $piece;
        }

        $repeats = ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));
        $total = $length * $repeats;

        $piece['meta']['notions'][] = [
            'type' => 'elastic',
            'label' => $label,
            'count' => 1,
            'length' => round($total * $ratio, 1),
        ];
        $piece['meta']['notes'][] = $label.': '.$this->fa(round($total * $ratio))
            .' سانتی‌متر کش برای لبه‌ای به بلندی '.$this->fa(round($total)).' سانتی‌متر.';

        return $piece;
    }

    /**
     * یادداشت‌های همیشگی لایهٔ چسبان.
     *
     * @return array<int, string>
     */
    protected function compressionNotes(float $stretch): array
    {
        return [
            'الگو '.$this->fa(round((1 - $stretch) * 100)).' درصد کوچک‌تر از دور بدن بریده شده؛'
                .' همین تنگی است که لباس را هنگام دویدن سر جایش نگه می‌دارد.',
            'با نخ کشی (استرچ) و سوزن جرسی بدوزید؛ درز معمولی زیر کشیدگی پاره می‌شود.',
            'درزها را تخت (اورلاک چهارنخ یا کاور) بدوزید تا زیر لباس بعدی نساید.',
        ];
    }

    /**
     * یادداشت‌های همیشگی لایهٔ رویی.
     *
     * @return array<int, string>
     */
    protected function shellNotes(): array
    {
        return [
            'این لایه روی لباس ورزشیِ چسبان پوشیده می‌شود، پس آزادی‌اش مثبت است و باید از آن گشادتر بماند.',
            'اگر پارچه بافتهٔ بی‌کشش است، درز پهلو و درز آستین را با درز فرانسوی یا نواردوزی تمام کنید.',
        ];
    }
}
