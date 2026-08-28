<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ جینِ دکمه‌دار با دامنِ راسته. */
class DressDenimGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_denim';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_denim',
            'title' => 'پیراهن جین',
            'form' => 'waisted',
            'skirt' => 'skirt_straight',
            'skirt_length' => 52,
            'closure' => 'buttons',
            'sleeve' => 'set_in',
            'sleeve_length' => 20,
            'notes' => ['روی پارچهٔ جین، درزها را دو سوزنه بدوزید تا لبه تاب برندارد.'],
        ];
    }
}
