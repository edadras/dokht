<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کتانِ میدیِ گشاد. */
class DressLinenGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_linen_midi';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_linen_midi',
            'title' => 'پیراهن کتان میدی',
            'form' => 'onepiece',
            'shape' => 'trapeze',
            'length' => 70,
            'hem_flare' => 8,
            'bust_dart' => false,
            'closure' => 'none',
            'sleeve' => 'set_in',
            'sleeve_length' => 20,
            'block' => ['neck_width_extra' => 3, 'front_neck_depth_extra' => 5, 'armhole_depth_extra' => 3]
        ];
    }
}
