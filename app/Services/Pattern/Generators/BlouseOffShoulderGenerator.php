<?php

namespace App\Services\Pattern\Generators;

/** خطِ بالا از سرشانه پایین می‌افتد و با کش روی بازو می‌ایستد. */
class BlouseOffShoulderGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_off_shoulder';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_off_shoulder', 'title' => 'شومیز آستین‌افتاده',
            'fit' => 'regular', 'neckline' => 'off_shoulder', 'collar' => 'none', 'sleeve' => 'flutter',
            'use' => 'summer', 'gathers' => 8, 'bust_dart' => false];
    }
}
