<?php

namespace App\Services\Pattern\Generators;

/** تاپِ کرستی با درزهای عمودی و لبهٔ بالای صاف. */
class BlouseCorsetTopGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_corset_top';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_corset_top', 'title' => 'تاپ کرستی',
            'fit' => 'fitted', 'neckline' => 'straight', 'collar' => 'none', 'sleeve' => 'none',
            'use' => 'party', 'body_length' => 4];
    }
}
