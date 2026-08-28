<?php

namespace App\Services\Pattern\Generators;

/** ساق‌شلواریِ بچگانه از پارچهٔ کشی. */
class ChildLeggingsGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_leggings';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_leggings',
            'title' => 'ساق‌شلواری بچگانه',
            'form' => 'pants',
            'use' => 'play',
            'play' => 0.5,
            'knee_ease' => 2,
            'hem_ease' => 2,
            'elastic_ratio' => 0.86,
            'notes' => ['فقط با پارچه کشی بریده می‌شود.'],
        ];
    }
}
