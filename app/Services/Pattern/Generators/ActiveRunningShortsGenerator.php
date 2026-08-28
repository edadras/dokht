<?php

namespace App\Services\Pattern\Generators;

/** شلوارکِ دو: کوتاه، گشاد، با کمرِ کشی و بند. */
class ActiveRunningShortsGenerator extends ActiveTrackPantsGenerator
{
    public static function key(): string
    {
        return 'active_running_shorts';
    }

    public function label(): string
    {
        return 'شلوارک دو';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length_extra' => -52,
            'thigh_ease' => 18,
            'ankle' => 'open',
            'hem_ease' => 20,
        ]);
    }
}
