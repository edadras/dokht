<?php

namespace App\Services\Pattern\Generators;

/** بمبرِ مردانه: کوتاه، با نوارِ کشبافِ یقه و لبه و مچ. */
class MensBomberGenerator extends CoatBomberGenerator
{
    public static function key(): string
    {
        return 'mens_bomber';
    }

    public function label(): string
    {
        return 'کاپشن بمبر مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_width_extra' => 2.5,
            'armhole_depth_extra' => 6.0,
        ]);
    }
}
