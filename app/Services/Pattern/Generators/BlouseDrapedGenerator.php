<?php

namespace App\Services\Pattern\Generators;

/** چینِ نرم روی یک پهلو جمع می‌شود. */
class BlouseDrapedGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_draped';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_draped', 'title' => 'شومیز دراپه',
            'fit' => 'fitted', 'neckline' => 'scoop', 'collar' => 'none', 'sleeve' => 'three_quarter',
            'use' => 'party', 'gathers' => 9];
    }
}
