<?php

namespace App\Services\Pattern\Generators;

/** تاپِ کراپِ ورزشی: بالای خطِ کمر تمام می‌شود. */
class ActiveCropTopGenerator extends ActiveTopBaseGenerator
{
    public static function key(): string
    {
        return 'active_crop_top';
    }

    protected function active(): array
    {
        return [
            'prefix' => 'active_crop_top',
            'title' => 'تاپ کراپ ورزشی',
            'use' => 'yoga',
            'stretch' => 0.9,
            'height' => 24,
            'back_drop' => 2,
            'strap' => 5,
            'armhole_lift' => 2,
            'armhole_narrow' => 2.5,
        ];
    }
}
