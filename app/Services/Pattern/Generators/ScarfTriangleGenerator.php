<?php

namespace App\Services\Pattern\Generators;

/** شالِ سه‌گوشِ روی شانه. */
class ScarfTriangleGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'scarf_triangle';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'scarf_triangle',
            'title' => 'شال سه‌گوش',
            'kind' => 'scarf',
            'parts' => [
                ['form' => 'tri', 'code' => 'body', 'name' => 'شال سه‌گوش', 'w' => 170.0, 'h' => 78.0, 'cut' => 1],
            ],
        ];
    }
}
