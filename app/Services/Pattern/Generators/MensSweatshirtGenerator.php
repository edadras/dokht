<?php

namespace App\Services\Pattern\Generators;

/** سویشرت مردانه: بی‌کلاه، با نوارِ کشبافِ لبه و مچ. */
class MensSweatshirtGenerator extends HoodieSweatshirtGenerator
{
    public static function key(): string
    {
        return 'mens_sweatshirt';
    }

    public function label(): string
    {
        return 'سویشرت مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            "neck_width_extra" => 2.5,
            "armhole_depth_extra" => 5.5,
            "body_length" => 22,
        ]);
    }
}
