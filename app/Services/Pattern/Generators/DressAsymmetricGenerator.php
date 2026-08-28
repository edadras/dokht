<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ نامتقارن با دمِ کج. */
class DressAsymmetricGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_asymmetric';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_asymmetric',
            'title' => 'پیراهن نامتقارن',
            'form' => 'waisted',
            'skirt' => 'skirt_asymmetric',
            'skirt_length' => 60,
            'sleeve' => 'none',
            'fit' => 'fitted',
            'block' => ['neck_width_extra' => 2, 'front_neck_depth_extra' => 6]
        ];
    }
}
