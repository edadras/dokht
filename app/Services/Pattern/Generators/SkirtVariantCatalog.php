<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ دامن.
 *
 * دامن دو چیزِ روشن دارد که خریدار با همان دو انتخابش می‌کند: چه فرمی، و تا
 * کجا. جدول همان دو را در هم ضرب می‌کند و روی درفت‌های آزمودهٔ کاتالوگ سوار
 * می‌شود.
 *
 * قدها همه از خطِ کمر شمرده می‌شوند و هر فرم فقط قدهایی را می‌گیرد که رویش
 * معنا دارند: دامنِ ماهی کوتاه نمی‌شود (فرمش از زانو به پایین است) و دامنِ
 * توتو بلند نمی‌شود.
 */
class SkirtVariantCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'skirt';
    }

    /**
     * فرم‌ها: کلید ⇒ [نام، درفتِ پایه، نامِ پارامترِ قد، قدهای پذیرفته، نسبت‌ها، پرداخت‌های کمر].
     *
     * «پرداخت‌های کمر» فقط برای دامن‌هایی نوشته می‌شود که کمرشان انتخابی نیست:
     * دامنِ کمر کشی و کمرچین هر دو ساختِ کمرشان بخشی از هویتشان است و درفتشان
     * اصلاً پارامترِ کمربند ندارد. فهرستِ خالی یعنی «این محور را نچرخان».
     *
     * «نسبت‌ها» پارامترهایی‌اند که باید با قد بالا و پایین بروند، نه ثابت
     * بمانند. دامنِ ماهی این را لازم کرد: شروعِ کلوشش عددی ثابت از کمر است و اگر
     * دامن کوتاه شود ولی آن عدد نه، تمامِ کلوش در ده سانتی‌متر جمع می‌شود و مسیرِ
     * قطعه خودش را قطع می‌کند.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: array<int, string>, 4?: array<string, float>, 5?: array<int, string>}>
     */
    protected const SHAPES = [
        'aline' => ['خط A', 'skirt_a_line', 'length', ['mini', 'short', 'knee', 'midi', 'maxi']],
        'straight' => ['راسته', 'skirt_straight', 'length', ['mini', 'short', 'knee', 'midi', 'maxi']],
        'pencil' => ['مدادی', 'skirt_pencil', 'length', ['knee', 'midi']],
        'gathered' => ['چین‌دار', 'skirt_gathered', 'length', ['mini', 'short', 'knee', 'midi', 'maxi']],
        'circle_full' => ['کلوش کامل', 'skirt_circle_full', 'length', ['short', 'knee', 'midi', 'maxi']],
        'circle_half' => ['نیم‌کلوش', 'skirt_circle_half', 'length', ['short', 'knee', 'midi', 'maxi']],
        'circle_quarter' => ['ربع‌کلوش', 'skirt_circle_quarter', 'length', ['short', 'knee', 'midi', 'maxi']],
        'knife' => ['پیلی ریز', 'skirt_pleat_knife', 'length', ['mini', 'short', 'knee', 'midi']],
        'box' => ['پیلی پهن', 'skirt_pleat_box', 'length', ['mini', 'short', 'knee', 'midi']],
        'inverted' => ['پیلی برگردان', 'skirt_pleat_inverted', 'length', ['short', 'knee', 'midi']],
        'accordion' => ['پیلی آکاردئونی', 'skirt_pleat_accordion', 'length', ['knee', 'midi', 'maxi']],
        'sunburst' => ['پیلی آفتابی', 'skirt_pleat_sunburst', 'length', ['knee', 'midi', 'maxi']],
        'gored' => ['ترک‌دار', 'skirt_gored', 'length', ['knee', 'midi', 'maxi']],
        'godet' => ['گوده‌دار', 'skirt_godet', 'length', ['knee', 'midi', 'maxi']],
        'trumpet' => ['شیپوری', 'skirt_trumpet', 'length', ['midi', 'maxi']],
        'mermaid' => ['ماهی', 'skirt_mermaid', 'length', ['midi', 'maxi'], ['flare_start' => 0.68]],
        'tiered' => ['طبقه‌ای', 'skirt_tiered', 'length', ['knee', 'midi', 'maxi']],
        'wrap' => ['پاکتی', 'skirt_wrap', 'length', ['short', 'knee', 'midi', 'maxi']],
        'yoke' => ['یوک‌دار', 'skirt_yoke', 'length', ['mini', 'short', 'knee', 'midi']],
        'paperbag' => ['کمرچین (پیپربگ)', 'skirt_paperbag', 'length', ['short', 'knee', 'midi'], [], []],
        'elastic' => ['کمر کشی', 'skirt_elastic_waist', 'length', ['short', 'knee', 'midi', 'maxi'], [], []],
        'bubble' => ['بادکنکی', 'skirt_bubble', 'length', ['mini', 'short', 'knee']],
        'tulip' => ['لاله‌ای', 'skirt_tulip', 'length', ['short', 'knee']],
        'asymmetric' => ['نامتقارن', 'skirt_asymmetric', 'length', ['knee', 'midi', 'maxi']],
        'handkerchief' => ['دستمالی', 'skirt_handkerchief', 'length', ['knee', 'midi']],
        'cargo' => ['کارگو', 'skirt_cargo', 'length', ['mini', 'short', 'knee', 'midi']],
        'skort' => ['اسکورت', 'skirt_skort', 'length', ['mini', 'short', 'knee']],
        'peplum' => ['پپلوم', 'skirt_peplum', 'length', ['mini', 'short']],
    ];

    /**
     * قدها: کلید ⇒ [نام، سانتی‌متر از خط کمر].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const LENGTHS = [
        'mini' => ['خیلی کوتاه', 34.0],
        'short' => ['کوتاه', 44.0],
        'knee' => ['تا زانو', 58.0],
        'midi' => ['میدی', 76.0],
        'maxi' => ['ماکسی', 98.0],
    ];

    /**
     * پرداختِ خطِ کمر: کلید ⇒ [نام، کمربند دارد یا نه].
     *
     * این برچسب نیست، یک قطعهٔ کم و زیاد است: دامنِ کمربنددار یک نوارِ جدا دارد
     * و دامنِ بی‌کمربند به‌جایش سجافِ هم‌شکلِ خطِ کمر می‌خواهد. برندها هر دو را
     * می‌فروشند و روی الگو دو چیز متفاوت‌اند.
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    protected const WAISTS = [
        'band' => ['کمربنددار', true],
        'faced' => ['بی‌کمربند (سجاف)', false],
    ];

    /**
     * جای زیپ: کلید ⇒ [نام، مقدارِ پارامتر].
     *
     * زیپِ پهلو و زیپِ مرکزِ پشت دو الگوی متفاوت‌اند، نه یک الگو با دو نشانه:
     * زیپِ پشت درزِ مرکزِ پشت را باز می‌کند، پس پنلِ پشت دیگر روی تای پارچه بریده
     * نمی‌شود و دو نیمه می‌گیرد.
     *
     * گزینهٔ «بی‌زیپ» این‌جا نیست چون روی الگو با زیپِ پهلو یکی درمی‌آید: هر دو
     * پنلِ پشت را یک‌تکه می‌گذارند و فقط در نشانه فرق دارند. سرِ جایش در فرمِ
     * مدل هست.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const ZIPS = [
        'side' => ['زیپ پهلو', 'side'],
        'back' => ['زیپ پشت', 'back'],
    ];

    /**
     * دامن‌هایی که محورِ جای زیپ رویشان نمی‌چرخد.
     *
     * دستهٔ اول کمرشان کشی یا چین است و اصلاً زیپ نمی‌گیرند. دامنِ نامتقارن
     * دلیلِ دیگری دارد: پنلِ پشتش از اول کامل بریده می‌شود، نه نیم‌پنلِ روی تا،
     * پس درزِ مرکزِ پشتی وجود ندارد که زیپ در آن بنشیند و زیپش همیشه پهلوست.
     *
     * @var array<int, string>
     */
    protected const NO_ZIP = ['tiered', 'paperbag', 'elastic', 'skort', 'peplum', 'asymmetric'];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::SHAPES as $shape => $row) {
            [$shapeName, $base, $param, $lengths] = $row;
            $scaled = $row[4] ?? [];
            $waists = $row[5] ?? array_keys(static::WAISTS);

            foreach ($lengths as $length) {
                [$lengthName, $cm] = static::LENGTHS[$length];
                $params = [$param => $cm];

                foreach ($scaled as $name => $ratio) {
                    $params[$name] = round($cm * $ratio);
                }

                if ($waists === []) {
                    $rows['skirt_'.$shape.'_'.$length] = [
                        'title' => 'دامن '.$shapeName.' '.$lengthName,
                        'base' => $base,
                        'params' => $params,
                    ];

                    continue;
                }

                $zips = in_array($shape, static::NO_ZIP, true)
                    ? ['keep' => ['', null]]
                    : static::ZIPS;

                foreach ($waists as $waist) {
                    [$waistName, $hasBand] = static::WAISTS[$waist];

                    foreach ($zips as $zip => [$zipName, $zipValue]) {
                        $row = array_merge($params, ['waistband' => $hasBand]);

                        if ($zipValue !== null) {
                            $row['zip'] = $zipValue;
                        }

                        $rows['skirt_'.$shape.'_'.$length.'_'.$waist.($zipValue === null ? '' : '_'.$zip)] = [
                            'title' => 'دامن '.$shapeName.' '.$lengthName.'، '.$waistName
                                .($zipName === '' ? '' : '، '.$zipName),
                            'base' => $base,
                            'params' => $row,
                        ];
                    }
                }
            }
        }

        return $rows;
    }
}
