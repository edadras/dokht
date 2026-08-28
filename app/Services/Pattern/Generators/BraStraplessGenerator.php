<?php

namespace App\Services\Pattern\Generators;

/** سوتینِ بدونِ بند: همهٔ وزن روی سینه‌بند است، پس کشِ زیرِ سینه محسوس کوتاه‌تر بریده می‌شود. */
class BraStraplessGenerator extends BraSoftGenerator
{
    public static function key(): string
    {
        return 'bra_strapless';
    }

    public function label(): string
    {
        return 'سوتین بدون بند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'cup_ratio' => 0.25,
            'band_height' => 6.5,
            'hook_rows' => 3,
            'hook_columns' => 3,
            'strap_width' => 1.2,
            'band_ratio' => 0.74,
        ]);
    }
}
