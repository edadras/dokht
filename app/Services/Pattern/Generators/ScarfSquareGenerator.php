<?php

namespace App\Services\Pattern\Generators;

/** روسریِ چهارگوش. */
class ScarfSquareGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'scarf_square';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'scarf_square',
            'title' => 'روسری چهارگوش',
            'kind' => 'scarf',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'روسری', 'w' => 110.0, 'h' => 110.0, 'cut' => 1],
            ],
        ];
    }
}
