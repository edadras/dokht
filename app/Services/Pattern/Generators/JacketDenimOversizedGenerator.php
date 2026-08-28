<?php

namespace App\Services\Pattern\Generators;

/** کتِ جینِ اورسایز با سرشانهٔ افتاده. */
class JacketDenimOversizedGenerator extends JacketWorkGenerator
{
    public static function key(): string
    {
        return 'jacket_denim_oversized';
    }

    public function label(): string
    {
        return 'کت جین اورسایز';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 6.5,
            'armhole_depth_extra' => 7,
            'shoulder_slope' => 3,
        ]);
    }
}
