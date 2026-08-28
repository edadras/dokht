<?php

namespace App\Services\Pattern\Generators;

/** رکابی مردانه: حلقهٔ باز و بندِ پهن؛ زیرپوش یا تنهاپوشِ تابستانی. */
class MensTankGenerator extends TopTankGenerator
{
    public static function key(): string
    {
        return 'mens_tank';
    }

    public function label(): string
    {
        return 'رکابی مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            "neck_width_extra" => 3.0,
            "body_length" => 26,
        ]);
    }
}
