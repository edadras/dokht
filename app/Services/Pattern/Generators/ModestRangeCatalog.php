<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ پیراهنِ بلند و مانتو و تونیک.
 *
 * درفت‌های پایه‌اش پیراهن‌های بلندِ منطقه‌ای‌اند — از کُردی و بلوچی و قشقایی تا
 * جلابیه و کورتا و یوکاتا — و همه یک اسکلتِ مشترک دارند: تنه‌ای که از خطِ کمر
 * به پایین باز می‌شود، آستینِ راسته و یقهٔ ساده. برندهای پوشاکِ محتشم دقیقاً
 * روی همین اسکلت کار می‌کنند: یک تنه، و بعد همان در سه قد و سه آستین و سه فرم.
 *
 * چهار محور، هر چهار سازنده:
 *
 * — «قد» خطِ پایین و با آن مقدارِ کلوش را جابه‌جا می‌کند.
 * — «آستین» یا قطعهٔ آستین را می‌آورد یا حذفش می‌کند و حلقه را تمیزدوزی می‌کند.
 * — «فرم» آزادیِ تنه را عوض می‌کند.
 * — «یقه» یک قطعهٔ کم و زیاد است: نوارِ ایستاده، یا لبهٔ تمیزدوزی‌شده.
 */
class ModestRangeCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'traditional';
    }

    /**
     * پیراهن‌ها: کلید ⇒ [نام، درفتِ پایه، سه قد، یقهٔ انتخابی دارد، آستین‌های پذیرفته].
     *
     * قدها عددِ مطلق‌اند نه نسبی، چون هر درفت بازهٔ خودش را دارد: کورتا از ۱۶ تا
     * ۷۰ سانتی‌متر می‌رود و جلابیه از ۴۰ تا ۱۶۰. یک «کوتاه» و «بلند» مشترک برای
     * هر دو، یکی را از بازه بیرون می‌انداخت.
     *
     * ستونِ آخر محورِ *پنجم* هر پیراهن است: چیزی که روی همان درفت قطعه را عوض
     * می‌کند. یکی نیست چون هر پیراهن ساختِ خودش را دارد — پیراهنِ بلندِ عربی
     * چاکِ جلو دارد و کورتا کلوشِ دم. آنچه رویش اثر ندارد این‌جا نمی‌آید.
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, float>, 3: bool, 4?: array<int, string>, 5?: array{0: string, 1: array<string, array{0: string, 1: float}>}}>
     */
    protected const ROBES = [
        'kurta' => ['کورتا', 'trad_kurta', [32, 40, 56], true, null, ['pocket', self::POCKETS]],
        'bindalli' => ['بیندالی', 'trad_bindalli', [110, 136, 155], true, null, ['front_slit', self::SLIT]],
        'dishdasha' => ['دشداشه', 'trad_dishdasha', [118, 142, 158], true, null, ['front_slit', self::SLIT]],
        'kebaya' => ['کبایا', 'trad_kebaya', [50, 62, 78], true, null, ['front_slit', self::SLIT]],
        'yukata' => ['یوکاتا', 'trad_yukata', [118, 140, 158], true, null, ['front_slit', self::SLIT]],
        'kurdish' => ['پیراهن کردی', 'trad_kurdish_dress', [108, 132, 152], true, null, ['front_slit', self::SLIT]],
        'aodai' => ['آئو دای', 'trad_ao_dai', [104, 128, 150], true, null, ['front_slit', self::SLIT]],
        'huipil' => ['ویپیل', 'trad_huipil', [70, 92, 112], true, null, ['front_slit', self::SLIT]],
        'gilaki' => ['پیراهن گیلکی', 'trad_gilaki', [74, 96, 116], true, null, ['front_slit', self::SLIT]],
        'ferace' => ['فراجه', 'trad_ferace', [118, 142, 158], true, null, ['front_slit', self::SLIT]],
        'qashqai' => ['پیراهن قشقایی', 'trad_qashqai', [96, 118, 140], true, null, ['front_slit', self::SLIT]],
        'dashiki' => ['داشیکی', 'trad_dashiki', [66, 88, 108], true, null, ['front_slit', self::SLIT]],
        'turkmen' => ['پیراهن ترکمن', 'trad_turkmen', [110, 134, 154], true, null, ['front_slit', self::SLIT]],
        'bandari' => ['پیراهن بندری', 'trad_bandari', [114, 138, 158], true, null, ['front_slit', self::SLIT]],
        'lori' => ['پیراهن لری', 'trad_lori', [100, 124, 146], true, null, ['front_slit', self::SLIT]],
        'baluchi' => ['پیراهن بلوچی', 'trad_baluchi', [106, 130, 152], true, null, ['front_slit', self::SLIT]],
        'jalabiya' => ['جلابیه', 'trad_jalabiya', [116, 140, 158], true, null, ['front_slit', self::SLIT]],
        /*
         * تونیکِ پوشیده دو نکته دارد: فقط آستینِ دوخته‌شده می‌گیرد (حلقهٔ بازش را
         * درفت نمی‌شناسد)، و قدِ کوتاهش عمداً از باسن پایین‌تر است — تونیکِ کلوشِ
         * کوتاه خطِ باسنش وسطِ کلوش می‌افتد و روی بدنِ درشت از بازهٔ کاتالوگ
         * بیرون می‌زند. همین برای شلوار کمیض هم هست.
         */
        'tunic' => ['تونیک پوشیده', 'trad_modest_tunic', [40, 48, 62], true, ['short', 'long'], ['cuff', self::CUFFS]],
        // این دو یقهٔ ایستادهٔ ثابت دارند و آن بخشی از هویتشان است
        'qipao' => ['چیپائو', 'trad_qipao', [58, 72, 95], false, null, ['overlap', self::OVERLAP]],
        'shalwar' => ['شلوار کمیض', 'trad_shalwar_kameez', [48, 58, 72], false, null, ['shalwar_ankle', self::ANKLES]],
    ];

    /** چاکِ جلو: بسته یا باز. روی پیراهنِ بلند، چاک راهِ رفتن را باز می‌کند. */
    protected const SLIT = [
        'closed' => ['بی‌چاک', 0.0],
        'slit' => ['چاک جلو', 30.0],
    ];

    /*
     * سه پیراهنِ کوتاهِ این جدول — کورتا، تونیک و کمیض — محورِ کلوشِ دم نگرفتند،
     * و این از یک تلهٔ واقعی درآمد.
     *
     * این‌ها کوتاه‌اند و خطِ باسنشان *داخلِ* کلوش می‌افتد، نه بالای آن. پس هر
     * سانتی‌متر کلوش مستقیم به دورِ باسنِ تمام‌شده اضافه می‌شود: کلوشِ محسوس،
     * لباس را از بازهٔ کاتالوگ بیرون می‌برد، و کلوشِ آن‌قدر کم که در بازه بماند،
     * درفت خودش گِردش می‌کند و دو ردیف یک الگو می‌شوند. هر دو سرش بسته بود.
     *
     * پس محورشان چیزی شد که قطعه را عوض می‌کند و به دورِ باسن کاری ندارد: جیبِ
     * کورتا، مچ‌بندِ تونیک و دمِ پاچهٔ شلوارِ کمیض.
     */

    /** جیبِ کورتا: یک قطعهٔ کاملِ اضافه. */
    protected const POCKETS = [
        'plain' => ['بی‌جیب', false],
        'pocket' => ['جیب‌دار', true],
    ];

    /** مچ‌بندِ تونیک: نوارِ جدا روی دمِ آستین. */
    protected const CUFFS = [
        'plain' => ['بی‌مچ‌بند', false],
        'cuffed' => ['مچ‌بنددار', true],
    ];

    /** دمِ پاچهٔ شلوارِ کمیض. */
    protected const ANKLES = [
        'narrow' => ['دم پاچه جمع', 24.0],
        'wide' => ['دم پاچه گشاد', 34.0],
    ];

    /** رویهم‌آمدنِ جلوی چیپائو. */
    protected const OVERLAP = [
        'narrow' => ['رویهم کم', 2.0],
        'wide' => ['رویهم پهن', 6.0],
    ];

    /**
     * قد: کلید ⇒ نام. عددش از ستونِ سومِ جدولِ بالا می‌آید.
     *
     * @var array<int, string>
     */
    protected const LENGTH_NAMES = ['قد کوتاه', 'قد معمولی', 'قد بلند'];

    /** کلیدِ قد، هم‌ترتیب با LENGTH_NAMES. */
    protected const LENGTH_KEYS = ['short', 'mid', 'long'];

    /**
     * آستین: کلید ⇒ [نام، سبک، بلندی].
     *
     * @var array<string, array{0: string, 1: string, 2: float}>
     */
    protected const SLEEVES = [
        'none' => ['بی‌آستین', 'none', 0.0],
        'short' => ['آستین کوتاه', 'set_in', 26.0],
        'long' => ['آستین بلند', 'set_in', 58.0],
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
     * یقه: کلید ⇒ [نام، مقدارِ پارامتر].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const COLLARS = [
        'plain' => ['بدون یقه', 'none'],
        'stand' => ['یقه ایستاده', 'stand'],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::ROBES as $robe => $row) {
            [$robeName, $base, $lengths, $hasCollar] = $row;
            $sleeves = $row[4] ?? array_keys(static::SLEEVES);
            [$extraParam, $extraOptions] = $row[5];

            foreach ($lengths as $index => $cm) {
                $lengthKey = static::LENGTH_KEYS[$index];
                $lengthName = static::LENGTH_NAMES[$index];

                foreach ($sleeves as $sleeve) {
                    [$sleeveName, $style, $sleeveLength] = static::SLEEVES[$sleeve];

                    foreach (static::FITS as $fit => $fitName) {
                        foreach ($hasCollar ? array_keys(static::COLLARS) : [null] as $collar) {
                            $key = 'modest_'.$robe.'_'.$lengthKey.'_'.$sleeve.'_'.$fit
                                .($collar === null ? '' : '_'.$collar);

                            $params = [
                                'length' => (float) $cm,
                                'sleeve_style' => $style,
                                'sleeve_length' => $sleeveLength,
                                'fit' => $fit,
                            ];

                            $title = $robeName.' '.$lengthName.'، '.$sleeveName.'، فرم '.$fitName;

                            if ($collar !== null) {
                                $params['collar'] = static::COLLARS[$collar][1];
                                $title .= '، '.static::COLLARS[$collar][0];
                            }

                            foreach ($extraOptions as $extra => [$extraName, $extraValue]) {
                                $rows[$key.'_'.$extra] = [
                                    'title' => $title.'، '.$extraName,
                                    'base' => $base,
                                    'params' => array_merge($params, [$extraParam => $extraValue]),
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $rows;
    }
}
