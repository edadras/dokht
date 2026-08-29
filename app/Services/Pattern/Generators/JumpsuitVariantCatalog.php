<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ لباس یک‌تکه.
 *
 * سه انتخاب: پاچه چه فرمی دارد، آستین چیست، و جلو چطور باز می‌شود. هر سه قطعه
 * را عوض می‌کنند.
 */
class JumpsuitVariantCatalog extends JumpsuitBaseGenerator implements VariantAware
{
    use HasVariants;

    public static function group(): string
    {
        return 'onepiece';
    }

    /**
     * پاچه: کلید ⇒ [نام، فرم، آزادی زانو، آزادی دم پا، بلندی پاچهٔ کوتاه].
     *
     * @var array<string, array{0: string, 1: string, 2: float, 3: float, 4: float}>
     */
    protected const LEGS = [
        'slim' => ['پاچه جذب', 'pants', 4.0, 4.0, 0.0],
        'straight' => ['پاچه راسته', 'pants', 10.0, 12.0, 0.0],
        'wide' => ['پاچه گشاد', 'pants', 20.0, 30.0, 0.0],
        'palazzo' => ['پاچه پالازو', 'pants', 30.0, 44.0, 0.0],
        'tapered' => ['پاچه باریک‌شونده', 'pants', 14.0, 6.0, 0.0],
        'cropped' => ['پاچه کراپ', 'pants', 12.0, 14.0, 0.0],
        'short' => ['پاچه کوتاه', 'shorts', 0.0, 8.0, 16.0],
        'micro' => ['پاچه خیلی کوتاه', 'shorts', 0.0, 6.0, 9.0],
        'bermuda' => ['پاچه تا زانو', 'shorts', 0.0, 12.0, 30.0],
    ];

    /**
     * آستین: کلید ⇒ [نام، سبک، بلندی].
     *
     * @var array<string, array{0: string, 1: string, 2: float}>
     */
    protected const SLEEVES = [
        'none' => ['بی‌آستین', 'none', 0.0],
        'short' => ['آستین کوتاه', 'set_in', 20.0],
        'long' => ['آستین بلند', 'set_in', 58.0],
    ];

    /**
     * جلو: کلید ⇒ [نام، بست، یقه].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const FRONTS = [
        'zip' => ['زیپ جلو', 'zip', 'stand'],
        'button' => ['دکمه‌دار', 'button', 'turn'],
        'plain' => ['جلو بسته', 'closed', 'none'],
    ];

    /**
     * فرم: کلید ⇒ نام. آزادیِ سینه و کمر و باسن را با هم عوض می‌کند.
     *
     * @var array<string, string>
     */
    protected const FITS = [
        'regular' => 'فرم معمولی',
        'loose' => 'فرم گشاد',
    ];

    /**
     * جیب: کلید ⇒ [نام، دارد یا نه].
     *
     * جیبِ سرهمی یک قطعهٔ کاملِ اضافه است. سرهمیِ کار جیب می‌خواهد و سرهمیِ
     * مجلسی خطِ تمیزِ بی‌جیب.
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    protected const POCKETS = [
        'pocket' => ['جیب‌دار', true],
        'plain' => ['بی‌جیب', false],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::LEGS as $leg => [$legName, $form, $knee, $hem, $shortLength]) {
            foreach (static::SLEEVES as $sleeve => [$sleeveName, $style, $sleeveLength]) {
                foreach (static::FRONTS as $front => [$frontName, $opening, $collar]) {
                    // جلوی بسته با پاچهٔ گشاد از سر پوشیده نمی‌شود؛ یقه‌اش باید
                    // باز باشد و آن دیگر مدلِ دیگری است
                    if ($opening === 'closed' && $leg === 'palazzo') {
                        continue;
                    }

                    foreach (static::FITS as $fit => $fitName) {
                        foreach (static::POCKETS as $bag => [$bagName, $hasPocket]) {
                            $key = 'jumpsuit_'.$leg.'_'.$sleeve.'_'.$front.'_'.$fit.'_'.$bag;

                            $rows[$key] = [
                                'title' => ($form === 'shorts' ? 'سرهمی کوتاه ' : 'سرهمی ').$legName.'، '.$sleeveName.'، '
                                    .$frontName.'، '.$fitName.'، '.$bagName,
                                'fit' => $fit,
                                'form' => $form,
                                'knee_ease' => $knee,
                                'hem_ease' => $hem,
                                'short_length' => $shortLength,
                                'leg_length' => $leg === 'cropped' ? -20.0 : 0.0,
                                'sleeve' => $style,
                                'sleeve_length' => $sleeveLength,
                                'opening' => $opening,
                                'collar' => $collar,
                                'belt' => in_array($leg, ['wide', 'palazzo', 'straight'], true),
                                'pocket' => $hasPocket,
                                'neck_depth' => $opening === 'closed' ? 7.0 : 2.5,
                                'neck_width' => $opening === 'closed' ? 3.0 : 1.0,
                            ];
                        }
                    }
                }
            }
        }

        return $rows;
    }

    protected function jumpsuit(): array
    {
        return $this->spec();
    }
}
