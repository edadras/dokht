<?php

namespace App\Services\Pattern\Generators;

/** مچ‌پوشِ بلندِ بی‌انگشت. */
class GloveArmWarmerGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'glove_arm_warmer';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'glove_arm_warmer',
            'title' => 'مچ‌پوش بلند',
            'kind' => 'warmer',
            'parts' => [
                ['form' => 'taper', 'code' => 'body', 'name' => 'ساقه مچ‌پوش', 'top' => 'bicep-2', 'bottom' => 'wrist-1', 'h' => 34.0, 'cut' => 2],
            ],
            'notes' => ['فقط با پارچهٔ کشی؛ جای شست پس از دوختِ درز، چهار سانتی‌متر باز گذاشته می‌شود.'],
        ];
    }
}
