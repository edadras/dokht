<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\RecordsGathers;
use App\Services\Pattern\Geometry;

/**
 * پایه مشترک لباس ساحلی.
 *
 * لباس ساحلی یک خانواده دورگه است: سه مدلش با پارچه بافته و آزادی مثبت بریده
 * می‌شوند (کافتان، ساروَنگ، پیراهن ساحلی) و یکی با پارچه کشی و آزادی **منفی**
 * (راش‌گارد). چون این دو در یک گروه‌اند، خطر اصلی همین است که یکی ادعای دیگری را
 * بکند. پس قرارداد مایوی کاتالوگ عیناً رعایت می‌شود:
 *
 *   stretch()            نسبتی که الگو نسبت به بدن کوچک می‌شود.
 *   meta.stretch_ratio   فقط روی قطعه‌های پوسته مدلی که واقعاً آزادی منفی دارد
 *                        می‌نشیند ($negativeEase). مدل بافته این مهر را نمی‌گیرد،
 *                        وگرنه بررسی اندازه با سنجه غلط سنجیده می‌شود.
 *   elastic()            هر لبه بازِ پارچه کشی کش می‌خواهد، و طول کش از روی خودِ
 *                        لبه اندازه گرفته می‌شود، نه از روی حدس.
 *
 * meta.stretch روی نوار کشباف معنای دیگری دارد («نوار این‌قدر کوتاه‌تر از لبه
 * بریده شده») و عمداً با stretch_ratio یکی گرفته نشده است.
 */
abstract class BeachBaseGenerator extends BodiceGarmentBase
{
    use RecordsGathers;

    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'beach';
    }

    /** آخرین ضریب کشسانی خوانده‌شده؛ برای مهر زدن روی قطعه‌ها. */
    protected ?float $stretchRatio = null;

    /**
     * آیا این مدل کوچک‌تر از بدن بریده می‌شود؟
     *
     * پیش‌فرض «نه»؛ تنها راش‌گارد آن را روشن می‌کند.
     */
    protected bool $negativeEase = false;

    /* ---------------------------------------------------------------------
     |  پارچه کشی
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای پارچه کشی.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function stretchSchema(float $stretch = 0.88): array
    {
        return [
            'stretch' => [
                'label' => 'ضریب کشسانی پارچه', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                'default' => $stretch, 'unit' => 'نسبت',
                'hint' => 'الگو به این نسبت از دور بدن کوچک‌تر بریده می‌شود. ۰٫۸۸ برای لایکرای معمولی،'
                    .' ۰٫۸۰ برای پارچه پرکشش.',
            ],
            'elastic_ratio' => [
                'label' => 'کوتاهی کش نسبت به لبه', 'min' => 0.75, 'max' => 1, 'step' => 0.01,
                'default' => 0.92,
                'hint' => 'کش هر لبه این‌قدر کوتاه‌تر بریده می‌شود؛ همین کوتاهی لبه را روی تن نگه می‌دارد.',
            ],
        ];
    }

    /** نسبت کوچک‌شدن الگو نسبت به بدن. */
    protected function stretch(array $params): float
    {
        return $this->stretchRatio = min(1.0, max(0.7, (float) $this->param($params, 'stretch', 0.88)));
    }

    /**
     * آزادی منفی بر پایه ضریب کشسانی.
     *
     * @param  array<string, float>  $ease
     * @return array<string, float>
     */
    protected function stretchEase(array $ease, array $measurements, array $params): array
    {
        $stretch = $this->stretch($params);
        $shrink = fn (string $key, float $fallback) => -((float) ($measurements[$key] ?? $fallback)) * (1 - $stretch);

        return array_merge($ease, [
            'bust' => $shrink('bust', 92),
            'waist' => $shrink('waist', 74),
            'hip' => $shrink('hip', 98),
            // بازو کمتر کوچک می‌شود؛ آستین بیش از حد تنگ، حرکت شنا را می‌بندد
            'bicep' => $shrink('bicep', 28.5) * 0.5,
        ]);
    }

    /**
     * مهر «این الگو با آزادی منفی بریده شده» روی قطعه‌های پوسته.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function finish(array $pieces): array
    {
        if ($this->negativeEase && $this->stretchRatio !== null && $this->stretchRatio < 0.999) {
            foreach ($pieces as $index => $piece) {
                if (($piece['meta']['girth_role'] ?? '') !== 'shell') {
                    continue;
                }

                $pieces[$index]['meta']['stretch_ratio'] = round($this->stretchRatio, 3);
            }
        }

        return parent::finish($pieces);
    }

    /**
     * ثبت کش یک لبه روی قطعه.
     *
     * طول کش از روی خودِ لبه اندازه گرفته می‌شود و تعداد برش قطعه هم حساب می‌شود:
     * قطعه‌ای که روی تای پارچه است دو برابر لبه دارد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function elastic(array $piece, string $tag, string $label, array $params): array
    {
        $ratio = min(1.0, max(0.75, (float) $this->param($params, 'elastic_ratio', 0.92)));
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

        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            $label.': '.$this->fa(round($total * $ratio)).' سانتی‌متر کش برای لبه‌ای به بلندی '
                .$this->fa(round($total)).' سانتی‌متر.',
        ]);

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  کمک‌های کوچک
     * ------------------------------------------------------------------- */

    /**
     * بلندی لبه پایین از سرشانه، از روی خودِ قطعه.
     *
     * مبدأ هر پنل گوشه بالا-چپ خودش است و بالاترین نقطه همان خط سرشانه/گردن
     * است، پس ارتفاع کادر قطعه همان بلندی از سرشانه است.
     *
     * @param  array<string, mixed>  $piece
     */
    protected function hemFromShoulder(array $piece): float
    {
        return round(Geometry::height($piece['outline'] ?? []), 1);
    }

    /**
     * افزودن یراق به یک قطعه، با یادداشت فارسی.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function addNotion(array $piece, array $notion, ?string $note = null): array
    {
        $piece['meta']['notions'][] = $notion;

        if ($note !== null) {
            $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [$note]);
        }

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  یادداشت‌های همیشگی
     * ------------------------------------------------------------------- */

    /**
     * یادداشت‌های مشترک لباس ساحلی بافته.
     *
     * @param  array<int, string>  $extra
     * @return array<int, string>
     */
    protected function beachNotes(array $extra = []): array
    {
        return array_merge([
            'روی مایو پوشیده می‌شود؛ با پارچه سبک و خوش‌ریزش (ویسکوز، کتان نازک، ابریشم شسته) بدوزید.',
            'پارچه ساحلی خیس می‌شود و سنگین می‌افتد؛ لبه‌ها را با تودوزی باریک یا نوار اریب تمام کنید.',
        ], $extra);
    }

    /**
     * یادداشت‌های همیشگی مدلِ پارچه کشی.
     *
     * @param  array<int, string>  $extra
     * @return array<int, string>
     */
    protected function stretchNotes(array $params, array $extra = []): array
    {
        $stretch = $this->stretch($params);

        return array_merge([
            'الگو '.$this->fa(round((1 - $stretch) * 100)).' درصد کوچک‌تر از دور بدن بریده شده؛'
                .' پارچه کشی با کش آمدن روی تن می‌نشیند و در آب که سنگین می‌شود، همین تنگی نگهش می‌دارد.',
            'با نخ کشی (استرچ) و سوزن جرسی بدوزید؛ درز معمولی با کشیدن پارچه پاره می‌شود.',
        ], $extra);
    }
}
