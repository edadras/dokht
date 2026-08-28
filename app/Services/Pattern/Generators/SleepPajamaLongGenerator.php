<?php

namespace App\Services\Pattern\Generators;

/** پیژامهٔ زمستانی: آستینِ بلند، شلوارِ تا مچ و یقهٔ ایستادهٔ بلندتر. */
class SleepPajamaLongGenerator extends SleepPajamaGenerator
{
    public static function key(): string
    {
        return 'sleep_pajama_long';
    }

    public function label(): string
    {
        return 'ست پیژامه زمستانی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'sleeve_length' => 56,
            'pants_length' => 0,
            'top_length' => 18,
            'collar_height' => 5,
            'ease_extra' => 4.5,
        ]);
    }
}
