<?php

namespace App\Services\Pattern\Generators;

/** باکسرِ گشاد: پاچهٔ بلندتر و کمرهٔ پهن‌تر؛ برای خواب و خانه. */
class BoxerLooseGenerator extends BoxerBriefGenerator
{
    public static function key(): string
    {
        return 'boxer_loose';
    }

    public function label(): string
    {
        return 'شورت باکسر گشاد';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'leg_length' => 22,
            'rise_drop' => 3,
            'gusset' => 12,
            'waistband_height' => 5,
            'stretch' => 1.0,
        ]);
    }
}
