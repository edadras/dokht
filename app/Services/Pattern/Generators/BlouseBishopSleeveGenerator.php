<?php

namespace App\Services\Pattern\Generators;

/** آستینِ اسقفی: از سرشانه صاف و از مچ پرچین. */
class BlouseBishopSleeveGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_bishop_sleeve';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_bishop_sleeve', 'title' => 'شومیز آستین‌اسقفی',
            'fit' => 'regular', 'neckline' => 'round', 'collar' => 'stand', 'sleeve' => 'bishop',
            'use' => 'daily'];
    }
}
