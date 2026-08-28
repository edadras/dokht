<?php

namespace App\Services\Pattern\Generators;

/** شومیز اسپرت: یقهٔ ایستاده، آستینِ کوتاه، جیبِ سینه و فرمِ راحت. */
class BlouseSportGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_sport';
    }

    public function label(): string
    {
        return 'شومیز اسپرت';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-sport', 'title' => 'شومیز اسپرت', 'fit' => 'loose',
            'neckline' => 'round', 'collar' => 'stand', 'sleeve' => 'short', 'use' => 'daily',
            'pocket' => true, 'bust_dart' => false];
    }
}
