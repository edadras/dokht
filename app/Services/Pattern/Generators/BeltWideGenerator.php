<?php

namespace App\Services\Pattern\Generators;

/** کمربندِ پهنِ پارچه‌ای با سگک. */
class BeltWideGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'belt_wide';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'belt_wide',
            'title' => 'کمربند پهن',
            'kind' => 'belt',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'کمربند', 'w' => 'waist+18', 'h' => 16.0, 'cut' => 1],
                ['form' => 'rect', 'code' => 'loop', 'name' => 'حلقه نگه‌دارنده', 'w' => 5.0, 'h' => 12.0, 'cut' => 1],
            ],
            'notes' => ['دولا بریده می‌شود، پس بلندی تمام‌شده هشت سانتی‌متر است؛ هجده سانتی‌متر اضافه برای بستن است.'],
        ];
    }
}
