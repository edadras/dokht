<?php

namespace App\Services\Pattern\Generators;

/** شومیز کژوال: راحت، یقهٔ ایستادهٔ کوتاه و آستینِ سه‌ربع. */
class BlouseCasualGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_casual';
    }

    public function label(): string
    {
        return 'شومیز کژوال';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-casual', 'title' => 'شومیز کژوال', 'fit' => 'loose',
            'neckline' => 'scoop', 'collar' => 'stand', 'sleeve' => 'three_quarter', 'use' => 'daily',
            'bust_dart' => false];
    }
}
