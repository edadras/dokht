<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ تاپ و بادی.
 *
 * این همان کاری است که یک برندِ بزرگِ پوشاک با یک تاپ می‌کند: یک درفت درست
 * می‌کند و بعد همان را در چند بلندی، چند فرم و چند ساختِ متفاوت به بازار می‌دهد.
 * تاپِ رکابیِ کراپِ جذبِ کشباف، و همان تاپ در قدِ تونیک با فرمِ گشاد روی پارچهٔ
 * بافته، در ویترین دو محصولِ جدا هستند و روی میزِ برش هم دو الگوی جدا.
 *
 * «بلندی» محورِ مشترکِ همه است: جای خطِ پایین را می‌برد، و با آن شیبِ درزِ پهلو
 * در آن ناحیه. باقیِ محورها برای هر مدل جداست، چون هر درفت چیزِ دیگری دارد که
 * واقعاً عوض می‌شود.
 *
 * محورهای هر مدل عمداً از روی *خروجی* انتخاب شده‌اند، نه از روی فرم، و این از
 * دو اشتباه درآمد:
 *
 * اول، برای همه یک محورِ مشترک گذاشته شد — «پرداختِ لبه» (نوار اریب، سجاف، جای
 * کش). سه گزینه‌ای که در فرم دیده می‌شوند و در دستور دوخت هم فرق دارند، ولی
 * *الگو* را دست نمی‌زنند: هیچ‌کدام قطعه‌ای کم و زیاد نمی‌کند. نتیجه ۴۳۲ ردیف بود
 * که هر سه‌تا سه‌تا یک الگو بودند.
 *
 * دوم، «فرم لباس» به همه داده شد و روی چند مدل هیچ کاری نمی‌کرد. سرِ بعضی‌شان
 * ایرادِ خودِ درفت بود و درست شد (بوستیه فرم را حساب می‌کرد و به‌کار نمی‌بُرد)؛
 * بعضی دیگر واقعاً فرم ندارند و نباید داشته باشند: ریسربک و بندو و آف‌شولدر
 * اندازهٔ تنشان را از خطِ بالا و بند و کششِ پارچه می‌گیرند، نه از آزادیِ سینه.
 * آن سه محورِ خودشان را گرفتند.
 *
 * «پرداختِ لبه» سرِ جایش در فرمِ مدل ماند؛ فقط از جدول بیرون رفت.
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
     * تاپ‌ها: کلید ⇒ [نام، درفتِ پایه، محورها].
     *
     * هر محور: نامِ پارامتر ⇒ (کلیدِ گزینه ⇒ [نامِ فارسی، مقدار]).
     *
     * محورها برای هر مدل جدا نوشته شده‌اند، چون هر درفت پارامترهای *خودش* را
     * می‌شناسد و — مهم‌تر — چون پارامتری که در فرم هست لزوماً الگو را عوض
     * نمی‌کند. هر محورِ این جدول آزموده شده که واقعاً قطعه را جابه‌جا می‌کند.
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, array<string, array{0: string, 1: mixed}>>}>
     */
    protected const TOPS = [
        'tank' => ['رکابی', 'top_tank', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'knit' => self::FABRIC]],
        'tank_men' => ['رکابی مردانه', 'mens_tank', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'knit' => self::FABRIC]],
        'cami' => ['بندی', 'top_camisole', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'top_shape' => self::TOP_LINE]],
        'cami_lace' => ['بندی توری', 'top_cami_lace', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'top_shape' => self::TOP_LINE]],
        'halter' => ['هالتر', 'top_halter', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'tie' => self::TIE]],
        'wrap' => ['رویهم', 'top_wrap', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'shoulder_width' => self::SHOULDER]],
        'ruched' => ['چین پهلو', 'top_ruched', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'shoulder_width' => self::SHOULDER]],
        'crop' => ['کراپ', 'top_crop', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'shoulder_width' => self::SHOULDER]],
        'one_shoulder' => ['یک‌سرشانه', 'top_one_shoulder', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'strap_width' => self::STRAP]],
        'backless' => ['پشت‌باز', 'top_backless', ['crop', 'waist', 'hip', 'tunic'], ['fit' => self::FITS, 'back_shape' => self::BACK_LINE]],
        'pinafore' => ['پیش‌بندی', 'top_pinafore', ['waist', 'hip', 'tunic'], ['fit' => self::FITS, 'bib_height' => self::BIB]],

        // ── این سه فرم نمی‌گیرند؛ اندازهٔ تنشان از خطِ بالا و بند می‌آید ─────
        'racer' => ['ریسربک', 'top_racerback', ['crop', 'waist', 'hip', 'tunic'], ['racer_depth' => self::RACER, 'neck_drop' => self::NECK]],
        'off_shoulder' => ['آف‌شولدر', 'top_off_shoulder', ['crop', 'waist', 'hip', 'tunic'], ['ruffle' => self::RUFFLE, 'band_height' => self::BAND]],
        // تاپِ بی‌بند در قدِ تونیک روی تن نمی‌ماند؛ فقط کوتاه و تا کمر
        'bandeau' => ['بندو (بی‌بند)', 'top_bandeau', ['crop', 'waist'], ['top_shape' => self::BANDEAU_LINE, 'negative_ease' => self::HOLD]],
        // بالاتنه‌های استخوان‌دار از باسن پایین‌تر نمی‌روند. فرم دارند، ولی
        // بستِ پشت و خطِ بالا چیزی است که خریدارشان با آن انتخاب می‌کند
        'bustier' => ['بوستیه', 'top_bustier', ['crop', 'waist', 'hip'], ['closure' => self::CLOSURE, 'top_shape' => self::BANDEAU_LINE]],
        'corset' => ['کرست', 'top_corset', ['crop', 'waist', 'hip'], ['closure' => self::CLOSURE, 'top_shape' => self::BANDEAU_LINE]],
        // بادی: «بلندی تنه»اش تا فاق است، پس محورِ قد رویش معنا ندارد
        'bodysuit' => ['بادی', 'top_bodysuit', [], ['fit' => self::FITS, 'shoulder_width' => self::SHOULDER, 'leg_rise' => self::LEG_RISE, 'gusset' => self::GUSSET]],
        // بادیِ آستین‌دار سرشانهٔ کامل می‌خواهد (بندِ باریک حلقه ندارد و آستین
        // جایی ندارد بنشیند)، پس به‌جای پهنای سرشانه، بلندیِ آستین محورش است
        'bodysuit_long' => ['بادی آستین‌بلند', 'top_bodysuit_long_sleeve', [], ['fit' => self::FITS, 'sleeve_length' => self::SLEEVE, 'leg_rise' => self::LEG_RISE, 'gusset' => self::GUSSET]],
    ];

    /** پارچه: کشباف یا بافته. روی پارچهٔ کشباف الگو تنگ‌تر بریده می‌شود. */
    protected const FABRIC = [
        'knit' => ['کشباف', true],
        'woven' => ['بافته', false],
    ];

    /** عمقِ برشِ ریسربک از سرشانه. */
    protected const RACER = [
        'soft' => ['ریسربک کم‌عمق', 6.0],
        'classic' => ['ریسربک معمولی', 9.0],
        'deep' => ['ریسربک عمیق', 13.0],
    ];

    /** گودیِ یقهٔ جلو. */
    protected const NECK = [
        'high' => ['یقه بسته', 4.0],
        'classic' => ['یقه معمولی', 7.0],
        'low' => ['یقه باز', 12.0],
    ];

    /** خطِ بالای تاپِ بندی. */
    protected const TOP_LINE = [
        'straight' => ['خط بالای صاف', 'straight'],
        'sweetheart' => ['خط بالای قلبی', 'sweetheart'],
        'scoop' => ['خط بالای گرد', 'scoop'],
    ];

    /** خطِ بالای بندو و بالاتنهٔ استخوان‌دار؛ گودیِ گرد رویشان نمی‌ایستد. */
    protected const BANDEAU_LINE = [
        'straight' => ['خط بالای صاف', 'straight'],
        'sweetheart' => ['خط بالای قلبی', 'sweetheart'],
    ];

    /** چقدر تنگ‌تر از بدن بریده شود تا تاپِ بی‌بند سرِ جایش بماند. */
    protected const HOLD = [
        'easy' => ['نگه‌داری ملایم', 2.0],
        'classic' => ['نگه‌داری معمولی', 4.0],
        'firm' => ['نگه‌داری محکم', 7.0],
    ];

    /**
     * بستنِ بندِ هالتر پشتِ گردن.
     *
     * «دکمه‌ای» این‌جا نیست چون روی الگو با «دوخته» یکی درمی‌آید: هر دو بندِ
     * کوتاهِ اندازه‌شده می‌خواهند و فقط در دوختِ آخر فرق دارند. گزینه‌اش سرِ
     * جایش در فرمِ مدل هست.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const TIE = [
        'tie' => ['بند گره‌ای', 'tie'],
        'fixed' => ['بند دوخته', 'fixed'],
    ];

    /** پهنای سرشانه: از سرشانهٔ کامل تا بندِ باریک. */
    protected const SHOULDER = [
        'full' => ['سرشانه کامل', 'full'],
        'strap' => ['بند پهن', 'strap'],
        'thin' => ['بند باریک', 'thin'],
    ];

    /** پهنای بندِ تاپِ یک‌سرشانه. */
    protected const STRAP = [
        'narrow' => ['بند باریک', 3.0],
        'medium' => ['بند متوسط', 5.0],
        'wide' => ['بند پهن', 8.0],
    ];

    /** شکلِ خطِ پشتِ تاپِ پشت‌باز. */
    protected const BACK_LINE = [
        'straight' => ['پشت صاف', 'straight'],
        'scoop' => ['پشت گرد', 'scoop'],
        'sweetheart' => ['پشت قلبی', 'sweetheart'],
    ];

    /** بلندیِ پیش‌سینهٔ تاپِ پیش‌بندی. */
    protected const BIB = [
        'low' => ['پیش‌سینه کوتاه', 3.0],
        'classic' => ['پیش‌سینه معمولی', 6.0],
        'high' => ['پیش‌سینه بلند', 11.0],
    ];

    /** فِرِفِرِ لبهٔ آف‌شولدر: یک قطعهٔ اضافه است، نه یک تزیین. */
    protected const RUFFLE = [
        'plain' => ['لبه ساده', false],
        'ruffled' => ['لبه فرفری', true],
    ];

    /** بلندیِ نوارِ لبهٔ آف‌شولدر. */
    protected const BAND = [
        'narrow' => ['نوار باریک', 2.0],
        'classic' => ['نوار معمولی', 3.0],
        'wide' => ['نوار پهن', 5.5],
    ];

    /**
     * بستِ مرکز پشتِ بالاتنه‌های استخوان‌دار.
     *
     * «قزن ردیفی» این‌جا نیست چون روی الگو با زیپ یکی است: هر دو مرکزِ پشت را
     * باز می‌گذارند و فقط در دوخت فرق دارند. بندِ کشی اما مرکزِ پشت را تای
     * پارچه می‌کند و قطعه را عوض می‌کند. گزینهٔ قزن سرِ جایش در فرمِ مدل هست.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const CLOSURE = [
        'zip' => ['بست زیپ یا قزن', 'zip'],
        'lacing' => ['بند کشی', 'lacing'],
    ];

    /** خطِ پای بادی: هرچه بالاتر، پا بلندتر دیده می‌شود. */
    protected const LEG_RISE = [
        'low' => ['خط پای کوتاه', 1.0],
        'classic' => ['خط پای معمولی', 4.0],
        'high' => ['خط پای بلند', 9.0],
    ];

    /** بلندیِ آستینِ بادی. */
    protected const SLEEVE = [
        'short' => ['آستین کوتاه', 22.0],
        'elbow' => ['آستین تا آرنج', 36.0],
        'long' => ['آستین بلند', 58.0],
    ];

    /** لایهٔ فاقِ بادی: یک قطعهٔ جدا. */
    protected const GUSSET = [
        'gusset' => ['با لایه فاق', true],
        'plain' => ['بی‌لایه فاق', false],
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
     * فرم لباس.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const FITS = [
        'fitted' => ['فرم جذب', 'fitted'],
        'regular' => ['فرم معمولی', 'regular'],
        'loose' => ['فرم گشاد', 'loose'],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::TOPS as $top => [$topName, $base, $lengths, $axes]) {
            $combos = static::combine($axes);

            // فهرستِ خالیِ قد یعنی «این مدل فقط یک بلندی دارد»؛ یک بار می‌چرخد
            // با کلیدِ بی‌قد، نه اینکه از جدول بیفتد
            foreach ($lengths === [] ? [null] : $lengths as $length) {
                $lengthName = $length === null ? '' : static::LENGTHS[$length][0];
                $lengthParams = $length === null ? [] : ['body_length' => static::LENGTHS[$length][1]];

                foreach ($combos as [$suffix, $names, $params]) {
                    $key = 'top_range_'.$top.($length === null ? '' : '_'.$length).$suffix;

                    $rows[$key] = [
                        'title' => 'تاپ '.$topName.($lengthName === '' ? '' : ' '.$lengthName)
                            .'، '.implode('، ', $names),
                        'base' => $base,
                        'params' => array_merge($lengthParams, $params),
                    ];
                }
            }
        }

        return $rows;
    }
}
