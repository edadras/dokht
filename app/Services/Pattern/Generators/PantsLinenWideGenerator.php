<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ کتانِ گشادِ تابستانی با کمرِ کشی. */
class PantsLinenWideGenerator extends PantsPalazzoGenerator
{
    public static function key(): string
    {
        return 'pants_linen_wide';
    }

    public function label(): string
    {
        return 'شلوار کتان گشاد';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 20,
            'knee_ease' => 30,
            'hem_ease' => 38,
        ]);
    }
}
