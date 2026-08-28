<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کمرگرفته با دامنِ کلوش. */
class DressFitFlareGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_fit_flare';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_fit_flare',
            'title' => 'پیراهن کمرگرفته دامن‌کلوش',
            'form' => 'waisted',
            'skirt' => 'skirt_circle_half',
            'skirt_length' => 58,
            'sleeve' => 'set_in',
            'sleeve_length' => 20,
            'fit' => 'fitted',
        ];
    }
}
