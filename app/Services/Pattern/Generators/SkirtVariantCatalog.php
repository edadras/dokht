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
     * فرم‌ها: کلید ⇒ [نام، درفتِ پایه، نامِ پارامترِ قد، قدهای پذیرفته، نسبت‌ها].
     *
     * «نسبت‌ها» پارامترهایی‌اند که باید با قد بالا و پایین بروند، نه ثابت
     * بمانند. دامنِ ماهی این را لازم کرد: شروعِ کلوشش عددی ثابت از کمر است و اگر
     * دامن کوتاه شود ولی آن عدد نه، تمامِ کلوش در ده سانتی‌متر جمع می‌شود و مسیرِ
     * قطعه خودش را قطع می‌کند.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: array<int, string>, 4?: array<string, float>}>
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
        'paperbag' => ['کمرچین (پیپربگ)', 'skirt_paperbag', 'length', ['short', 'knee', 'midi']],
        'elastic' => ['کمر کشی', 'skirt_elastic_waist', 'length', ['short', 'knee', 'midi', 'maxi']],
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

            foreach ($lengths as $length) {
                [$lengthName, $cm] = static::LENGTHS[$length];
                $params = [$param => $cm];

                foreach ($scaled as $name => $ratio) {
                    $params[$name] = round($cm * $ratio);
                }

                $rows['skirt_'.$shape.'_'.$length] = [
                    'title' => 'دامن '.$shapeName.' '.$lengthName,
                    'base' => $base,
                    'params' => $params,
                ];
            }
        }

        return $rows;
    }
}
