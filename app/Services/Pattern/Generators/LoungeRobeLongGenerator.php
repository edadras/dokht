<?php

namespace App\Services\Pattern\Generators;

/** روبِ بلند تا مچِ پا با هم‌پوشانیِ بیشترِ جلو. */
class LoungeRobeLongGenerator extends SleepRobeGenerator
{
    public static function key(): string
    {
        return 'lounge_robe_long';
    }

    public function label(): string
    {
        return 'روب بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 95,
            'sleeve_length' => 56,
            'overlap' => 18,
            'band_width' => 8,
            'ease_extra' => 5,
        ]);
    }
}
