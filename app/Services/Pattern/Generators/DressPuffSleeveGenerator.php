<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ آستین‌پفی با دامنِ چین‌دار. */
class DressPuffSleeveGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_puff_sleeve';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_puff_sleeve',
            'title' => 'پیراهن آستین‌پفی',
            'form' => 'waisted',
            'skirt' => 'skirt_gathered',
            'skirt_length' => 56,
            'sleeve' => 'set_in',
            'sleeve_length' => 20,
            'fit' => 'fitted'
        ];
    }
}
