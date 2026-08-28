<?php

namespace App\Services\Pattern\Generators;

/** پولوشرت مردانه: همان پولو با سرشانهٔ پهن‌تر و تنهٔ بلندتر. */
class MensPoloGenerator extends PoloShirtGenerator
{
    public static function key(): string
    {
        return 'mens_polo';
    }

    public function label(): string
    {
        return 'پولوشرت مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            "neck_width_extra" => 2.0,
            "armhole_depth_extra" => 4.0,
            "body_length" => 26,
        ]);
    }
}
