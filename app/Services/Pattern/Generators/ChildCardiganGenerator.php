<?php

namespace App\Services\Pattern\Generators;

/** ژاکتِ جلوبازِ بچگانه با دکمه. */
class ChildCardiganGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_cardigan';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_cardigan',
            'title' => 'ژاکت بچگانه',
            'form' => 'top',
            'use' => 'daily',
            'knit' => true,
            'play' => 2.5,
            'length' => 15,
            'shape' => 'straight',
            'opening' => 'button',
            'buttons' => 5,
            'sleeve_length' => 40,
        ];
    }
}
