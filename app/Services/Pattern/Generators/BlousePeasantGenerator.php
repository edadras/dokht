<?php

namespace App\Services\Pattern\Generators;

/** شومیزِ گشادِ چین‌دار با یقهٔ کشی و آستینِ اسقفی. */
class BlousePeasantGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_peasant';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_peasant', 'title' => 'شومیز روستایی',
            'fit' => 'loose', 'neckline' => 'round', 'collar' => 'none', 'sleeve' => 'bishop',
            'use' => 'summer', 'gathers' => 16, 'ruffle' => 5, 'bust_dart' => false];
    }
}
