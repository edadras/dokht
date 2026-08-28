<?php

namespace App\Services\Pattern\Generators;

/** پالتوی بچگانه: بلند، دکمه‌دار، با یقهٔ برگردان. */
class ChildCoatGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_coat';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_coat',
            'title' => 'پالتو بچگانه',
            'form' => 'top',
            'use' => 'outdoor',
            'play' => 2.5,
            'growth' => 2.5,
            'length' => 32,
            'length_max' => 70,
            'shape' => 'straight',
            'opening' => 'button',
            'buttons' => 5,
            'collar' => 'turn',
            'collar_height' => 7,
            'sleeve_length' => 44,
            'hem_flare' => 3.5,
            'pocket' => true,
            'schema' => ['armhole_depth_extra' => 4.5],
        ];
    }
}
