<?php

namespace App\Services\Pattern\Generators;

/** جلیقهٔ کشبافِ روی پیراهن. */
class KnitVestGenerator extends VestSingleGenerator
{
    public static function key(): string
    {
        return 'knit_vest';
    }

    public function label(): string
    {
        return 'جلیقه کشباف';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 3,
        ]);
    }
}
