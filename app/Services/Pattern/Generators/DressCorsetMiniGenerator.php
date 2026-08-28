<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کوتاه با بالاتنهٔ کرستی و دامنِ خط A. */
class DressCorsetMiniGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_corset_mini';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_corset_mini',
            'title' => 'پیراهن کرستی کوتاه',
            'form' => 'waisted',
            'skirt' => 'skirt_a_line',
            'skirt_length' => 40,
            'skirt_params' => ['flare' => 20],
            'sleeve' => 'none',
            'fit' => 'fitted',
            'block' => ['neck_width_extra' => 3, 'front_neck_depth_extra' => 10],
        ];
    }
}
