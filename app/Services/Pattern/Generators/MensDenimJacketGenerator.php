<?php

namespace App\Services\Pattern\Generators;

/** کت جین: کوتاه، با یوکِ سینه، دو جیبِ دردار و نوارِ کمرِ دکمه‌دار. */
class MensDenimJacketGenerator extends JacketWorkGenerator
{
    public static function key(): string
    {
        return 'mens_denim_jacket';
    }

    public function label(): string
    {
        return 'کت جین مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_width_extra' => 2.0,
            'armhole_depth_extra' => 5.0,
        ]);
    }
}
