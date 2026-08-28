<?php

namespace App\Services\Pattern\Generators;

/** لباسِ شبِ طبقه‌ای: سه طبقهٔ چین‌دار از خطِ کمر. */
class EveningTieredGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'evening_tiered';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'evening_tiered',
            'title' => 'لباس شب طبقه‌ای',
            'skirt' => 'skirt_tiered',
            'length' => 110,
            'skirt_params' => ['tiers' => 3, 'tier_growth' => 1.35, 'waist_gather' => 1.2],
            'neckline' => 'strap',
            'fit' => 'regular',
        ];
    }
}
