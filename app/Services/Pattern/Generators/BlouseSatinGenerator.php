<?php

namespace App\Services\Pattern\Generators;

/** شومیزِ ساتنِ روان با یقهٔ باز و آستینِ بلند؛ پارچهٔ لیز، درزِ باریک. */
class BlouseSatinGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_satin';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_satin', 'title' => 'شومیز ساتن',
            'fit' => 'regular', 'neckline' => 'v', 'collar' => 'none', 'sleeve' => 'long',
            'use' => 'party', 'bust_dart' => false,
            'notes' => ['ساتن زیرِ سوزن سُر می‌خورد؛ با سوزنِ ریز و کاغذِ زیرِ درز بدوزید.']];
    }
}
