<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ لباس زیر.
 *
 * لباس زیر جایی است که «یک مدل» به‌تنهایی معنا ندارد. برندهای زیرپوش هر مدل را
 * در چند پوشش و چند پارچه می‌سازند، چون خریدار دقیقاً همان را انتخاب می‌کند:
 * شورتِ بیکینی در پوششِ کامل و کم دو محصولِ جداست و روی میزِ برش هم دو الگو.
 *
 * محورها برای هر دسته جداست، چون ساختشان یکی نیست:
 *
 * — شورت: پوشش (خطِ پا و پهنای پشت)، پارچه، و پهنای لایهٔ فاق.
 * — سوتین: نسبتِ کاپ، بلندیِ بند زیرِ سینه و پهنای بند دوش.
 * — باکسر: قدِ پاچه و پارچه.
 * — زیرپیراهن: قد و خطِ بالا.
 *
 * «پارچه» همان ضریبِ کشسانی است و مستقیم در ابعادِ هر قطعه ضرب می‌شود: لباس
 * زیر با آزادیِ منفی بریده می‌شود و پارچهٔ پرکشش الگوی کوچک‌تری می‌خواهد.
 */
class UnderwearRangeCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'underwear';
    }

    /**
     * مدل‌ها: کلید ⇒ [نام، درفتِ پایه، محورها].
     *
     * هر محور: نامِ پارامتر ⇒ (کلیدِ گزینه ⇒ [نامِ فارسی، مقدار]).
     *
     * @var array<string, array{0: string, 1: string, 2: array<string, array<string, array{0: string, 1: mixed}>>}>
     */
    protected const PIECES = [
        // ── شورت ───────────────────────────────────────────────────────────
        'brief' => ['شورت کلاسیک', 'panty_brief', ['coverage' => self::COVERAGES, 'stretch' => self::KNIT, 'gusset' => self::GUSSETS]],
        'bikini' => ['شورت بیکینی', 'panty_bikini', ['coverage' => self::COVERAGES, 'stretch' => self::KNIT, 'gusset' => self::GUSSETS]],
        'hipster' => ['شورت هیپستر', 'panty_hipster', ['coverage' => self::COVERAGES, 'stretch' => self::KNIT, 'gusset' => self::GUSSETS]],
        'boyshort' => ['شورت پاکوتاه', 'panty_boyshort', ['coverage' => self::COVERAGES, 'stretch' => self::KNIT, 'gusset' => self::GUSSETS]],
        'high_waist' => ['شورت کمربلند', 'panty_high_waist', ['coverage' => self::COVERAGES, 'stretch' => self::KNIT, 'gusset' => self::GUSSETS]],
        'thong' => ['شورت نخی باریک', 'panty_thong', ['coverage' => self::COVERAGES, 'stretch' => self::KNIT]],

        // ── سوتین ──────────────────────────────────────────────────────────
        'bra_soft' => ['سوتین نرم', 'bra_soft', ['cup_ratio' => self::CUPS, 'band_height' => self::BANDS, 'strap_width' => self::STRAPS]],
        'bra_full' => ['سوتین فول‌کاپ', 'bra_full_cup', ['cup_ratio' => self::CUPS, 'band_height' => self::BANDS, 'strap_width' => self::STRAPS]],
        /*
         * سوتینِ تی‌شرتی محورِ سومش «آسترِ کاپ» است، نه پهنای بند.
         *
         * این درفت روی سوتینِ نرم سوار است و تنها با سه عدد از آن جدا می‌شود:
         * نسبتِ کاپ، بلندیِ بند زیرسینه و پهنای بندِ دوش. اگر همان سه را محور
         * کنیم، هر ردیفش مو به مو ردیفِ سوتینِ نرم می‌شود. آنچه واقعاً تی‌شرتی
         * بودن را می‌سازد لایهٔ کاپ است — همان که کاپ را صاف و بی‌درز نگه
         * می‌دارد — و آن یک قطعهٔ کاملِ دیگر است.
         */
        'bra_tshirt' => ['سوتین تی‌شرتی', 'bra_tshirt', ['cup_ratio' => self::CUPS, 'band_height' => self::BANDS, 'cup_lining' => self::CUP_LININGS]],
        'bra_strapless' => ['سوتین بی‌بند', 'bra_strapless', ['cup_ratio' => self::CUPS, 'band_height' => self::BANDS, 'hook_rows' => self::HOOKS]],
        'bra_push' => ['سوتین پوش‌آپ', 'bra_push_up', ['cup_ratio' => self::CUPS, 'band_height' => self::BANDS, 'pad' => self::PADS]],
        'bralette' => ['برالت', 'bralette', ['band_height' => self::BANDS, 'back_drop' => self::BACKS, 'front_lining' => self::LININGS]],

        // ── باکسر ──────────────────────────────────────────────────────────
        'boxer_brief' => ['باکسر جذب', 'boxer_brief', ['leg_length' => self::LEGS, 'stretch' => self::KNIT, 'waistband_height' => self::WAISTS]],
        'boxer_loose' => ['باکسر گشاد', 'boxer_loose', ['leg_length' => self::LEGS, 'stretch' => self::KNIT, 'waistband_height' => self::WAISTS]],

        // ── زیرپیراهن ──────────────────────────────────────────────────────
        'slip' => ['زیرپیراهن', 'slip_full', ['length' => self::SLIP_LENGTHS, 'top_shape' => self::TOP_LINES, 'hem_flare' => self::FLARES]],
    ];

    /** پوشش: خطِ پا و پهنای پشتِ شورت را جابه‌جا می‌کند. */
    protected const COVERAGES = [
        'full' => ['پوشش کامل', 'full'],
        'medium' => ['پوشش متوسط', 'medium'],
        'cheeky' => ['پوشش کم', 'cheeky'],
    ];

    /** پارچه: ضریبِ کشسانی، که مستقیم در ابعادِ هر قطعه ضرب می‌شود. */
    protected const KNIT = [
        'power' => ['پارچه پرکشش', 0.78],
        'standard' => ['پارچه معمولی', 0.86],
        'stable' => ['پارچه کم‌کشش', 0.94],
    ];

    /** پهنای لایهٔ فاق. */
    protected const GUSSETS = [
        'narrow' => ['لایه فاق باریک', 6.5],
        'wide' => ['لایه فاق پهن', 10.0],
    ];

    /** نسبتِ کاپ به دورِ سینه. */
    protected const CUPS = [
        'shallow' => ['کاپ کم‌عمق', 0.19],
        'classic' => ['کاپ معمولی', 0.23],
        'deep' => ['کاپ عمیق', 0.27],
    ];

    /** بلندیِ بندِ زیرِ سینه. */
    protected const BANDS = [
        'narrow' => ['بند زیرسینه باریک', 3.0],
        'wide' => ['بند زیرسینه پهن', 5.5],
    ];

    /** پهنای بندِ دوش. */
    protected const STRAPS = [
        'thin' => ['بند باریک', 1.0],
        'wide' => ['بند پهن', 2.2],
    ];

    /** آسترِ کاپ: لایه‌ای که کاپ را صاف و بی‌درز نگه می‌دارد. */
    protected const CUP_LININGS = [
        'lined' => ['کاپ آستردار', true],
        'plain' => ['کاپ تک‌لایه', false],
    ];

    /** ردیفِ قزنِ پشت. */
    protected const HOOKS = [
        'two' => ['دو ردیف قزن', 2],
        'three' => ['سه ردیف قزن', 3],
    ];

    /** لایهٔ کاپ. */
    protected const PADS = [
        'padded' => ['کاپ لایه‌دار', true],
        'plain' => ['بی‌لایه', false],
    ];

    /**
     * گودیِ پشتِ برالت.
     *
     * بازترینش تا شانزده سانتی‌متر می‌رود، نه بیشتر: با بندِ زیرسینهٔ پهن روی
     * تنِ کوچک، پنلِ پشت از کفِ مساحتِ کاتالوگ پایین‌تر می‌رفت — نواری که
     * دوختنی نیست.
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const BACKS = [
        'high' => ['پشت بسته', 8.0],
        'classic' => ['پشت معمولی', 12.0],
        'low' => ['پشت باز', 15.5],
    ];

    /** آسترِ جلوی برالت: یک لایهٔ کاملِ دیگر روی قطعهٔ جلو. */
    protected const LININGS = [
        'lined' => ['جلو آستردار', true],
        'plain' => ['جلو تک‌لایه', false],
    ];

    /** قدِ پاچهٔ باکسر. */
    protected const LEGS = [
        'short' => ['پاچه کوتاه', 9.0],
        'classic' => ['پاچه معمولی', 14.0],
        'long' => ['پاچه بلند', 20.0],
    ];

    /** بلندیِ کشِ کمرِ باکسر. */
    protected const WAISTS = [
        'narrow' => ['کش باریک', 2.5],
        'wide' => ['کش پهن', 4.5],
    ];

    /** قدِ زیرپیراهن از خطِ کمر. */
    protected const SLIP_LENGTHS = [
        'short' => ['کوتاه', 24.0],
        'classic' => ['معمولی', 32.0],
        'long' => ['بلند', 44.0],
    ];

    /** خطِ بالای زیرپیراهن. */
    protected const TOP_LINES = [
        'straight' => ['خط بالای صاف', 'straight'],
        'sweetheart' => ['خط بالای قلبی', 'sweetheart'],
        'scoop' => ['خط بالای گرد', 'scoop'],
    ];

    /** بازشدنِ دمِ زیرپیراهن. */
    protected const FLARES = [
        'straight' => ['دم راسته', 2.0],
        'flared' => ['دم کلوش', 12.0],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::PIECES as $item => [$name, $base, $axes]) {
            foreach (static::combine($axes) as [$suffix, $names, $params]) {
                $rows['under_'.$item.$suffix] = [
                    'title' => $name.'، '.implode('، ', $names),
                    'base' => $base,
                    'params' => $params,
                ];
            }
        }

        return $rows;
    }
}
