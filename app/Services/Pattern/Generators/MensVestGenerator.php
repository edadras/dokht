<?php

namespace App\Services\Pattern\Generators;

/** جلیقهٔ مردانه: بی‌آستین، جلو دکمه‌دار و پشتِ آستری با بندِ تنظیم. */
class MensVestGenerator extends VestSingleGenerator
{
    public static function key(): string
    {
        return 'mens_vest';
    }

    public function label(): string
    {
        return 'جلیقه مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            "neck_width_extra" => 1.5,
        ]);
    }
}
