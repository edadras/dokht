<?php

namespace App\Services\Pattern\Generators;

/** دامنِ طبقه‌ایِ چین‌دار با سه طبقه. */
class SkirtRuffleTieredGenerator extends SkirtTieredGenerator
{
    public static function key(): string
    {
        return 'skirt_ruffle_tiered';
    }

    public function label(): string
    {
        return 'دامن طبقه‌ای چین‌دار';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 84,
            'tiers' => 3,
            'tier_growth' => 1.4,
        ]);
    }
}
