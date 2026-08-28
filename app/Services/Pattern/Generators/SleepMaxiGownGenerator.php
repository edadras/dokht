<?php

namespace App\Services\Pattern\Generators;

/** لباسِ خوابِ بلند تا مچِ پا با دامنِ بازتر. */
class SleepMaxiGownGenerator extends SleepNightgownGenerator
{
    public static function key(): string
    {
        return 'sleep_maxi_gown';
    }

    public function label(): string
    {
        return 'لباس خواب بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 92,
            'hem_flare' => 14,
            'top_shape' => 'scoop',
            'strap_width' => 2.4,
        ]);
    }
}
