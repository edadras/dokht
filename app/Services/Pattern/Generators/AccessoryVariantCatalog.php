<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ متعلقات.
 *
 * متعلق از بلوکِ بدن درفت نمی‌شود، پس محورش اندازه است نه فرم: همان کیف در سه
 * اندازه، همان شال در سه بلندی. «بزرگ‌نمایی» پارامترِ خودِ پایه است و همهٔ
 * اندازه‌های یک متعلق را با هم بالا و پایین می‌برد — که برای متعلق درست است و
 * برای لباس نه.
 */
class AccessoryVariantCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'accessory';
    }

    /**
     * متعلق‌ها: کلید ⇒ [نام، درفتِ پایه، اندازه‌های پذیرفته].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    protected const ITEMS = [
        'tote' => ['کیف خرید', 'bag_tote', ['small', 'regular', 'large']],
        'shoulder' => ['کیف دوشی', 'bag_shoulder', ['small', 'regular', 'large']],
        'crossbody' => ['کیف رودوشی', 'bag_crossbody', ['small', 'regular']],
        'backpack' => ['کوله‌پشتی', 'bag_backpack', ['small', 'regular', 'large']],
        'pouch' => ['کیف لوازم', 'bag_pouch', ['small', 'regular', 'large']],
        'sleeve' => ['جلد رایانه', 'bag_laptop_sleeve', ['small', 'regular', 'large']],
        'bucket' => ['کلاه باکت', 'hat_bucket', ['small', 'regular', 'large']],
        'beanie' => ['کلاه بافتنی', 'hat_beanie', ['small', 'regular', 'large']],
        'cap' => ['کلاه نقاب‌دار', 'hat_cap', ['small', 'regular', 'large']],
        'sun' => ['کلاه آفتابی', 'hat_sun', ['regular', 'large']],
        'headband' => ['هدبند', 'hat_headband', ['small', 'regular']],
        'rect' => ['شال مستطیل', 'scarf_rect', ['small', 'regular', 'large']],
        'square' => ['روسری چهارگوش', 'scarf_square', ['small', 'regular', 'large']],
        'infinity' => ['شال گردن حلقه‌ای', 'scarf_infinity', ['small', 'regular']],
        'triangle' => ['شال سه‌گوش', 'scarf_triangle', ['regular', 'large']],
        'snood' => ['گردن‌پوش', 'scarf_snood', ['small', 'regular']],
        'mitten' => ['دستکش یک‌انگشتی', 'glove_mitten', ['small', 'regular', 'large']],
        'armwarmer' => ['مچ‌پوش بلند', 'glove_arm_warmer', ['small', 'regular']],
        'legwarmer' => ['ساق‌پوش', 'warmer_leg', ['small', 'regular']],
        'beltwide' => ['کمربند پهن', 'belt_wide', ['regular', 'large']],
        'belttie' => ['بند کمر گره‌ای', 'belt_tie', ['regular', 'large']],
        'tie' => ['کراوات', 'tie_neck', ['small', 'regular']],
        'bow' => ['پاپیون', 'tie_bow', ['small', 'regular']],
        'mask' => ['ماسک پارچه‌ای', 'accessory_face_mask', ['small', 'regular', 'large']],
        'eyemask' => ['چشم‌بند خواب', 'accessory_eye_mask', ['small', 'regular']],
    ];

    /**
     * اندازه‌ها: کلید ⇒ [نام، ضریبِ بزرگ‌نمایی].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const SIZES = [
        'small' => ['کوچک', 0.8],
        'regular' => ['معمولی', 1.0],
        'large' => ['بزرگ', 1.25],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::ITEMS as $item => [$itemName, $base, $sizes]) {
            foreach ($sizes as $size) {
                [$sizeName, $scale] = static::SIZES[$size];

                $rows['acc_'.$item.'_'.$size] = [
                    'title' => $itemName.' '.$sizeName,
                    'base' => $base,
                    'params' => ['scale' => $scale],
                ];
            }
        }

        return $rows;
    }
}
