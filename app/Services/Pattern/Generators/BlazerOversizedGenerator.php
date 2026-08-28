<?php

namespace App\Services\Pattern\Generators;

/** کتِ اورسایزِ سرشانه‌افتاده — همان کتی که روی همه‌چیز پوشیده می‌شود. */
class BlazerOversizedGenerator extends BlazerGenerator
{
    public static function key(): string
    {
        return 'blazer_oversized';
    }

    public function label(): string
    {
        return 'کت اورسایز';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 6,
            'armhole_depth_extra' => 6,
            'shoulder_slope' => 3,
            'neck_width_extra' => 3,
        ]);
    }
}
