<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ ساتنِ میدی با یقهٔ هفت و آستینِ کوتاه. */
class DressSatinMidiGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_satin_midi';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_satin_midi',
            'title' => 'پیراهن ساتن میدی',
            'form' => 'waisted',
            'skirt' => 'skirt_a_line',
            'skirt_length' => 72,
            'skirt_params' => ['flare' => 26],
            'sleeve' => 'set_in',
            'sleeve_length' => 16,
            'fit' => 'fitted',
            'block' => ['front_neck_depth_extra' => 7]
        ];
    }
}
