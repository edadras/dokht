<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ یک‌شانه. */
class DressOneShoulderGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_one_shoulder';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_one_shoulder',
            'title' => 'پیراهن یک‌شانه',
            'form' => 'waisted',
            'skirt' => 'skirt_straight',
            'skirt_length' => 56,
            'sleeve' => 'none',
            'fit' => 'fitted',
            'block' => ['neck_width_extra' => 3, 'front_neck_depth_extra' => 9]
        ];
    }
}
