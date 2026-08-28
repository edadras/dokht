<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کتی: یقهٔ برگردان، دکمهٔ جلو، کوتاه. */
class DressBlazerGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_blazer';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_blazer',
            'title' => 'پیراهن کتی',
            'form' => 'onepiece',
            'shape' => 'fitted',
            'length' => 40,
            'hem_flare' => 2,
            'waist_dart' => true,
            'closure' => 'buttons',
            'sleeve' => 'set_in',
            'sleeve_length' => 58,
            'block' => ['front_neck_depth_extra' => 12, 'armhole_depth_extra' => 3]
        ];
    }
}
