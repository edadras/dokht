<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ هالتر: بندِ گردنی و پشتِ باز. */
class DressHalterGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_halter';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_halter',
            'title' => 'پیراهن هالتر',
            'form' => 'waisted',
            'skirt' => 'skirt_a_line',
            'skirt_length' => 58,
            'skirt_params' => ['flare' => 28],
            'sleeve' => 'none',
            'fit' => 'fitted',
            'block' => ['neck_width_extra' => -1, 'front_neck_depth_extra' => 6, 'back_neck_depth' => 10],
        ];
    }
}
