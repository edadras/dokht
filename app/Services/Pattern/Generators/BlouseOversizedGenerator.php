<?php

namespace App\Services\Pattern\Generators;

/**
 * شومیز اورسایز.
 *
 * گشادی‌اش از آزادیِ بیشتر نمی‌آید، از *جای حلقه* می‌آید: سرشانه روی بازو
 * می‌افتد و حلقه پایین‌تر می‌رود. بی این دو، لباسِ گشاد فقط «بزرگ» است نه
 * اورسایز.
 */
class BlouseOversizedGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_oversized';
    }

    public function label(): string
    {
        return 'شومیز اورسایز';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-oversized', 'title' => 'شومیز اورسایز', 'fit' => 'boxy',
            'neckline' => 'round', 'collar' => 'shirt', 'sleeve' => 'long', 'use' => 'daily',
            'armhole' => 6, 'bust_dart' => false, 'body_length' => 22,
            'defaults' => ['drop_shoulder' => 5]];
    }
}
