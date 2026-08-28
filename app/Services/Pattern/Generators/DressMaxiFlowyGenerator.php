<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ ماکسیِ روان با دامنِ باز. */
class DressMaxiFlowyGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_maxi_flowy';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_maxi_flowy',
            'title' => 'پیراهن ماکسی روان',
            'form' => 'waisted',
            'skirt' => 'skirt_circle_half',
            'skirt_length' => 100,
            'sleeve' => 'set_in',
            'sleeve_length' => 18
        ];
    }
}
