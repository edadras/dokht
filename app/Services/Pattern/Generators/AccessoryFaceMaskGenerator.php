<?php

namespace App\Services\Pattern\Generators;

/** ماسکِ پارچه‌ای دولایه با بندِ گوش. */
class AccessoryFaceMaskGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'accessory_face_mask';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'accessory_face_mask',
            'title' => 'ماسک پارچه‌ای',
            'kind' => 'other',
            'parts' => [
                ['form' => 'taper', 'code' => 'body', 'name' => 'تنه ماسک', 'top' => 18.0, 'bottom' => 14.0, 'h' => 16.0, 'cut' => 4],
                ['form' => 'rect', 'code' => 'strap', 'name' => 'بند گوش', 'w' => 3.0, 'h' => 20.0, 'cut' => 2],
            ],
            'notes' => ['دو لایهٔ رو و دو لایهٔ آستر؛ سیمِ بینی در درزِ بالا کار گذاشته می‌شود.'],
        ];
    }
}
