<?php

namespace App\Services\Pattern\Generators;

/** شلوارکِ بچگانه با کمرِ کشی. */
class ChildShortsGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_shorts';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_shorts',
            'title' => 'شلوارک بچگانه',
            'form' => 'pants',
            'use' => 'play',
            'knee_ease' => 12,
            'hem_ease' => 14,
            'leg_length' => 26,
        ];
    }
}
