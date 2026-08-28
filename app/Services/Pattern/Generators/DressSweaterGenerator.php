<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کشباف (سویشرت‌پیراهن) تا بالای زانو. */
class DressSweaterGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_sweater';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_sweater',
            'title' => 'پیراهن کشباف',
            'form' => 'onepiece',
            'shape' => 'straight',
            'length' => 44,
            'hem_flare' => 3,
            'bust_dart' => false,
            'closure' => 'none',
            'sleeve' => 'set_in',
            'sleeve_length' => 58,
            'fit' => 'loose',
            'block' => ['armhole_depth_extra' => 3.5, 'neck_width_extra' => 2.5],
        ];
    }
}
