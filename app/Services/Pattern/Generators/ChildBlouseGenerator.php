<?php

namespace App\Services\Pattern\Generators;

/** شومیزِ بچگانه: جلوباز، آستینِ کوتاه، لبهٔ کمی باز. */
class ChildBlouseGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_blouse';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_blouse',
            'title' => 'شومیز بچگانه',
            'form' => 'top',
            'use' => 'party',
            'length' => 14,
            'shape' => 'straight',
            'opening' => 'button',
            'buttons' => 4,
            'collar' => 'turn',
            'collar_height' => 4,
            'sleeve_length' => 18,
            'hem_flare' => 4,
        ];
    }
}
