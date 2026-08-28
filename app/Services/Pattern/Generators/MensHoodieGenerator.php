<?php

namespace App\Services\Pattern\Generators;

/** هودی مردانه: گشادتر، با کلاه و جیبِ کانگورویی. */
class MensHoodieGenerator extends HoodieGenerator
{
    public static function key(): string
    {
        return 'mens_hoodie';
    }

    public function label(): string
    {
        return 'هودی مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            "neck_width_extra" => 2.5,
            "armhole_depth_extra" => 6.0,
            "body_length" => 24,
        ]);
    }
}
