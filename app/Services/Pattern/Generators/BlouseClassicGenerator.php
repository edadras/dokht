<?php

namespace App\Services\Pattern\Generators;

/** شومیز کلاسیک: یقهٔ مردانه، آستین بلندِ مچ‌دار، فرم معمولی و دکمهٔ سرتاسری. */
class BlouseClassicGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_classic';
    }

    public function label(): string
    {
        return 'شومیز کلاسیک';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-classic', 'title' => 'شومیز کلاسیک', 'fit' => 'regular',
            'neckline' => 'round', 'collar' => 'shirt', 'sleeve' => 'long', 'use' => 'daily'];
    }
}
