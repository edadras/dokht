<?php

namespace App\Services\Pattern\Generators;

/** بندِ گردنی و پشتِ باز. */
class BlouseHalterGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_halter';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_halter', 'title' => 'شومیز هالتر',
            'fit' => 'fitted', 'neckline' => 'halter', 'collar' => 'none', 'sleeve' => 'none',
            'use' => 'party'];
    }
}
