<?php

namespace App\Services\Pattern\Generators;

/** کیفِ لوازم با زیپِ سرتاسری. */
class BagPouchGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'bag_pouch';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'bag_pouch',
            'title' => 'کیف لوازم',
            'kind' => 'bag',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'تنه کیف', 'w' => 24.0, 'h' => 16.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'lining', 'name' => 'آستر', 'w' => 24.0, 'h' => 16.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'tab', 'name' => 'زبانه دو سر زیپ', 'w' => 5.0, 'h' => 6.0, 'cut' => 2],
            ],
        ];
    }
}
