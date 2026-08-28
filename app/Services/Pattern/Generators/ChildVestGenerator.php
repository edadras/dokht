<?php

namespace App\Services\Pattern\Generators;

/** جلیقهٔ بچگانه: بی‌آستین، روی پیراهن پوشیده می‌شود. */
class ChildVestGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_vest';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_vest',
            'title' => 'جلیقه بچگانه',
            'form' => 'top',
            'use' => 'school',
            'play' => 2.5,
            'length' => 14,
            'shape' => 'straight',
            'opening' => 'button',
            'buttons' => 4,
            'sleeve' => 'none',
            'sleeve_length' => 0,
        ];
    }
}
