<?php

namespace App\Services\Pattern\Generators;

/** جاگرِ راحتی: کمرِ کشی و دمِ پاچهٔ کشباف، گشادتر از جاگرِ ورزشی. */
class LoungeJoggerGenerator extends PantsJoggerGenerator
{
    /** این مدل در فهرست، زیر «زیر و راحتی» می‌نشیند نه زیر «پایین‌تنه». */
    public static function group(): string
    {
        return 'sleepwear';
    }

    public static function key(): string
    {
        return 'lounge_jogger';
    }

    public function label(): string
    {
        return 'شلوار جاگر راحتی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 16,
            'cuff_ease' => 8,
        ]);
    }
}
