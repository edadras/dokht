<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ شلوار و شلوارک.
 *
 * شلوار سه انتخاب دارد که هر سه در الگو دیده می‌شوند: فرمِ پاچه، بلندیِ فاق، و
 * قد. جدول همان سه را در هم ضرب می‌کند.
 *
 * فاق را با پارامترِ خودِ درفت عوض می‌کنیم (low/mid/high)، نه با دست‌کاریِ عدد —
 * چون بالا و پایین بردنِ خطِ کمر منحنیِ فاق را هم عوض می‌کند و درفت این را
 * خودش می‌داند. قد با «تغییر قد پا» گرفته می‌شود که همان چیزی است که خیاط
 * می‌بُرد: نسبت به قدِ داخلِ پای اندازه‌گرفته‌شده.
 */
class PantsVariantCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'pants';
    }

    /**
     * فرم‌ها: کلید ⇒ [نام، درفتِ پایه، فاق‌های پذیرفته، قدهای پذیرفته، پرداخت‌های کمر].
     *
     * ستونِ آخر برای دو مدلی است که کمرشان قابلِ انتخاب نیست: شلوارِ کمر کشی و
     * ساق‌شلواری هر دو روی کشِ دورِ کمر بسته می‌شوند و اصلاً بستِ دیگری ندارند.
     * دادنِ محورِ «کمربنددار / بی‌کمربند» به آن‌ها فقط دو نامِ متفاوت روی یک الگو
     * می‌ساخت.
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<int, string>, 4?: array<int, string>}>
     */
    protected const SHAPES = [
        'skinny' => ['جذب', 'pants_skinny', ['mid', 'high'], ['ankle', 'full']],
        'cigarette' => ['سیگاری', 'pants_cigarette', ['mid', 'high'], ['crop', 'ankle', 'full']],
        'tapered' => ['باریک‌شونده', 'pants_tapered', ['low', 'mid', 'high'], ['ankle', 'full']],
        'chino' => ['چینو', 'pants_chino', ['mid', 'high'], ['ankle', 'full']],
        'bootcut' => ['دم‌گشاد کوتاه', 'pants_bootcut', ['mid', 'high'], ['full', 'long']],
        'flare' => ['دم‌گشاد', 'pants_flare', ['mid', 'high'], ['full', 'long']],
        'palazzo' => ['پالازو', 'pants_palazzo', ['high'], ['ankle', 'full', 'long']],
        'culottes' => ['کولوت', 'pants_culottes', ['mid', 'high'], ['crop', 'ankle']],
        'pleated' => ['پیلی‌دار', 'pants_pleated', ['mid', 'high'], ['ankle', 'full']],
        'cargo' => ['کارگو', 'pants_cargo', ['low', 'mid'], ['ankle', 'full']],
        'jogger' => ['جاگر', 'pants_jogger', ['mid'], ['ankle', 'full'], []],
        'harem' => ['هارمی', 'pants_harem', ['mid', 'high'], ['ankle', 'full'], []],
        'jodhpur' => ['سوارکاری', 'pants_jodhpur', ['high'], ['full']],
        'paperbag' => ['کمرچین', 'pants_paperbag', ['high'], ['ankle', 'full'], []],
        'elastic' => ['کمر کشی', 'pants_elastic_waist', ['mid', 'high'], ['ankle', 'full'], []],
        'leggings' => ['ساق‌شلواری', 'leggings', ['mid', 'high'], ['ankle', 'full'], []],
    ];

    /**
     * فاق: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const RISES = [
        'low' => 'فاق کوتاه',
        'mid' => 'فاق متوسط',
        'high' => 'فاق بلند',
    ];

    /**
     * قد: کلید ⇒ [نام، تغییر نسبت به قد داخل پا].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const LENGTHS = [
        'crop' => ['کراپ', -22.0],
        'ankle' => ['تا قوزک', -8.0],
        'full' => ['تمام‌قد', 0.0],
        'long' => ['بلند', 6.0],
    ];

    /**
     * شلوارک‌ها: کلید ⇒ [نام، درفتِ پایه، قدِ پا از خط فاق].
     *
     * @var array<string, array{0: string, 1: string, 2: array<string, float>}>
     */
    protected const SHORTS = [
        'bermuda' => ['برمودا', 'shorts_bermuda', ['micro' => 14.0, 'short' => 22.0, 'knee' => 32.0]],
        'short' => ['کوتاه', 'shorts_short', ['micro' => 10.0, 'short' => 16.0, 'knee' => 24.0]],
        'paperbag' => ['کمرچین', 'shorts_paperbag', ['micro' => 14.0, 'short' => 20.0, 'knee' => 30.0]],
        'cycling' => ['دوچرخه‌سواری', 'shorts_cycling', ['micro' => 14.0, 'short' => 22.0, 'knee' => 30.0]],
    ];

    /** نامِ فارسیِ قدِ شلوارک. */
    protected const SHORT_LENGTHS = [
        'micro' => 'خیلی کوتاه',
        'short' => 'کوتاه',
        'knee' => 'تا زانو',
    ];

    /**
     * پرداختِ خطِ کمر: کلید ⇒ [نام، کمربند دارد یا نه].
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    protected const WAISTS = [
        'band' => ['کمربنددار', true],
        'faced' => ['بی‌کمربند (سجاف)', false],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::SHAPES as $shape => $row) {
            [$shapeName, $base, $rises, $lengths] = $row;
            $waists = $row[4] ?? array_keys(static::WAISTS);

            foreach ($rises as $rise) {
                foreach ($lengths as $length) {
                    [$lengthName, $change] = static::LENGTHS[$length];

                    if ($waists === []) {
                        $rows['pants_'.$shape.'_'.$rise.'_'.$length] = [
                            'title' => 'شلوار '.$shapeName.'، '.static::RISES[$rise].'، '.$lengthName,
                            'base' => $base,
                            'params' => ['rise' => $rise, 'length_extra' => $change],
                        ];

                        continue;
                    }

                    foreach ($waists as $waist) {
                        [$waistName, $hasBand] = static::WAISTS[$waist];

                        $rows['pants_'.$shape.'_'.$rise.'_'.$length.'_'.$waist] = [
                            'title' => 'شلوار '.$shapeName.'، '.static::RISES[$rise].'، '.$lengthName.'، '.$waistName,
                            'base' => $base,
                            'params' => ['rise' => $rise, 'length_extra' => $change, 'waistband' => $hasBand],
                        ];
                    }
                }
            }
        }

        foreach (static::SHORTS as $shape => [$shapeName, $base, $lengths]) {
            foreach ($lengths as $length => $cm) {
                foreach (['mid', 'high'] as $rise) {
                    $rows['shorts_'.$shape.'_'.$rise.'_'.$length] = [
                        'title' => 'شلوارک '.$shapeName.'، '.static::RISES[$rise].'، '.static::SHORT_LENGTHS[$length],
                        'base' => $base,
                        'params' => ['rise' => $rise, 'leg_length' => $cm],
                    ];
                }
            }
        }

        return $rows;
    }
}
