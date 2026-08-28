<?php

namespace App\Services\Pattern\Generators;

/** شورتِ بیکینی: کمرِ پایین‌تر، درزِ پهلوی کوتاه‌تر، پوششِ معمولی. */
class PantyBikiniGenerator extends PantyBriefGenerator
{
    public static function key(): string
    {
        return 'panty_bikini';
    }

    public function label(): string
    {
        return 'شورت بیکینی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'rise_drop' => 7,
            'side_seam' => 4,
            'coverage' => 'medium',
            'seat' => 3,
        ]);
    }
}
