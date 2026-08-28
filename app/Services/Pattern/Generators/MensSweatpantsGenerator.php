<?php

namespace App\Services\Pattern\Generators;

/** گرم‌کنِ مردانه: کمرِ کشی و بندی، پای راسته و راحت. */
class MensSweatpantsGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_sweatpants';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_sweatpants',
            'title' => 'شلوار گرم‌کن مردانه',
            'use' => 'sport',
            'rise' => 'mid',
            'thigh_ease' => 20,
            'knee_ease' => 18,
            'hem_ease' => 16,
            'hem_vs_knee' => -3.0,
            'front_waist' => 'gather',
            'back_waist' => 'gather',
            'waist_balance' => 0.0,
            'band' => 'elastic',
            'band_stretch' => 0.88,
            'band_height' => 5,
            'notes' => ['دو جادکمهٔ بند روی مرکزِ جلوی نوارِ کمر کار گذاشته می‌شود.'],
        ];
    }
}
