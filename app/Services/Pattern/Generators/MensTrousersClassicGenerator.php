<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ پارچه‌ایِ کلاسیکِ مردانه: پای راسته، یک پیلیِ جلو، دو ساسونِ پشت. */
class MensTrousersClassicGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_trousers_classic';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_trousers_classic',
            'title' => 'شلوار پارچه‌ای مردانه',
            'use' => 'office',
            'rise' => 'mid',
            'thigh_ease' => 12,
            'knee_ease' => 9,
            'hem_ease' => 11,
            'hem_vs_knee' => -2.0,
            'front_waist' => 'pleat',
            'pleat_count' => 1,
            'notes' => ['پیلیِ جلو رو به مرکز خوابانده می‌شود؛ خطِ اتوی پا از وسطِ همان پیلی رد می‌شود.'],
        ];
    }
}
