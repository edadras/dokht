<?php

namespace App\Services\Pattern\Generators;

/** دو سرِ دمِ لباس جلو گره می‌خورد؛ کوتاه و تابستانی. */
class BlouseTieFrontGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_tie_front';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_tie_front', 'title' => 'شومیز گره‌جلو',
            'fit' => 'regular', 'neckline' => 'v', 'collar' => 'none', 'sleeve' => 'short',
            'use' => 'summer', 'body_length' => 2, 'belt' => true];
    }
}
