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
 * ولی این فقط برای مایوی کشی درست است. شلوارکِ ساحلی و برکینی از پارچهٔ بافته
 * دوخته می‌شوند و درفتشان اصلاً ضریبِ کشش را به کار نمی‌بَرد؛ جامر و شورتِ
 * مردانه هم آسترِ تکه‌ای ندارند. اگر همان دو محورِ مشترک را به همه بدهیم، برای
 * این چهار مدل ردیف‌هایی می‌سازیم که همه یک الگو هستند با نام‌های متفاوت — و
 * دقیقاً همین شد و بیست‌وهشت ردیفِ تکراری درآمد.
 *
 * پس هر مدل محورهای *خودش* را دارد: آن‌که کشی است پارچه و آستر می‌گیرد، آن‌که
 * بافته است قد و چاک و بستِ خودش را.
 */
class SwimRangeCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'swim';
    }

    /**
     * مایوها: کلید ⇒ [نام، درفتِ پایه، محورها].
     *
     * هر محور: نامِ پارامتر ⇒ [کلیدِ گزینه ⇒ [نامِ فارسی، مقدار]].
     *
     * @var array<string, array{0: string, 1: string, 2: array<string, array<string, array{0: string, 1: mixed}>>}>
     */
    protected const SWIMS = [
        // ── مایوی کشی: پارچه، آسترکشی و (اگر شورت دارد) پوشش ──────────────
        'onepiece' => ['یک‌تکه', 'swim_onepiece', ['stretch' => self::MEDIUM, 'lining' => self::LININGS]],
        'onepiece_high' => ['یک‌تکه یقه‌بسته', 'swim_onepiece_high_neck', ['stretch' => self::MEDIUM, 'lining' => self::LININGS]],
        'onepiece_deep' => ['یک‌تکه پشت‌باز', 'swim_onepiece_deep_back', ['stretch' => self::MEDIUM, 'lining' => self::LININGS]],
        'skirted' => ['دامن‌دار', 'swim_skirted', ['stretch' => self::MEDIUM, 'lining' => self::LININGS, 'skirt_length' => self::SKIRT]],
        'skirted_long' => ['دامن‌دار بلند', 'swim_skirted_long', ['stretch' => self::MEDIUM, 'lining' => self::LININGS, 'skirt_length' => self::SKIRT]],
        'racerback' => ['ریسربک', 'swim_racerback', ['stretch' => self::SPORTY, 'lining' => self::LININGS]],
        'tankini' => ['تانکینی', 'swim_tankini', ['stretch' => self::MEDIUM, 'lining' => self::LININGS, 'coverage' => self::COVERAGES]],
        'bikini_sport' => ['بیکینی ورزشی', 'swim_bikini_sport', ['stretch' => self::MEDIUM, 'lining' => self::LININGS, 'coverage' => self::COVERAGES]],
        'bikini_triangle' => ['بیکینی مثلثی', 'swim_bikini_triangle', ['stretch' => self::MEDIUM, 'lining' => self::LININGS, 'coverage' => self::COVERAGES]],
        'bikini_high' => ['بیکینی کمربلند', 'swim_bikini_high_waist', ['stretch' => self::MEDIUM, 'lining' => self::LININGS, 'coverage' => self::COVERAGES]],
        'bikini_bandeau' => ['بیکینی بندو', 'swim_bikini_bandeau', ['stretch' => self::SPORTY, 'lining' => self::LININGS, 'coverage' => self::COVERAGES]],
        'briefs' => ['شورت مایو', 'swim_briefs', ['stretch' => self::MEDIUM, 'lining' => self::LININGS, 'coverage' => self::COVERAGES]],
        'sport_bra' => ['نیم‌تنه ورزشی', 'swim_sport_bra', ['stretch' => self::SPORTY, 'lining' => self::LININGS, 'top_height' => self::BRA_HEIGHT]],

        // ── بافته یا یک‌لایه: محورهای خودشان ──────────────────────────────
        'jammer' => ['جامر', 'swim_jammer', ['stretch' => self::RACING, 'leg_length' => self::JAMMER_LEG]],
        'trunks' => ['شورت مردانه', 'swim_trunks', ['stretch' => self::MEDIUM, 'leg_length' => self::TRUNK_LEG, 'back_pocket' => self::POCKET]],
        // شلوارکِ ساحلی بافته است: نه ضریبِ کشش رویش اثر دارد، نه آستر، نه چاک
        // و بستِ کمر — درفتش هیچ‌کدام را در قطعه نمی‌آورد. فقط قد و آزادیِ کمر
        'boardshorts' => ['شلوارک ساحلی', 'swim_boardshorts', ['leg_length' => self::BOARD_LEG, 'waist_ease' => self::BOARD_WAIST]],
        'burkini' => ['برکینی', 'swim_burkini', ['tunic_length' => self::TUNIC, 'sleeve_length' => self::SLEEVE, 'hood' => self::HOOD]],
    ];

    /** پارچهٔ مایوی معمولی (پایه ۰٫۸۵). */
    protected const MEDIUM = [
        'power' => ['پارچه پرکشش', 0.78],
        'standard' => ['پارچه معمولی مایو', 0.85],
        'stable' => ['پارچه کم‌کشش', 0.92],
    ];

    /** پارچهٔ مدل‌های ورزشی‌تر (پایه ۰٫۷۸ تا ۰٫۸۰). */
    protected const SPORTY = [
        'power' => ['پارچه پرکشش', 0.72],
        'standard' => ['پارچه معمولی مایو', 0.80],
        'stable' => ['پارچه کم‌کشش', 0.88],
    ];

    /** پارچهٔ مایوی مسابقه‌ای (پایه ۰٫۷۲). */
    protected const RACING = [
        'power' => ['پارچه مسابقه‌ای', 0.66],
        'standard' => ['پارچه تمرین', 0.72],
        'stable' => ['پارچه معمولی', 0.80],
    ];

    /** آسترکشی: سه چیزِ متفاوت روی میز برش، نه سه برچسب. */
    protected const LININGS = [
        'full' => ['آستر کامل', 'full'],
        'front' => ['آستر جلو', 'front'],
        'gusset' => ['فقط لایه فاق', 'gusset'],
    ];

    /** پوشش: خطِ پا را جابه‌جا می‌کند و شکلِ پشتِ شورت را عوض. */
    protected const COVERAGES = [
        'full' => ['پوشش کامل', 'full'],
        'medium' => ['پوشش متوسط', 'medium'],
        'cheeky' => ['پوشش کم', 'cheeky'],
    ];

    /** قدِ دامنِ مایوی دامن‌دار. */
    protected const SKIRT = [
        'short' => ['دامن کوتاه', 14.0],
        'classic' => ['دامن معمولی', 20.0],
        'long' => ['دامن بلند', 28.0],
    ];

    /** بلندیِ نیم‌تنهٔ ورزشی. */
    protected const BRA_HEIGHT = [
        'short' => ['نیم‌تنه کوتاه', 15.0],
        'classic' => ['نیم‌تنه معمولی', 20.0],
        'long' => ['نیم‌تنه بلند', 26.0],
    ];

    /**
     * قدِ پاچهٔ جامر.
     *
     * کوتاه‌ترینش تا میانِ ران است، نه بالاتر: جامر روی تنِ بلند با پاچهٔ
     * کوتاه‌تر، قطعه‌ای می‌دهد که در بازهٔ مساحتِ «پاچه» نمی‌گنجد و دیگر جامر
     * نیست، شورت است — و شورت جای خودش را در همین فهرست دارد.
     */
    protected const JAMMER_LEG = [
        'short' => ['تا میان ران', 27.0],
        'classic' => ['تا بالای زانو', 34.0],
        'long' => ['تا زانو', 42.0],
    ];

    /** قدِ پاچهٔ شورتِ مردانه. */
    protected const TRUNK_LEG = [
        'short' => ['پاچه کوتاه', 13.0],
        'classic' => ['پاچه معمولی', 18.0],
        'long' => ['پاچه بلند', 25.0],
    ];

    /** جیبِ پشتِ شورتِ مردانه: یک قطعهٔ اضافه. */
    protected const POCKET = [
        'plain' => ['بی‌جیب', false],
        'pocket' => ['جیب پشت', true],
    ];

    /** قدِ پاچهٔ شلوارکِ ساحلی. */
    protected const BOARD_LEG = [
        'short' => ['تا میان ران', 26.0],
        'classic' => ['تا بالای زانو', 32.0],
        'long' => ['تا زیر زانو', 42.0],
    ];

    /** آزادیِ کمرِ شلوارکِ ساحلی. */
    protected const BOARD_WAIST = [
        'snug' => ['کمر جمع', 3.0],
        'classic' => ['کمر معمولی', 6.0],
        'relaxed' => ['کمر راحت', 11.0],
    ];

    /** قدِ تونیکِ برکینی از خط کمر. */
    protected const TUNIC = [
        'short' => ['تونیک کوتاه', 30.0],
        'classic' => ['تونیک معمولی', 38.0],
        'long' => ['تونیک بلند', 48.0],
    ];

    /** آستینِ برکینی. */
    protected const SLEEVE = [
        'elbow' => ['آستین تا آرنج', 34.0],
        'long' => ['آستین بلند', 54.0],
    ];

    /** کلاهِ برکینی: یک قطعهٔ کامل، نه یک گزینهٔ ظاهری. */
    protected const HOOD = [
        'hooded' => ['کلاه‌دار', true],
        'plain' => ['بی‌کلاه', false],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::SWIMS as $swim => [$swimName, $base, $axes]) {
            foreach (static::combine($axes) as [$suffix, $names, $params]) {
                $rows['swim_range_'.$swim.$suffix] = [
                    'title' => 'مایو '.$swimName.'، '.implode('، ', $names),
                    'base' => $base,
                    'params' => $params,
                ];
            }
        }

        return $rows;
    }
}
