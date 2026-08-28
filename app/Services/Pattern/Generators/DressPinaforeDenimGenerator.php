<?php

namespace App\Services\Pattern\Generators;

/** پیش‌بندپیراهنِ جین با بندِ سرشانه. */
class DressPinaforeDenimGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_pinafore_denim';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_pinafore_denim',
            'title' => 'پیراهن پیش‌بندی جین',
            'form' => 'onepiece',
            'shape' => 'straight',
            'length' => 46,
            'hem_flare' => 4,
            'bust_dart' => false,
            'closure' => 'none',
            'sleeve' => 'none',
            'block' => ['neck_width_extra' => 6, 'front_neck_depth_extra' => 10, 'back_neck_depth' => 8, 'armhole_depth_extra' => 4]
        ];
    }
}
