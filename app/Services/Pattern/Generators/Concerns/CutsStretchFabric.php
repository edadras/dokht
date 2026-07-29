<?php

namespace App\Services\Pattern\Generators\Concerns;

use App\Services\Pattern\Geometry;

/**
 * بریدن روی پارچهٔ کشی: آزادی منفی و کشِ هر لبه.
 *
 * لباس زیر و بیشتر لباس‌های خواب از پارچهٔ کشی بریده می‌شوند و باید **کوچک‌تر از
 * بدن** باشند؛ همان کاری که مایو می‌کند. دلیلش هم یکی است: هیچ بستی این لباس‌ها
 * را روی تن نگه نمی‌دارد، فقط تنگی پارچه و کش لبه‌ها.
 *
 * سه چیز این‌جا جمع شده تا در دو خانوادهٔ لباس زیر و لباس خواب دوباره نوشته نشود:
 *
 *   ۱. «ضریب کشسانی»: الگو به این نسبت از دور بدن کوچک‌تر بریده می‌شود. عدد روی
 *      هر قطعهٔ پوسته در meta.stretch_ratio مهر می‌خورد تا بررسی‌ها بدانند این
 *      لباس عمداً کوچک است، نه اشتباهی.
 *   ۲. کشِ هر لبهٔ باز: کش همیشه کوتاه‌تر از خودِ لبه بریده می‌شود و همان کوتاهی
 *      است که لبه را روی تن نگه می‌دارد. طولش در meta.notions ثبت می‌شود تا در
 *      کارت فنی و فهرست خرید بیاید.
 *   ۳. مدلی که از پارچهٔ بافته بریده می‌شود (روب، پیژامه) $negativeEase را خاموش
 *      می‌کند و هیچ مهرِ کشسانی نمی‌گیرد؛ فرقشان باید صریح باشد نه ضمنی.
 */
trait CutsStretchFabric
{
    /** آخرین ضریب کشسانی خوانده‌شده؛ برای مهر زدن روی قطعه‌ها. */
    protected ?float $stretchRatio = null;

    /** آیا این مدل کوچک‌تر از بدن بریده می‌شود؟ پارچهٔ بافته این را خاموش می‌کند. */
    protected bool $negativeEase = true;

    /**
     * پارامترهای مشترک هر لباس کشی.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function stretchSchema(float $stretch = 0.85, float $elastic = 0.9): array
    {
        return [
            'stretch' => [
                'label' => 'ضریب کشسانی پارچه', 'min' => 0.6, 'max' => 1, 'step' => 0.01,
                'default' => $stretch, 'unit' => 'نسبت',
                'hint' => 'الگو به این نسبت از دور بدن کوچک‌تر بریده می‌شود. ۰٫۸۵ برای جرسی و ریب معمولی،'
                    .' ۰٫۷۵ برای تور و پارچهٔ پرکشش لباس زیر.',
            ],
            'elastic_ratio' => [
                'label' => 'کوتاهی کش نسبت به لبه', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                'default' => $elastic,
                'hint' => 'کش هر لبه این‌قدر کوتاه‌تر بریده می‌شود؛ همین کوتاهی است که لبه را روی تن نگه می‌دارد.',
            ],
        ];
    }

    /** نسبت کوچک‌شدن الگو نسبت به بدن. */
    protected function stretchOf(array $params): float
    {
        return $this->stretchRatio = min(1.0, max(0.6, (float) $this->param($params, 'stretch', 0.85)));
    }

    /**
     * آزادی منفی بر پایهٔ ضریب کشسانی.
     *
     * عمداً از خودِ اندازهٔ بدن ضرب می‌شود، نه از عددی ثابت: ده درصدِ دور سینهٔ
     * ۱۲۰ با ده درصدِ دور سینهٔ ۶۰ یکی نیست، ولی هر دو باید ده درصد تنگ‌تر شوند.
     *
     * @param  array<string, float>  $ease
     * @return array<string, float>
     */
    protected function negativeEaseFor(array $ease, array $measurements, array $params): array
    {
        $stretch = $this->stretchOf($params);

        $shrink = fn (string $key, float $fallback) => -((float) ($measurements[$key] ?? $fallback)) * (1 - $stretch);

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
     * کلیدش عمداً از meta.stretch جداست: آن یکی روی نوار کشباف یعنی «نوار
     * این‌قدر کوتاه‌تر از لبه بریده شده» و این یکی یعنی «کل لباس این‌قدر کوچک‌تر
     * از بدن بریده شده».
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function stampStretch(array $pieces): array
    {
        if (! $this->negativeEase || $this->stretchRatio === null || $this->stretchRatio >= 0.999) {
            return $pieces;
        }

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['girth_role'] ?? '') !== 'shell') {
                continue;
            }

            $pieces[$index]['meta']['stretch_ratio'] = round($this->stretchRatio, 3);
        }

        return $pieces;
    }

    /**
     * ثبت کش روی لبه‌هایی که شمارهٔ آن‌ها داده شده.
     *
     * شماره‌ای بودن (به‌جای برچسبی) لازم است: روی سینه‌بند و کاپ، چند لبه برچسب
     * یکسان دارند ولی فقط بعضی‌شان کش می‌خورند.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<int, int>  $edgeIndexes
     * @return array<string, mixed>
     */
    protected function elasticFor(array $piece, array $edgeIndexes, string $label, array $params, ?float $ratio = null): array
    {
        $ratio ??= (float) $this->param($params, 'elastic_ratio', 0.9);
        $ratio = min(1.0, max(0.7, $ratio));

        $length = 0.0;

        foreach ($edgeIndexes as $edge) {
            $length += Geometry::edgeLength($piece['outline'] ?? [], (int) $edge);
        }

        if ($length < 1.0) {
            return $piece;
        }

        $repeats = ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));
        $total = $length * $repeats;

        $piece['meta']['notions'][] = [
            'type' => 'elastic',
            'label' => $label,
            'length' => round($total * $ratio, 1),
            'edge_length' => round($total, 1),
        ];

        $piece['meta']['notes'][] = $label.': '.$this->fa(round($total * $ratio))
            .' سانتی‌متر کش برای لبه‌ای به بلندی '.$this->fa(round($total)).' سانتی‌متر.';

        return $piece;
    }

    /**
     * ثبت کش روی همهٔ لبه‌هایی که یک برچسب دارند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function elasticOn(array $piece, string $tag, string $label, array $params, ?float $ratio = null): array
    {
        return $this->elasticFor($piece, Geometry::edgesWithTag($piece, $tag), $label, $params, $ratio);
    }

    /**
     * لبه‌هایی که این برچسب را دارند ولی تای پارچه نیستند.
     *
     * لازم است چون خط مرکز جلو و خط بالای بریده‌شدهٔ تاپ هر دو برچسب default
     * می‌گیرند: یکی تای پارچه است و کش نمی‌خورد، آن یکی لبهٔ باز است و کش
     * می‌خورد. بی این جدا کردن، طول کش دو برابر واقعیت حساب می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<int, int>
     */
    protected function openEdges(array $piece, string $tag): array
    {
        return array_values(array_diff(
            Geometry::edgesWithTag($piece, $tag),
            array_map('intval', $piece['meta']['fold_edges'] ?? []),
        ));
    }

    /**
     * راستای اریب روی یک قطعه.
     *
     * پارچهٔ بافته روی اریب کشِ خودش را پیدا می‌کند؛ کمبینزون و لباس خواب ساتن
     * دقیقاً به همین دلیل اریب بریده می‌شوند، نه برای زیبایی.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function onBias(array $piece, string $label = 'راستای پارچه (اریب)'): array
    {
        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($piece['outline'] ?? []);

        $width = max(2.0, $maxX - $minX);
        $height = max(2.0, $maxY - $minY);
        $span = min($width, $height) * 0.6;

        $piece['grainline'] = [
            'from' => Geometry::point($minX + ($width * 0.2), $minY + ($height * 0.2)),
            'to' => Geometry::point($minX + ($width * 0.2) + $span, $minY + ($height * 0.2) + $span),
            'label' => $label,
        ];

        $piece['meta']['bias'] = true;

        return $piece;
    }
}
