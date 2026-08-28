<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ طبقه‌ای با سه طبقهٔ چین‌دار. */
class DressTieredCatalogGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_tiered';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_tiered',
            'title' => 'پیراهن طبقه‌ای',
            'form' => 'waisted',
            'skirt' => 'skirt_tiered',
            'skirt_length' => 70,
            'skirt_params' => ['tiers' => 3, 'tier_growth' => 1.3],
            'sleeve' => 'set_in',
            'sleeve_length' => 16,
        ];
    }
}
