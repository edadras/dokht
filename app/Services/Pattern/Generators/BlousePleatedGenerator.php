<?php

namespace App\Services\Pattern\Generators;

/** شومیز پیلی‌دار: پیلیِ تیغه‌ای روی جلو؛ برخلاف چین، پیلی اتو می‌خورد و می‌ایستد. */
class BlousePleatedGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_pleated';
    }

    public function label(): string
    {
        return 'شومیز پیلی‌دار';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-pleated', 'title' => 'شومیز پیلی‌دار', 'fit' => 'regular',
            'neckline' => 'round', 'collar' => 'shirt', 'sleeve' => 'long', 'use' => 'office',
            'gathers' => 6];
    }
}
