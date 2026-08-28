<?php

namespace App\Services\Pattern\Generators;

/** کت تک مردانه: دو دکمه، یقهٔ انگلیسی و سرشانهٔ پهن‌تر از کتِ زنانه. */
class MensBlazerGenerator extends BlazerGenerator
{
    public static function key(): string
    {
        return 'mens_blazer';
    }

    public function label(): string
    {
        return 'کت تک مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_width_extra' => 2.0,
            'armhole_depth_extra' => 4.0,
        ]);
    }
}
