<?php

namespace App\Services\Pattern\Generators;

/** تایتِ کوتاه: تا میانهٔ ران؛ همان درفتِ تایت با پای کوتاه. */
class ActiveBikeShortsGenerator extends ActiveTightsGenerator
{
    public static function key(): string
    {
        return 'active_bike_shorts';
    }

    public function label(): string
    {
        return 'تایت کوتاه ورزشی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length_extra' => -48,
            'hem_ease' => 2,
        ]);
    }
}
