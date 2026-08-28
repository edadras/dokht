<?php

namespace App\Services\Pattern\Generators;

/** سویشرتِ بچگانه: کشبافِ ضخیم، سرخود، با نوارِ کشبافِ یقه و لبه. */
class ChildSweatshirtGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_sweatshirt';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_sweatshirt',
            'title' => 'سویشرت بچگانه',
            'form' => 'top',
            'use' => 'daily',
            'knit' => true,
            'play' => 3,
            'length' => 14,
            'shape' => 'straight',
            'opening' => 'closed',
            'sleeve_length' => 40,
            'neck_band' => true,
            'band_ratio' => 0.9,
        ];
    }
}
