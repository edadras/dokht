<?php

namespace App\Services\Pattern\Generators;

/** روبِ کوتاه تا بالای ران، با آستینِ سه‌ربع. */
class LoungeRobeShortGenerator extends SleepRobeGenerator
{
    public static function key(): string
    {
        return 'lounge_robe_short';
    }

    public function label(): string
    {
        return 'روب کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 30,
            'sleeve_length' => 38,
            'overlap' => 10,
        ]);
    }
}
