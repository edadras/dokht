<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ خواب و راحتی.
 *
 * جدا از خانوادهٔ ورزشی نوشته شده، نه از سرِ تکرار: گروهِ فهرست یک ویژگیِ ایستای
 * کلاس است، پس دو دستهٔ متفاوت نمی‌توانند در یک کلاس بنشینند بی آنکه یکی زیر
 * نامِ دیگری فهرست شود. لباس خواب باید زیر «لباس خواب» پیدا شود، نه زیر
 * «ورزشی».
 */
class SleepVariantCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'sleepwear';
    }

    /**
     * خواب و راحتی: کلید ⇒ [نام، درفتِ پایه، پارامترِ قد، قدها].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: array<string, float>, 4?: array<string, array<string, float>>}>
     */
    protected const SLEEP = [
        // باز شدنِ دم با قد بالا و پایین می‌رود: همان چهارده سانتی‌متری که روی
        // لباسِ بلند یک ریزشِ نرم است، روی لباسِ چهل سانتی درست روی خطِ باسن
        // می‌نشیند و آزادیِ باسن را از بازهٔ کاتالوگ بیرون می‌برد
        'nightgown' => ['لباس خواب', 'sleep_nightgown', 'length', ['short' => 40.0, 'midi' => 70.0, 'long' => 95.0],
            ['short' => ['hem_flare' => 5.0], 'midi' => ['hem_flare' => 11.0], 'long' => ['hem_flare' => 14.0]]],
        'robe' => ['روب', 'sleep_robe', 'length', ['short' => 32.0, 'midi' => 60.0, 'long' => 92.0]],
        // «کوتاه» و «بلند» این ردیف از پیش دو مدلِ دستیِ خودشان را دارند
        // (sleep_pajama_short و sleep_pajama_long)، پس این‌جا فقط میانه می‌آید
        'pajama' => ['ست پیژامه', 'sleep_pajama', 'top_length', ['regular' => 16.0]],
        'shorts' => ['شلوارک خواب', 'sleep_shorts', 'leg_length', ['micro' => 9.0, 'short' => 14.0, 'knee' => 24.0]],
        'lounge' => ['شلوار راحتی', 'lounge_pants', 'length_extra', ['crop' => -22.0, 'full' => 0.0]],
    ];

    /** نامِ فارسیِ قدهای خواب. */
    protected const SLEEP_NAMES = [
        'short' => 'کوتاه',
        'midi' => 'میدی',
        'long' => 'بلند',
        'regular' => 'معمولی',
        'micro' => 'خیلی کوتاه',
        'knee' => 'تا زانو',
        'crop' => 'کراپ',
        'full' => 'تمام‌قد',
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::SLEEP as $item => $row) {
            [$itemName, $base, $param, $lengths] = $row;
            $extra = $row[4] ?? [];

            foreach ($lengths as $length => $value) {
                $rows['sleep_'.$item.'_'.$length] = [
                    'title' => $itemName.'، '.(static::SLEEP_NAMES[$length] ?? $length),
                    'base' => $base,
                    'params' => array_merge([$param => $value], $extra[$length] ?? []),
                ];
            }
        }

        return $rows;
    }
}
