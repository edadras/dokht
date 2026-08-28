<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کمرگرفته با دامنِ پیلیِ ریز. */
class DressPleatedGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_pleated';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_pleated',
            'title' => 'پیراهن دامن پیلی‌دار',
            'form' => 'waisted',
            'skirt' => 'skirt_pleat_knife',
            'skirt_length' => 60,
            'sleeve' => 'set_in',
            'sleeve_length' => 22,
        ];
    }
}
