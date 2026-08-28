<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ گشادِ روپوشی، بی‌خطِ کمر. */
class DressSmockGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_smock';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_smock',
            'title' => 'پیراهن روپوشی',
            'form' => 'onepiece',
            'shape' => 'trapeze',
            'length' => 50,
            'hem_flare' => 8,
            'bust_dart' => false,
            'closure' => 'none',
            'sleeve' => 'set_in',
            'sleeve_length' => 26,
            'fit' => 'regular',
            'block' => ['armhole_depth_extra' => 3, 'neck_width_extra' => 3, 'front_neck_depth_extra' => 4],
            'notes' => ['از سر پوشیده می‌شود، پس یقه باید به‌اندازهٔ دور سر باز باشد.'],
        ];
    }
}
