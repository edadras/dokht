<?php

namespace App\Services\Pattern\Generators;

/** شلوارکِ راحتیِ خانه: از شلوارکِ خواب گشادتر و بلندتر. */
class LoungeShortsGenerator extends SleepShortsGenerator
{
    public static function key(): string
    {
        return 'lounge_shorts';
    }

    public function label(): string
    {
        return 'شلوارک راحتی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'leg_length' => 22,
            'thigh_ease' => 10,
            'hip_ease' => 5,
            'waistband_height' => 5,
        ]);
    }
}
