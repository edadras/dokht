<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ پیش‌بندی با پشتِ باز و بندِ گره‌ای. */
class DressApronGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_apron_style';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_apron_style',
            'title' => 'پیراهن پیش‌بندی',
            'form' => 'waisted',
            'skirt' => 'skirt_a_line',
            'skirt_length' => 60,
            'skirt_params' => ['flare' => 26],
            'sleeve' => 'none',
            'closure' => 'none',
            'fit' => 'regular',
            'block' => ['neck_width_extra' => 5, 'back_neck_depth' => 12]
        ];
    }
}
