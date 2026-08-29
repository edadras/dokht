<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ لباس ورزشی.
 *
 * لباسِ ورزشی با آزادی درفت نمی‌شود، با *ضریبِ کشسانی* درفت می‌شود: چقدر کوچک‌تر
 * از بدن بریده شود. همان تنگی است که لباس را هنگام دویدن سرِ جایش نگه می‌دارد و
 * لباسِ اندازهٔ بدن پایین می‌رود. پس محورِ اصلیِ این جدول همان ضریب است، به‌علاوهٔ
 * قد برای پایین‌تنه.
 */
class ActiveVariantCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'active';
    }

    /**
     * ورزشی: کلید ⇒ [نام، درفتِ پایه، ضریب‌های کشسانی].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    protected const ACTIVE = [
        'tank' => ['رکابی ورزشی', 'active_tank', ['tight', 'snug', 'easy']],
        'tshirt' => ['تی‌شرت ورزشی', 'active_tshirt', ['snug', 'easy']],
        'longsleeve' => ['بالاتنه آستین‌بلند ورزشی', 'active_long_sleeve', ['tight', 'snug', 'easy']],
        'baselayer' => ['لایه اول ورزشی', 'active_base_layer', ['tight', 'snug']],
        'crop' => ['تاپ کراپ ورزشی', 'active_crop_top', ['tight', 'snug']],
        'singlet' => ['رکابی دو و میدانی', 'active_singlet', ['snug', 'easy']],
        'yogatop' => ['تاپ یوگا', 'active_yoga_top', ['tight', 'snug']],
        'brahigh' => ['سوتین ورزشی پرفشار', 'active_bra_high', ['tight', 'snug']],
        'bralight' => ['سوتین ورزشی سبک', 'active_bra_light', ['tight', 'snug']],
        'jersey' => ['پیراهن تیمی', 'active_team_jersey', ['snug', 'easy']],
    ];

    /**
     * ضریبِ کشسانی: کلید ⇒ [نام، ضریب].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const STRETCH = [
        'tight' => ['فشرده', 0.82],
        'snug' => ['چسبان', 0.9],
        'easy' => ['راحت', 0.98],
    ];

    /**
     * پایین‌تنهٔ ورزشی: کلید ⇒ [نام، درفتِ پایه، قدها].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    protected const ACTIVE_BOTTOMS = [
        'tights' => ['تایت ورزشی', 'active_tights', ['crop', 'ankle', 'full']],
        /*
         * تایتِ سه‌ربع این‌جا ردیف ندارد، و درست هم همین است.
         *
         * درفتِ «تایت سه‌ربع» با تایتِ معمولی فقط در یک چیز فرق دارد: قدِ پا. و
         * قد همان محوری است که این جدول می‌چرخاند، پس ردیفِ «تایت سه‌ربعِ کراپ»
         * مو به مو همان «تایتِ کراپ» درمی‌آمد با نامی دیگر. خودِ درفت سرِ جایش
         * در فهرست هست، برای کسی که آن را با نامش می‌خواهد.
         */
        'bike' => ['تایت کوتاه ورزشی', 'active_bike_shorts', ['crop']],
        'track' => ['شلوار گرمکن', 'active_track_pants', ['ankle', 'full']],
        'yoga' => ['شلوار یوگا', 'active_yoga_pants', ['ankle', 'full']],
        'jogger' => ['شلوار جاگر ورزشی', 'active_sport_jogger', ['ankle', 'full']],
    ];

    /** قدِ پایین‌تنه: کلید ⇒ [نام، تغییرِ قد پا]. */
    protected const LEG_LENGTHS = [
        'crop' => ['کراپ', -30.0],
        'ankle' => ['تا قوزک', -8.0],
        'full' => ['تمام‌قد', 0.0],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::ACTIVE as $item => [$itemName, $base, $grades]) {
            foreach ($grades as $grade) {
                [$gradeName, $ratio] = static::STRETCH[$grade];

                $rows['active_'.$item.'_'.$grade] = [
                    'title' => $itemName.'، '.$gradeName,
                    'base' => $base,
                    'params' => ['stretch' => $ratio],
                ];
            }
        }

        foreach (static::ACTIVE_BOTTOMS as $item => [$itemName, $base, $lengths]) {
            foreach ($lengths as $length) {
                [$lengthName, $change] = static::LEG_LENGTHS[$length];

                $rows['active_'.$item.'_'.$length] = [
                    'title' => $itemName.'، '.$lengthName,
                    'base' => $base,
                    'params' => ['length_extra' => $change],
                ];
            }
        }

        return $rows;
    }
}
