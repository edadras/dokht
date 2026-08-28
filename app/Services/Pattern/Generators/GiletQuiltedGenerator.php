<?php

namespace App\Services\Pattern\Generators;

/** جلیقهٔ لحافیِ بی‌آستین روی لباس. */
class GiletQuiltedGenerator extends VestUtilityGenerator
{
    public static function key(): string
    {
        return 'gilet_quilted';
    }

    public function label(): string
    {
        return 'جلیقه لحافی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 5,
        ]);
    }
}
