<?php

namespace App\Services\Pattern\Generators;

/** تونیکِ ساحلی: کوتاه‌تر و جمع‌تر از کفتان، روی مایو. */
class BeachTunicGenerator extends BeachCoverKaftanGenerator
{
    public static function key(): string
    {
        return 'beach_tunic';
    }

    public function label(): string
    {
        return 'تونیک ساحلی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 18,
            'ease_extra' => 3.5,
            'underarm_drop' => 7,
        ]);
    }
}
