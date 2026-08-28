<?php

namespace App\Services\Pattern\Generators;

/** پوپلینِ سفیدِ کلاسیک با یقهٔ برگردان و آستینِ بلند. */
class BlousePoplinGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_poplin';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_poplin', 'title' => 'شومیز پوپلین',
            'fit' => 'regular', 'neckline' => 'round', 'collar' => 'shirt', 'sleeve' => 'long',
            'use' => 'office', 'pocket' => true];
    }
}
