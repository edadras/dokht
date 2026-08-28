<?php

namespace App\Services\Pattern\Generators;

/** جینِ مردانه: فاقِ کوتاه‌تر، رانِ جمع‌تر، پارچهٔ بی‌کشش، دوختِ پنج‌جیبی. */
class MensJeansGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_jeans';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_jeans',
            'title' => 'شلوار جین مردانه',
            'use' => 'daily',
            'rise' => 'low',
            'rise_extra' => 0.5,
            'thigh_ease' => 7,
            'knee_ease' => 5,
            'hem_ease' => 6,
            'hem_vs_knee' => -3.0,
            'front_waist' => 'none',
            'side_share' => 0.3,
            'band_height' => 4,
            'notes' => ['کمربندِ جین منحنی است؛ اگر پارچه کشش ندارد یک سانتی‌متر به دورِ کمر اضافه کن.'],
        ];
    }
}
