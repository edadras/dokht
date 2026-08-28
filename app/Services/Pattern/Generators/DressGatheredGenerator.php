<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کمرگرفته با دامنِ چین‌دار. */
class DressGatheredGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_gathered';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_gathered',
            'title' => 'پیراهن دامن چین‌دار',
            'form' => 'waisted',
            'skirt' => 'skirt_gathered',
            'skirt_length' => 60,
            'sleeve' => 'set_in',
            'sleeve_length' => 18,
        ];
    }
}
