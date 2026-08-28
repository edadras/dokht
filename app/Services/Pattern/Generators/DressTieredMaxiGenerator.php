<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ ماکسیِ طبقه‌ای. */
class DressTieredMaxiGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_tiered_maxi';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_tiered_maxi',
            'title' => 'پیراهن ماکسی طبقه‌ای',
            'form' => 'waisted',
            'skirt' => 'skirt_tiered',
            'skirt_length' => 96,
            'skirt_params' => ['tiers' => 3, 'tier_growth' => 1.35],
            'sleeve' => 'set_in',
            'sleeve_length' => 14
        ];
    }
}
