<?php

namespace App\Services\Pattern\Generators;

/** بندِ کمرِ گره‌ای برای مانتو و ربدوشامبر. */
class BeltTieGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'belt_tie';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'belt_tie',
            'title' => 'بند کمر گره‌ای',
            'kind' => 'belt',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'بند کمر', 'w' => 'waist+90', 'h' => 10.0, 'cut' => 1],
            ],
        ];
    }
}
