<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ کمربلندِ راسته که روی گودیِ کمر می‌نشیند. */
class PantsHighWaistStraightGenerator extends PantsCigaretteGenerator
{
    public static function key(): string
    {
        return 'pants_high_waist_straight';
    }

    public function label(): string
    {
        return 'شلوار کمربلند راسته';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'rise' => 'high',
            'rise_extra' => 1,
            'thigh_ease' => 10,
            'crop' => 0,
            'knee_ease' => 11,
            'hem_ease' => 11,
        ]);
    }
}
