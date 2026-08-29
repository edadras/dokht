<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ کت‌وشلوار.
 *
 * کت‌وشلوار جایی است که برندهای بزرگ ریزترین تفاوت‌ها را محصولِ جدا می‌کنند، و
 * حق هم دارند: کتِ دو دکمهٔ آستردارِ تا باسن با کتِ یک دکمهٔ بی‌آسترِ کوتاه، دو
 * الگوی کاملاً متفاوت است — جای شکستِ یقه، تعدادِ جادکمه، و بود و نبودِ یک
 * نسخهٔ کاملِ دیگر از تنه و آستین.
 *
 * محورها:
 *
 * — «فرم» آزادیِ تنه را عوض می‌کند.
 * — «قد» خطِ پایینِ کت را می‌برد و با آن بلندیِ سجاف را.
 * — «دکمه» جای شکستِ یقه را بالا و پایین می‌برد، پس برگردانِ یقه هم عوض می‌شود.
 *   این را باید صریح نوشت، چون خودِ تعدادِ دکمه فقط جای جادکمه را عوض می‌کند و
 *   به الگو دست نمی‌زند: سه ردیفِ «یک دکمه»، «دو دکمه» و «سه دکمه» دقیقاً یک
 *   الگو می‌شدند با سه نام. آنچه واقعاً فرقشان است جای شکستِ یقه است — کتِ
 *   یک‌دکمه پایین می‌شکند و برگردانِ بلند دارد، سه‌دکمه بالا می‌شکند و برگردانِ
 *   کوتاه. پس هر تعدادِ دکمه با شکستِ خودش می‌آید.
 * — «آستر» یک دست قطعهٔ کاملِ دیگر است.
 *
 * شلوارِ کت‌وشلوار اسکلتِ دیگری دارد و محورهای خودش را می‌گیرد: فاق، قد، پیلیِ
 * جلو و دوبلهٔ پاچه.
 */
class SuitRangeCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'suit';
    }

    /**
     * کت‌ها: کلید ⇒ [نام، درفتِ پایه، سه قد، دکمه‌ها (تعداد ⇒ جای شکستِ یقه)].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, float>, 3: array<int, float>}>
     */
    protected const JACKETS = [
        'jacket' => ['کت رسمی', 'suit_jacket', [16, 24, 38], [1 => 10.0, 2 => 0.0, 3 => -8.0]],
        'jacket_men' => ['کت رسمی مردانه', 'mens_suit_jacket', [16, 24, 38], [1 => 10.0, 2 => 0.0, 3 => -8.0]],
        // تاکسیدو بیش از دو دکمه نمی‌گیرد؛ درفت هم همین را می‌گوید
        'tuxedo' => ['تاکسیدو', 'suit_tuxedo', [18, 26, 40], [1 => 8.0, 2 => -2.0]],
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

    /** نامِ سه قد، هم‌ترتیب با ستونِ قدها. */
    protected const LENGTH_NAMES = ['کوتاه', 'معمولی', 'بلند'];

    /** کلیدِ سه قد. */
    protected const LENGTH_KEYS = ['short', 'mid', 'long'];

    /**
     * آستر: کلید ⇒ [نام، دارد یا نه].
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    protected const LININGS = [
        'lined' => ['آستردار', true],
        'unlined' => ['بی‌آستر', false],
    ];

    /**
     * فاق: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const RISES = [
        'mid' => 'فاق متوسط',
        'high' => 'فاق بلند',
    ];

    /**
     * قدِ پاچه: کلید ⇒ [نام، تغییر نسبت به قد داخل پا].
     *
     * فاصلهٔ قدها عمداً هشت سانتی‌متر نیست: دوبلهٔ چهار سانتی‌متری هشت سانتی‌متر
     * از قدِ پاچه می‌خورد، و با فاصلهٔ هشت، «تا قوزکِ بی‌دوبله» دقیقاً همان
     * «کوتاهِ دوبله‌دار» می‌شد — دو نام روی یک الگو.
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const LEG_LENGTHS = [
        'crop' => ['کوتاه', -16.0],
        'ankle' => ['تا قوزک', -7.0],
        'full' => ['تمام‌قد', 0.0],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = array_merge(static::jackets(), static::waistcoats(), static::trousers());

        return $rows;
    }

    /** @return array<string, array<string, mixed>> */
    protected static function jackets(): array
    {
        $rows = [];

        foreach (static::JACKETS as $jacket => [$name, $base, $lengths, $buttons]) {
            foreach ($lengths as $index => $cm) {
                foreach ($buttons as $count => $break) {
                    foreach (static::FITS as $fit => $fitName) {
                        foreach (static::LININGS as $lining => [$liningName, $hasLining]) {
                            $rows['suit_set_'.$jacket.'_'.static::LENGTH_KEYS[$index].'_b'.$count.'_'.$fit.'_'.$lining] = [
                                'title' => $name.' '.static::LENGTH_NAMES[$index].'، '.$count.' دکمه، فرم '
                                    .$fitName.'، '.$liningName,
                                'base' => $base,
                                'params' => [
                                    'length' => (float) $cm,
                                    'buttons' => $count,
                                    'lapel_break' => $break,
                                    'fit' => $fit,
                                    'lining' => $hasLining,
                                ],
                            ];
                        }
                    }
                }
            }
        }

        return $rows;
    }

    /** @return array<string, array<string, mixed>> */
    protected static function waistcoats(): array
    {
        $rows = [];

        // مثل کت: تعدادِ دکمه به‌تنهایی الگو را عوض نمی‌کند. آنچه عوض می‌شود
        // نوکِ پایینِ جلیقه است — هرچه دکمه‌ها پایین‌تر بروند نوک تیزتر می‌شود
        foreach ([16.0, 22.0, 30.0] as $index => $cm) {
            foreach ([4 => 1.5, 5 => 4.5, 6 => 8.0] as $count => $point) {
                foreach (static::FITS as $fit => $fitName) {
                    $rows['suit_set_waistcoat_'.static::LENGTH_KEYS[$index].'_b'.$count.'_'.$fit] = [
                        'title' => 'جلیقه رسمی '.static::LENGTH_NAMES[$index].'، '.$count.' دکمه، فرم '.$fitName,
                        'base' => 'suit_waistcoat',
                        'params' => [
                            'length' => $cm,
                            'buttons' => $count,
                            'hem_point' => $point,
                            'fit' => $fit,
                        ],
                    ];
                }
            }
        }

        return $rows;
    }

    /** @return array<string, array<string, mixed>> */
    protected static function trousers(): array
    {
        $rows = [];

        /*
         * پیلی با آزادیِ ران جفت می‌شود، نه تنها.
         *
         * تعدادِ پیلی به‌تنهایی فقط جای تاخورن را نشان می‌دهد و پهنای پنل را
         * عوض نمی‌کند — یک‌پیلی و دوپیلی یک الگو درمی‌آمدند. ولی پیلی از جایی
         * می‌آید که پارچهٔ اضافه هست: شلوارِ دوپیلی واقعاً از ران گشادتر است و
         * آن اضافه در پیلی‌ها جمع می‌شود. پس هر تعداد پیلی آزادیِ ران خودش را
         * می‌آورد.
         */
        $pleatSet = [0 => ['بی‌پیلی', 9.0], 1 => ['یک پیلی', 12.0], 2 => ['دو پیلی', 16.0]];

        foreach (static::RISES as $rise => $riseName) {
            foreach (static::LEG_LENGTHS as $length => [$lengthName, $change]) {
                foreach ($pleatSet as $pleats => [$pleatName, $thighEase]) {
                    foreach (['cuffed' => ['دوبله', 4.0], 'plain' => ['بی‌دوبله', 0.0]] as $cuff => [$cuffName, $cuffCm]) {
                        $rows['suit_set_trousers_'.$rise.'_'.$length.'_p'.$pleats.'_'.$cuff] = [
                            'title' => 'شلوار رسمی، '.$riseName.'، '.$lengthName.'، '.$pleatName.'، '.$cuffName,
                            'base' => 'suit_trousers',
                            'params' => [
                                'rise' => $rise,
                                'length_extra' => $change,
                                'front_pleats' => $pleats,
                                'thigh_ease' => $thighEase,
                                'cuff' => $cuffCm,
                            ],
                        ];
                    }
                }
            }
        }

        return $rows;
    }
}
