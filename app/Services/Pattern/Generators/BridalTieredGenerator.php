<?php

namespace App\Services\Pattern\Generators;

/** لباسِ عروسِ طبقه‌ای با تورِ چندلایه. */
class BridalTieredGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_tiered';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'bridal_tiered',
            'title' => 'لباس عروس طبقه‌ای',
            'skirt' => 'skirt_tiered',
            'length' => 116,
            'skirt_params' => ['tiers' => 4, 'tier_growth' => 1.3, 'ruffle' => true],
            'neckline' => 'strap',
        ];
    }
}
