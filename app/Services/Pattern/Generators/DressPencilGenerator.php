<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ مدادیِ اداری تا زیرِ زانو. */
class DressPencilGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_pencil';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_pencil',
            'title' => 'پیراهن مدادی',
            'form' => 'waisted',
            'skirt' => 'skirt_pencil',
            'skirt_length' => 58,
            'sleeve' => 'set_in',
            'sleeve_length' => 20,
            'fit' => 'fitted'
        ];
    }
}
