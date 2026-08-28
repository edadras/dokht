<?php

namespace App\Services\Pattern\Generators;

/** کیفِ کوچکِ رودوشی با بندِ بلند. */
class BagCrossbodyGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'bag_crossbody';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'bag_crossbody',
            'title' => 'کیف رودوشی',
            'kind' => 'bag',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'تنه کیف', 'w' => 22.0, 'h' => 18.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'gusset', 'name' => 'کف و پهلو', 'w' => 6.0, 'h' => 56.0, 'cut' => 1],
                ['form' => 'rect', 'code' => 'flap', 'name' => 'در کیف', 'w' => 22.0, 'h' => 14.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'strap', 'name' => 'بند بلند', 'w' => 4.0, 'h' => 130.0, 'cut' => 1],
            ],
        ];
    }
}
