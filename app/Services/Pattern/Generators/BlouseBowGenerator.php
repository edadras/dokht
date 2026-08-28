<?php

namespace App\Services\Pattern\Generators;

/** یقهٔ روبانی که جلو گره می‌خورد — همان شومیزِ اداریِ کلاسیک. */
class BlouseBowGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_bow';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_bow', 'title' => 'شومیز پاپیونی',
            'fit' => 'regular', 'neckline' => 'round', 'collar' => 'tie', 'sleeve' => 'long',
            'use' => 'office'];
    }
}
