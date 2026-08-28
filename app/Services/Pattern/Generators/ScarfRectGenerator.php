<?php

namespace App\Services\Pattern\Generators;

/** شالِ مستطیلِ بلند. */
class ScarfRectGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'scarf_rect';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'scarf_rect',
            'title' => 'شال مستطیل',
            'kind' => 'scarf',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'شال', 'w' => 70.0, 'h' => 190.0, 'cut' => 1],
            ],
        ];
    }
}
