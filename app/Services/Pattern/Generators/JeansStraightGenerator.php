<?php

namespace App\Services\Pattern\Generators;

/** جینِ راستهٔ کلاسیک. */
class JeansStraightGenerator extends PantsCigaretteGenerator
{
    public static function key(): string
    {
        return 'jeans_straight';
    }

    public function label(): string
    {
        return 'شلوار جین راسته';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 8,
            'crop' => 0,
            'knee_ease' => 8,
            'hem_ease' => 8,
        ]);
    }
}
