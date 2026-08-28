<?php

namespace App\Services\Pattern\Generators;

/** ساق‌پوشِ کشباف. */
class WarmerLegGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'warmer_leg';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'warmer_leg',
            'title' => 'ساق‌پوش',
            'kind' => 'warmer',
            'parts' => [
                ['form' => 'taper', 'code' => 'body', 'name' => 'ساق‌پوش', 'top' => 'knee-2', 'bottom' => 'ankle-1', 'h' => 42.0, 'cut' => 2],
            ],
        ];
    }
}
