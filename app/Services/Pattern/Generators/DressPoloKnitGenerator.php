<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کشبافِ پولویی با یقهٔ برگردانِ کوتاه. */
class DressPoloKnitGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_polo_knit';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_polo_knit',
            'title' => 'پیراهن پولویی کشباف',
            'form' => 'onepiece',
            'shape' => 'straight',
            'length' => 44,
            'hem_flare' => 3,
            'bust_dart' => false,
            'closure' => 'none',
            'sleeve' => 'set_in',
            'sleeve_length' => 18,
            'block' => ['neck_width_extra' => 2, 'armhole_depth_extra' => 3]
        ];
    }
}
