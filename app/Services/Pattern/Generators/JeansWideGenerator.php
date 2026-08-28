<?php

namespace App\Services\Pattern\Generators;

/** جینِ گشادِ کمربلند با پاچهٔ یک‌دست. */
class JeansWideGenerator extends PantsPalazzoGenerator
{
    public static function key(): string
    {
        return 'jeans_wide';
    }

    public function label(): string
    {
        return 'شلوار جین گشاد';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 16,
            'knee_ease' => 22,
            'hem_ease' => 24,
        ]);
    }
}
