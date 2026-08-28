<?php

namespace App\Services\Pattern\Generators;

/** شومیزِ کتانِ گشادِ تابستانی با یقهٔ باز و آستینِ سه‌ربع. */
class BlouseLinenGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_linen';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_linen', 'title' => 'شومیز کتان',
            'fit' => 'loose', 'neckline' => 'v', 'collar' => 'none', 'sleeve' => 'three_quarter',
            'use' => 'summer', 'bust_dart' => false];
    }
}
