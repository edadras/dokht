<?php

namespace App\Services\Pattern\Generators;

/** بیکینیِ ورزشی: فنجانِ ثابت (نه لغزنده) و شورتِ پوششِ معمولی. */
class SwimBikiniSportGenerator extends SwimBikiniTriangleGenerator
{
    public static function key(): string
    {
        return 'swim_bikini_sport';
    }

    public function label(): string
    {
        return 'بیکینی ورزشی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'sliding' => false,
            'cup_width' => 22,
            'cup_height' => 17,
            'rise_drop' => 5,
            'leg_rise' => 5,
        ]);
    }
}
