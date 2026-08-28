<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ بچگانه با کمرِ کشی و پای راسته. */
class ChildPantsGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_pants';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_pants',
            'title' => 'شلوار بچگانه',
            'form' => 'pants',
            'use' => 'daily',
            'knee_ease' => 9,
            'hem_ease' => 8,
        ];
    }
}
