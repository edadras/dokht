<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ لباس کودک.
 *
 * پایهٔ کودک چهار تفاوتِ بدنِ کودک را می‌داند و سنجشِ «رد شدن یقه از سر» را هم
 * خودکار انجام می‌دهد؛ این جدول فقط فرم و قد و آستین را در هم ضرب می‌کند.
 *
 * قدها با «آزادیِ رشد» جمع می‌شوند، پس هر ردیف دو عدد دارد: قدِ امروز و آنچه
 * برای فصلِ بعد کنار گذاشته می‌شود.
 */
class ChildVariantCatalog extends ChildGarmentBaseGenerator implements VariantAware
{
    use HasVariants;

    /**
     * فرم‌ها: کلید ⇒ [نام، فرم، بست، یقه، قد، باز شدن دم، جیب].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: float, 5: float, 6: bool}>
     */
    protected const SHAPES = [
        'tee' => ['تی‌شرت', 'top', 'closed', 'none', 12.0, 0.0, false],
        'shirt' => ['پیراهن', 'top', 'button', 'turn', 16.0, 0.0, false],
        'tunic' => ['تونیک', 'top', 'closed', 'none', 26.0, 2.5, true],
        'dress' => ['پیراهن دخترانه', 'dress', 'closed', 'none', 34.0, 3.0, false],
        'partydress' => ['پیراهن مجلسی', 'dress', 'button', 'none', 38.0, 3.5, false],
        'cardigan' => ['ژاکت', 'top', 'button', 'none', 15.0, 0.0, false],
        'jacket' => ['کاپشن', 'top', 'zip', 'hood', 18.0, 0.0, true],
        'coat' => ['پالتو', 'top', 'button', 'turn', 32.0, 3.5, true],
        'vest' => ['جلیقه', 'top', 'button', 'none', 14.0, 0.0, false],
    ];

    /**
     * آستین: کلید ⇒ [نام، سبک، بلندی].
     *
     * @var array<string, array{0: string, 1: string, 2: float}>
     */
    protected const SLEEVES = [
        'none' => ['بی‌آستین', 'none', 0.0],
        'short' => ['آستین کوتاه', 'set_in', 16.0],
        'long' => ['آستین بلند', 'set_in', 40.0],
    ];

    /**
     * کاربرد: کلید ⇒ [نام، آزادی بازی، آزادی رشد].
     *
     * @var array<string, array{0: string, 1: float, 2: float}>
     */
    protected const USES_TABLE = [
        'daily' => ['روزمره', 1.5, 2.0],
        'play' => ['بازی', 2.5, 2.5],
        'school' => ['مدرسه', 2.0, 2.5],
        'party' => ['مهمانی', 1.0, 1.5],
    ];

    /**
     * پایین‌تنه: کلید ⇒ [نام، آزادی زانو، آزادی دم پا، قد پا، مچ کشباف].
     *
     * @var array<string, array{0: string, 1: float, 2: float, 3: float|null, 4: bool}>
     */
    protected const BOTTOMS = [
        'pants' => ['شلوار', 9.0, 8.0, null, false],
        'jogger' => ['شلوار جاگر', 12.0, 12.0, null, true],
        'wide' => ['شلوار گشاد', 18.0, 22.0, null, false],
        'leggings' => ['ساق‌شلواری', 2.0, 2.0, null, false],
        'shorts' => ['شلوارک', 12.0, 14.0, 26.0, false],
        'micro' => ['شلوارک کوتاه', 12.0, 14.0, 16.0, false],
    ];

    /**
     * اندازهٔ درفت: کلید ⇒ [نام، ضریبِ آزادیِ رشد].
     *
     * لباسِ کودک را برای امروز می‌دوزند یا برای یک فصلِ جلوتر، و این دو الگوی
     * متفاوت‌اند نه یک الگو با دو برچسب: آزادیِ رشد هم به بلندیِ تنه می‌رود هم
     * به آستین. برندهای کودک همین را «سایزِ رشد» می‌نامند.
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const GROWTH = [
        'now' => ['اندازهٔ امروز', 0.6],
        'grow' => ['با آزادی رشد', 1.6],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::SHAPES as $shape => [$shapeName, $form, $opening, $collar, $length, $flare, $pocket]) {
            foreach (static::SLEEVES as $sleeve => [$sleeveName, $style, $sleeveLength]) {
                // ژاکت و کاپشن و پالتوی بی‌آستین وجود ندارد
                if ($style === 'none' && in_array($shape, ['cardigan', 'jacket', 'coat', 'shirt'], true)) {
                    continue;
                }

                foreach (static::USES_TABLE as $use => [$useName, $play, $growth]) {
                    if ($use === 'party' && in_array($shape, ['jacket', 'tee'], true)) {
                        continue;
                    }

                    foreach (static::GROWTH as $grade => [$gradeName, $factor]) {
                    $key = 'child_'.$shape.'_'.$sleeve.'_'.$use.'_'.$grade;

                    $rows[$key] = [
                        'title' => $shapeName.' بچگانه، '.$sleeveName.'، '.$useName.'، '.$gradeName,
                        'form' => $form,
                        'use' => $use,
                        'length' => $length,
                        'length_max' => max(60.0, $length * 2.5),
                        'hem_flare' => $flare,
                        'opening' => $opening,
                        'collar' => $collar,
                        'sleeve' => $style,
                        'sleeve_length' => $sleeveLength,
                        'pocket' => $pocket,
                        'play' => $play,
                        'growth' => round($growth * $factor, 1),
                        'knit' => in_array($shape, ['tee', 'cardigan'], true),
                    ];
                    }
                }
            }
        }

        foreach (static::BOTTOMS as $bottom => [$bottomName, $knee, $hem, $legLength, $rib]) {
            foreach (static::USES_TABLE as $use => [$useName, $play, $growth]) {
                if ($use === 'party') {
                    continue;
                }

                foreach (static::GROWTH as $grade => [$gradeName, $factor]) {
                $key = 'child_'.$bottom.'_'.$use.'_'.$grade;

                $rows[$key] = [
                    'title' => $bottomName.' بچگانه، '.$useName.'، '.$gradeName,
                    'form' => 'pants',
                    'use' => $use,
                    'knee_ease' => $knee,
                    'hem_ease' => $hem,
                    'play' => $play,
                    'growth' => round($growth * $factor, 1),
                    'rib' => $rib,
                ];

                if ($legLength !== null) {
                    $rows[$key]['leg_length'] = $legLength;
                }
                }
            }
        }

        return $rows;
    }

    protected function child(): array
    {
        return $this->spec();
    }
}
