<?php

namespace App\Services\Pattern\Generators;

/** کلاهِ آفتابیِ لبه‌پهن. */
class HatSunGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'hat_sun';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'hat_sun',
            'title' => 'کلاه آفتابی',
            'kind' => 'hat',
            'parts' => [
                ['form' => 'disc', 'code' => 'crown', 'name' => 'تاج کلاه', 'r' => 'head/6.6', 'cut' => 1, 'fold' => true],
                ['form' => 'rect', 'code' => 'side', 'name' => 'دیواره کلاه', 'w' => 'head+2', 'h' => 11.0, 'cut' => 1],
                ['form' => 'ring', 'code' => 'brim', 'name' => 'لبه پهن', 'r' => 'head/6.3', 'width' => 12.0, 'cut' => 4],
            ],
        ];
    }
}
