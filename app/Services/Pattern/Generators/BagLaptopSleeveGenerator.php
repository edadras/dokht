<?php

namespace App\Services\Pattern\Generators;

/** جلدِ نرمِ رایانه با لایهٔ ضربه‌گیر. */
class BagLaptopSleeveGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'bag_laptop_sleeve';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'bag_laptop_sleeve',
            'title' => 'جلد رایانه',
            'kind' => 'bag',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'تنه جلد', 'w' => 36.0, 'h' => 26.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'padding', 'name' => 'لایه ضربه‌گیر', 'w' => 36.0, 'h' => 26.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'flap', 'name' => 'در جلد', 'w' => 36.0, 'h' => 12.0, 'cut' => 2],
            ],
        ];
    }
}
