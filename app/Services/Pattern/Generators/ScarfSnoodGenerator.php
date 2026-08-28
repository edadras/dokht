<?php

namespace App\Services\Pattern\Generators;

/** گردن‌پوشِ کوتاهِ کشباف. */
class ScarfSnoodGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'scarf_snood';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'scarf_snood',
            'title' => 'گردن‌پوش',
            'kind' => 'scarf',
            'parts' => [
                ['form' => 'tube', 'code' => 'body', 'name' => 'گردن‌پوش', 'w' => 56.0, 'h' => 34.0, 'cut' => 1],
            ],
        ];
    }
}
