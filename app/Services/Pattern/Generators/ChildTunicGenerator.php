<?php

namespace App\Services\Pattern\Generators;

/** تونیکِ بچگانه: بلندتر از تی‌شرت، روی شلوار. */
class ChildTunicGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_tunic';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_tunic',
            'title' => 'تونیک بچگانه',
            'form' => 'top',
            'use' => 'daily',
            'length' => 26,
            'length_max' => 60,
            'shape' => 'straight',
            'opening' => 'closed',
            'sleeve_length' => 34,
            'hem_flare' => 4,
            'pocket' => true,
        ];
    }
}
