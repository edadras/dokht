<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کوتاهِ شیفت. */
class DressShiftMiniGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_shift_mini';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_shift_mini',
            'title' => 'پیراهن کوتاه شیفت',
            'form' => 'onepiece',
            'shape' => 'straight',
            'length' => 32,
            'hem_flare' => 3,
            'sleeve' => 'set_in',
            'sleeve_length' => 18
        ];
    }
}
