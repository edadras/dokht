<?php

namespace App\Services\Pattern\Generators;

/** کاردیگان مردانه: جلوبازِ دکمه‌دار با نوارِ کشبافِ لبهٔ جلو. */
class MensCardiganGenerator extends CardiganGenerator
{
    public static function key(): string
    {
        return 'mens_cardigan';
    }

    public function label(): string
    {
        return 'کاردیگان مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            "neck_width_extra" => 2.0,
            "armhole_depth_extra" => 4.5,
        ]);
    }
}
