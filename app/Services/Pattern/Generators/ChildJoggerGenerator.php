<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ جاگرِ بچگانه با دمِ پاچهٔ کشباف. */
class ChildJoggerGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_jogger';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_jogger',
            'title' => 'شلوار جاگر بچگانه',
            'form' => 'pants',
            'use' => 'play',
            'play' => 2.5,
            'knee_ease' => 12,
            'hem_ease' => 12,
            'rib' => true,
        ];
    }
}
