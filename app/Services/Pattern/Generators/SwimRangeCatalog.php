<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ مایو.
 *
 * مایو تنها لباسی است که الگویش را *پارچه* تعیین می‌کند، نه فقط بدن. برندهای
 * بزرگ همان مایو را روی دو یا سه پارچه با کششِ متفاوت می‌بُرند و برای هر کدام
 * الگوی جدا نگه می‌دارند: با پارچهٔ پرکشش الگو کوچک‌تر بریده می‌شود و با پارچهٔ
 * کم‌کشش بزرگ‌تر، وگرنه یکی روی تن زخم می‌اندازد و آن یکی چروک می‌شود.
 *
 * سه محور، هر سه سازنده:
 *
 * — «پارچه» ضریبِ کشسانی است و مستقیم در ابعادِ همهٔ قطعه‌ها ضرب می‌شود.
 * — «آسترکشی» یک نسخهٔ کاملِ دیگر از قطعه‌هاست (آستر کامل)، یا فقط جلو، یا فقط
 *   لایهٔ فاق — سه چیزِ متفاوت روی میز برش.
 * — «پوشش» خطِ پا را جابه‌جا می‌کند و شکلِ پشتِ شورت را عوض می‌کند.
 */
class SwimRangeCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'swim';
    }

    /**
     * مایوها: کلید ⇒ [نام، درفتِ پایه، ضریبِ کششِ پایه، محورِ پوشش دارد یا نه].
     *
     * ضریبِ پایه از خودِ درفت می‌آید؛ سه پارچه از همان بالا و پایین می‌روند تا
     * هر مدل روی پارچهٔ خودش بماند: مایوی مسابقه‌ای از پیش پرکشش است و شلوارکِ
     * ساحلی تقریباً بی‌کشش.
     *
     * @var array<string, array{0: string, 1: string, 2: float, 3: bool}>
     */
    protected const SWIMS = [
        'onepiece' => ['یک‌تکه', 'swim_onepiece', 0.85, false],
        'onepiece_high' => ['یک‌تکه یقه‌بسته', 'swim_onepiece_high_neck', 0.85, false],
        'onepiece_deep' => ['یک‌تکه پشت‌باز', 'swim_onepiece_deep_back', 0.85, false],
        'skirted' => ['دامن‌دار', 'swim_skirted', 0.85, false],
        'skirted_long' => ['دامن‌دار بلند', 'swim_skirted_long', 0.85, false],
        'racerback' => ['ریسربک', 'swim_racerback', 0.78, false],
        'tankini' => ['تانکینی', 'swim_tankini', 0.85, true],
        'bikini_sport' => ['بیکینی ورزشی', 'swim_bikini_sport', 0.85, true],
        'bikini_triangle' => ['بیکینی مثلثی', 'swim_bikini_triangle', 0.85, true],
        'bikini_high' => ['بیکینی کمربلند', 'swim_bikini_high_waist', 0.85, true],
        'bikini_bandeau' => ['بیکینی بندو', 'swim_bikini_bandeau', 0.80, true],
        'briefs' => ['شورت مایو', 'swim_briefs', 0.85, true],
        'sport_bra' => ['نیم‌تنه ورزشی', 'swim_sport_bra', 0.80, false],
        'burkini' => ['برکینی', 'swim_burkini', 0.92, false],
        'jammer' => ['جامر', 'swim_jammer', 0.72, false],
        'trunks' => ['شورت مردانه', 'swim_trunks', 0.95, false],
        'boardshorts' => ['شلوارک ساحلی', 'swim_boardshorts', 0.98, false],
    ];

    /**
     * پارچه: کلید ⇒ [نام، تغییرِ ضریبِ کشش].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const FABRICS = [
        'power' => ['پارچه پرکشش', -0.07],
        'standard' => ['پارچه معمولی مایو', 0.0],
        'stable' => ['پارچه کم‌کشش', 0.07],
    ];

    /**
     * آسترکشی: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const LININGS = [
        'full' => 'آستر کامل',
        'front' => 'آستر جلو',
        'gusset' => 'فقط لایه فاق',
    ];

    /**
     * پوشش: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const COVERAGES = [
        'full' => 'پوشش کامل',
        'medium' => 'پوشش متوسط',
        'cheeky' => 'پوشش کم',
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::SWIMS as $swim => [$swimName, $base, $stretch, $hasCoverage]) {
            foreach (static::FABRICS as $fabric => [$fabricName, $delta]) {
                // ضریب همیشه در بازهٔ خودِ درفت می‌ماند؛ پارچهٔ کم‌کششِ شلوارک
                // ساحلی به یک می‌رسد و بالاتر نمی‌رود
                $ratio = round(min(1.0, max(0.62, $stretch + $delta)), 2);

                foreach (static::LININGS as $lining => $liningName) {
                    foreach ($hasCoverage ? array_keys(static::COVERAGES) : [null] as $coverage) {
                        $key = 'swim_range_'.$swim.'_'.$fabric.'_'.$lining
                            .($coverage === null ? '' : '_'.$coverage);

                        $params = ['stretch' => $ratio, 'lining' => $lining];
                        $title = 'مایو '.$swimName.'، '.$fabricName.'، '.$liningName;

                        if ($coverage !== null) {
                            $params['coverage'] = $coverage;
                            $title .= '، '.static::COVERAGES[$coverage];
                        }

                        $rows[$key] = ['title' => $title, 'base' => $base, 'params' => $params];
                    }
                }
            }
        }

        return $rows;
    }
}
