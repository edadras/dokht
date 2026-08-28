<?php

namespace App\Services\Pattern\Generators;

/** پالتوی مردانه: بلند تا زیر زانو، روی کت پوشیده می‌شود پس آزادی‌اش بیشتر است. */
class MensOvercoatGenerator extends CoatOvercoatGenerator
{
    public static function key(): string
    {
        return 'mens_overcoat';
    }

    public function label(): string
    {
        return 'پالتو مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_width_extra' => 2.5,
            'armhole_depth_extra' => 6.0,
        ]);
    }
}
