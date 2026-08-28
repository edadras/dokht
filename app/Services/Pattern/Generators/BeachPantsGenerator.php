<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ ساحلی: پاچهٔ بسیار گشاد از پارچهٔ سبک، کمرِ کشی. */
class BeachPantsGenerator extends PantsPalazzoGenerator
{
    /** این مدل در فهرست، زیر «شنا و ساحلی» می‌نشیند نه زیر «پایین‌تنه». */
    public static function group(): string
    {
        return 'beach';
    }

    public static function key(): string
    {
        return 'beach_pants';
    }

    public function label(): string
    {
        return 'شلوار ساحلی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 22,
            'knee_ease' => 34,
            'hem_ease' => 44,
        ]);
    }
}
