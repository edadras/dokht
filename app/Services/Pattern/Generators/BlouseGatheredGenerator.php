<?php

namespace App\Services\Pattern\Generators;

/** شومیز چین‌دار: چین روی یوکِ سرشانه، پس حجم از بالا می‌آید نه از پهلو. */
class BlouseGatheredGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_gathered';
    }

    public function label(): string
    {
        return 'شومیز چین‌دار';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-gathered', 'title' => 'شومیز چین‌دار', 'fit' => 'loose',
            'neckline' => 'round', 'collar' => 'none', 'sleeve' => 'puff', 'use' => 'daily',
            'gathers' => 10, 'bust_dart' => false];
    }
}
