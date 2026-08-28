<?php

namespace App\Services\Pattern\Generators;

/** پیژامهٔ تابستانی: همان دوختِ پیژامه با آستین و شلوارکِ کوتاه. */
class SleepPajamaShortGenerator extends SleepPajamaGenerator
{
    public static function key(): string
    {
        return 'sleep_pajama_short';
    }

    public function label(): string
    {
        return 'ست پیژامه تابستانی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'sleeve_length' => 16,
            'pants_length' => -50,
            'top_length' => 12,
            'collar_height' => 0,
        ]);
    }
}
