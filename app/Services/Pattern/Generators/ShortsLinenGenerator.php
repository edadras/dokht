<?php

namespace App\Services\Pattern\Generators;

/** شلوارکِ کتان با کمرِ چین‌دار و بندِ کمر. */
class ShortsLinenGenerator extends ShortsPaperbagGenerator
{
    public static function key(): string
    {
        return 'shorts_linen';
    }

    public function label(): string
    {
        return 'شلوارک کتان کمرچین';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 16,
            'hem_ease' => 18,
        ]);
    }
}
