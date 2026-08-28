<?php

namespace App\Services\Pattern\Generators;

/** کاپشنِ لحافیِ نازک با کانالهٔ ریز. */
class JacketQuiltedGenerator extends JacketPufferGenerator
{
    public static function key(): string
    {
        return 'jacket_quilted';
    }

    public function label(): string
    {
        return 'کاپشن لحافی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 30,
            'baffle_spacing' => 7,
            'ease_extra' => 3,
        ]);
    }
}
