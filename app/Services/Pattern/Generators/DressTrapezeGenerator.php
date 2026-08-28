<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ ذوزنقه‌ای که از سرشانه باز می‌شود. */
class DressTrapezeGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_trapeze';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_trapeze',
            'title' => 'پیراهن ذوزنقه‌ای',
            'form' => 'onepiece',
            'shape' => 'trapeze',
            'length' => 52,
            'hem_flare' => 6,
            'bust_dart' => false,
            'closure' => 'none',
            'sleeve' => 'set_in',
            'sleeve_length' => 22,
            'block' => ['neck_width_extra' => 3, 'front_neck_depth_extra' => 4]
        ];
    }
}
