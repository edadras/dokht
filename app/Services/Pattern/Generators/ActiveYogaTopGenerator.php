<?php

namespace App\Services\Pattern\Generators;

/** تاپِ یوگا: چسبان، بندِ پهن، لایهٔ دومِ جلو. */
class ActiveYogaTopGenerator extends ActiveTopBaseGenerator
{
    public static function key(): string
    {
        return 'active_yoga_top';
    }

    protected function active(): array
    {
        return [
            'prefix' => 'active_yoga_top',
            'title' => 'تاپ یوگا',
            'use' => 'yoga',
            'stretch' => 0.88,
            'length' => 6,
            'back_drop' => 2,
            'strap' => 6,
            'armhole_lift' => 2,
            'armhole_narrow' => 3,
            'inner' => true,
        ];
    }
}
