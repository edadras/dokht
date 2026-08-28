<?php

namespace App\Services\Pattern\Generators;

/** آستینِ پفیِ بلند با مچِ جمع. */
class BlousePuffSleeveGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_puff_sleeve';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_puff_sleeve', 'title' => 'شومیز آستین‌پفی',
            'fit' => 'regular', 'neckline' => 'round', 'collar' => 'none', 'sleeve' => 'puff',
            'use' => 'daily', 'gathers' => 6];
    }
}
