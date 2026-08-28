<?php

namespace App\Services\Pattern\Generators;

/** شومیز بلند: تا روی باسن؛ زیرِ کت یا با لگ پوشیده می‌شود. */
class BlouseLonglineGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_longline';
    }

    public function label(): string
    {
        return 'شومیز بلند';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-longline', 'title' => 'شومیز بلند', 'fit' => 'regular',
            'neckline' => 'round', 'collar' => 'shirt', 'sleeve' => 'long', 'use' => 'daily',
            'body_length' => 32];
    }
}
