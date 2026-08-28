<?php

namespace App\Services\Pattern\Generators;

/** لباسِ خوابِ کوتاه: تا میانهٔ ران، با بندِ باریک. */
class SleepChemiseGenerator extends SleepNightgownGenerator
{
    public static function key(): string
    {
        return 'sleep_chemise';
    }

    public function label(): string
    {
        return 'لباس خواب کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 30,
            'hem_flare' => 5,
            'top_drop' => 8,
            'back_drop' => 12,
        ]);
    }
}
