<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ تاپ و بادی.
 *
 * این همان کاری است که یک برندِ بزرگِ پوشاک با یک تاپ می‌کند: یک درفت درست
 * می‌کند و بعد همان را در سه بلندی، سه فرم و سه پرداختِ لبه به بازار می‌دهد.
 * تاپِ رکابیِ کراپِ جذب با لبهٔ نواری، و همان تاپ در قدِ تونیک با فرمِ گشاد و
 * لبهٔ سجاف‌دار، در ویترین دو محصولِ جدا هستند و روی میزِ برش هم دو الگوی جدا.
 *
 * هر سه محور واقعاً قطعه را عوض می‌کنند:
 *
 * — «فرم» آزادیِ سینه و کمر و باسن را جابه‌جا می‌کند، پس پهنای پنل عوض می‌شود.
 * — «بلندی» جای خطِ پایین را می‌برد، و با آن شیبِ درزِ پهلو در آن ناحیه.
 * — «پرداختِ لبه» یک قطعهٔ کم و زیاد است: نوارِ دورِ حلقه و یقه، یا سجافِ
 *   هم‌شکل که باید جداگانه بریده شود، یا جای کش که فقط لبه را برمی‌گرداند.
 *
 * بادی از محورِ بلندی بیرون است: «بلندی تنه»اش فاصله تا فاق است، نه تا لبهٔ
 * پایین، و کوتاه و بلندش کردن لباس را از کار می‌اندازد.
 */
class TopRangeCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'top';
    }

    /**
     * تاپ‌ها: کلید ⇒ [نام، درفتِ پایه، بلندی‌های پذیرفته].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    protected const TOPS = [
        'tank' => ['رکابی', 'top_tank', ['crop', 'waist', 'hip', 'tunic']],
        'tank_men' => ['رکابی مردانه', 'mens_tank', ['crop', 'waist', 'hip', 'tunic']],
        'racer' => ['ریسربک', 'top_racerback', ['crop', 'waist', 'hip', 'tunic']],
        'cami' => ['بندی', 'top_camisole', ['crop', 'waist', 'hip', 'tunic']],
        'cami_lace' => ['بندی توری', 'top_cami_lace', ['crop', 'waist', 'hip', 'tunic']],
        'halter' => ['هالتر', 'top_halter', ['crop', 'waist', 'hip', 'tunic']],
        'wrap' => ['رویهم', 'top_wrap', ['crop', 'waist', 'hip', 'tunic']],
        'ruched' => ['چین پهلو', 'top_ruched', ['crop', 'waist', 'hip', 'tunic']],
        'crop' => ['کراپ', 'top_crop', ['crop', 'waist', 'hip', 'tunic']],
        'one_shoulder' => ['یک‌سرشانه', 'top_one_shoulder', ['crop', 'waist', 'hip', 'tunic']],
        'backless' => ['پشت‌باز', 'top_backless', ['crop', 'waist', 'hip', 'tunic']],
        'off_shoulder' => ['آف‌شولدر', 'top_off_shoulder', ['crop', 'waist', 'hip', 'tunic']],
        // تاپِ بی‌بند در قدِ تونیک روی تن نمی‌ماند؛ فقط کوتاه و تا کمر
        'bandeau' => ['بندو (بی‌بند)', 'top_bandeau', ['crop', 'waist']],
        // بالاتنه‌های استخوان‌دار از باسن پایین‌تر نمی‌روند
        'bustier' => ['بوستیه', 'top_bustier', ['crop', 'waist', 'hip']],
        'corset' => ['کرست', 'top_corset', ['crop', 'waist', 'hip']],
        'pinafore' => ['پیش‌بندی', 'top_pinafore', ['waist', 'hip', 'tunic']],
        // بادی: «بلندی تنه»اش تا فاق است، پس محورِ قد رویش معنا ندارد
        'bodysuit' => ['بادی', 'top_bodysuit', []],
        'bodysuit_long' => ['بادی آستین‌بلند', 'top_bodysuit_long_sleeve', []],
    ];

    /**
     * بلندی: کلید ⇒ [نام، «بلندی تنه» از خط کمر].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const LENGTHS = [
        'crop' => ['کراپ', -14.0],
        'waist' => ['تا کمر', 2.0],
        'hip' => ['تا باسن', 12.0],
        'tunic' => ['تونیکی', 24.0],
    ];

    /**
     * فرم: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const FITS = [
        'fitted' => 'جذب',
        'regular' => 'معمولی',
        'loose' => 'گشاد',
    ];

    /**
     * پرداختِ لبه: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const FINISHES = [
        'binding' => 'لبه نواری',
        'facing' => 'لبه سجاف‌دار',
        'elastic' => 'لبه کش‌دار',
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::TOPS as $top => [$topName, $base, $lengths]) {
            // فهرستِ خالیِ قد یعنی «این مدل فقط یک بلندی دارد»؛ یک بار می‌چرخد
            // با کلیدِ بی‌قد، نه اینکه از جدول بیفتد
            foreach ($lengths === [] ? [null] : $lengths as $length) {
                $lengthName = $length === null ? '' : static::LENGTHS[$length][0];
                $lengthParams = $length === null ? [] : ['body_length' => static::LENGTHS[$length][1]];

                foreach (static::FITS as $fit => $fitName) {
                    foreach (static::FINISHES as $finish => $finishName) {
                        $key = 'top_range_'.$top.($length === null ? '' : '_'.$length).'_'.$fit.'_'.$finish;

                        $rows[$key] = [
                            'title' => 'تاپ '.$topName.($lengthName === '' ? '' : ' '.$lengthName)
                                .'، فرم '.$fitName.'، '.$finishName,
                            'base' => $base,
                            'params' => array_merge($lengthParams, ['fit' => $fit, 'finish' => $finish]),
                        ];
                    }
                }
            }
        }

        return $rows;
    }
}
