<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ راستهٔ تمام‌قد که از باسن تا دمِ پا یک پهنا دارد. */
class PantsStraightFullGenerator extends PantsCigaretteGenerator
{
    public static function key(): string
    {
        return 'pants_straight_full';
    }

    public function label(): string
    {
        return 'شلوار راسته بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 12,
            'crop' => 0,
            'knee_ease' => 14,
            'hem_ease' => 14,
        ]);
    }
}
