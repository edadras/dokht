<?php

namespace App\Services\Pattern\Generators;

/** کیفِ دوشیِ جمع‌شونده با درِ برگردان. */
class BagShoulderGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'bag_shoulder';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'bag_shoulder',
            'title' => 'کیف دوشی',
            'kind' => 'bag',
            'parts' => [
                ['form' => 'taper', 'code' => 'body', 'name' => 'تنه کیف', 'top' => 30.0, 'bottom' => 24.0, 'h' => 26.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'gusset', 'name' => 'کف و پهلو', 'w' => 8.0, 'h' => 76.0, 'cut' => 1],
                ['form' => 'rect', 'code' => 'flap', 'name' => 'در کیف', 'w' => 30.0, 'h' => 18.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'strap', 'name' => 'بند دوشی', 'w' => 5.0, 'h' => 110.0, 'cut' => 1],
            ],
        ];
    }
}
