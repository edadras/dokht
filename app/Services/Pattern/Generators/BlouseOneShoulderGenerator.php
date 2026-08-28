<?php

namespace App\Services\Pattern\Generators;

/** یک سرشانه دارد و سرشانهٔ دیگر باز است. */
class BlouseOneShoulderGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_one_shoulder';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_one_shoulder', 'title' => 'شومیز یک‌شانه',
            'fit' => 'fitted', 'neckline' => 'one_shoulder', 'collar' => 'none', 'sleeve' => 'none',
            'use' => 'party'];
    }
}
