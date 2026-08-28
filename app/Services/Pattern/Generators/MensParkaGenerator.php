<?php

namespace App\Services\Pattern\Generators;

/** پارکای مردانه: بلند، کلاه‌دار و گشاد؛ روی چند لایه پوشیده می‌شود. */
class MensParkaGenerator extends JacketParkaGenerator
{
    public static function key(): string
    {
        return 'mens_parka';
    }

    public function label(): string
    {
        return 'پارکا مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_width_extra' => 2.5,
            'armhole_depth_extra' => 7.0,
        ]);
    }
}
