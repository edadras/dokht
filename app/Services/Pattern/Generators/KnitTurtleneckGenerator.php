<?php

namespace App\Services\Pattern\Generators;

/** یقه‌اسکیِ کشبافِ چسبان. */
class KnitTurtleneckGenerator extends TshirtLongSleeveGenerator
{
    public static function key(): string
    {
        return 'knit_turtleneck';
    }

    public function label(): string
    {
        return 'یقه‌اسکی کشباف';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 0.5,
            'neck_width_extra' => 0,
        ]);
    }
}
