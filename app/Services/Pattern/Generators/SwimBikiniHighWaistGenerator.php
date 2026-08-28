<?php

namespace App\Services\Pattern\Generators;

/** بیکینیِ کمرِ بلند: شورتِ کمرِ بالا با پوششِ کامل. */
class SwimBikiniHighWaistGenerator extends SwimBikiniTriangleGenerator
{
    public static function key(): string
    {
        return 'swim_bikini_high_waist';
    }

    public function label(): string
    {
        return 'بیکینی کمر بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'rise_drop' => 0,
            'leg_rise' => 3,
            'coverage' => 'full',
            'cup_width' => 20,
            'cup_height' => 18,
        ]);
    }
}
