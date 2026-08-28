<?php

namespace App\Services\Pattern\Generators;

/** جاگرِ ورزشی: پایِ جمع‌تر از گرمکن با مچِ کشباف. */
class ActiveSportJoggerGenerator extends ActiveTrackPantsGenerator
{
    public static function key(): string
    {
        return 'active_sport_jogger';
    }

    public function label(): string
    {
        return 'شلوار جاگر ورزشی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 12,
            'knee_ease' => 6,
            'cuff_ease' => 5,
        ]);
    }
}
