<?php

namespace App\Services\Pattern\Generators;

/** چشم‌بندِ خواب با بندِ کشی. */
class AccessoryEyeMaskGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'accessory_eye_mask';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'accessory_eye_mask',
            'title' => 'چشم‌بند خواب',
            'kind' => 'other',
            'parts' => [
                ['form' => 'taper', 'code' => 'body', 'name' => 'تنه چشم‌بند', 'top' => 20.0, 'bottom' => 16.0, 'h' => 10.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'padding', 'name' => 'لایه نرم', 'w' => 20.0, 'h' => 10.0, 'cut' => 1],
                ['form' => 'rect', 'code' => 'strap', 'name' => 'بند کشی', 'w' => 2.5, 'h' => 34.0, 'cut' => 1],
            ],
        ];
    }
}
