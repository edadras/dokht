<?php

namespace App\Services\Pattern\Generators;

/** آستینِ خفاشیِ یک‌سره با تنه. */
class BlouseBatwingGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_batwing';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_batwing', 'title' => 'شومیز خفاشی',
            'fit' => 'loose', 'neckline' => 'boat', 'collar' => 'none', 'sleeve' => 'batwing',
            'use' => 'daily', 'bust_dart' => false];
    }
}
