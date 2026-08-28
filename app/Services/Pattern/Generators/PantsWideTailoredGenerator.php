<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ پارچه‌ایِ گشادِ کمربلند با پیلیِ جلو — همان شلوارِ اداریِ امروزی. */
class PantsWideTailoredGenerator extends PantsPleatedGenerator
{
    public static function key(): string
    {
        return 'pants_wide_tailored';
    }

    public function label(): string
    {
        return 'شلوار پارچه‌ای گشاد';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 20,
            'knee_ease' => 24,
            'hem_ease' => 26,
            'front_pleats' => 2,
        ]);
    }
}
