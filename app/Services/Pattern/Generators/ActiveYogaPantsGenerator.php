<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ یوگا: از زانو به پایین باز، بی‌مچِ کشباف. */
class ActiveYogaPantsGenerator extends ActiveTrackPantsGenerator
{
    public static function key(): string
    {
        return 'active_yoga_pants';
    }

    public function label(): string
    {
        return 'شلوار یوگا';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 12,
            'ankle' => 'open',
            'hem_ease' => 22,
            'knee_ease' => 12,
        ]);
    }
}
