<?php

namespace App\Services\Pattern\Generators;

/** شومیز کراپ: تا خط کمر یا بالاتر تمام می‌شود؛ لبهٔ پایین صاف است، نه گرد. */
class BlouseCropGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_crop';
    }

    public function label(): string
    {
        return 'شومیز کراپ';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-crop', 'title' => 'شومیز کراپ', 'fit' => 'regular',
            'neckline' => 'round', 'collar' => 'none', 'sleeve' => 'short', 'use' => 'daily',
            'body_length' => 0];
    }
}
