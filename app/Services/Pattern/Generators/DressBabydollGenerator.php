<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ بیبی‌دال: چینِ زیرِ سینه و دامنِ کوتاهِ گشاد. */
class DressBabydollGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_babydoll';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_babydoll',
            'title' => 'پیراهن بیبی‌دال',
            'form' => 'onepiece',
            'shape' => 'trapeze',
            'length' => 40,
            'hem_flare' => 7,
            'bust_dart' => true,
            'closure' => 'none',
            'sleeve' => 'set_in',
            'sleeve_length' => 16,
            'block' => ['neck_width_extra' => 2.5, 'front_neck_depth_extra' => 4]
        ];
    }
}
