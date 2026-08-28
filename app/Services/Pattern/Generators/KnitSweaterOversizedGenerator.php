<?php

namespace App\Services\Pattern\Generators;

/** پلیورِ اورسایزِ سرشانه‌افتاده. */
class KnitSweaterOversizedGenerator extends HoodieSweatshirtGenerator
{
    public static function key(): string
    {
        return 'knit_sweater_oversized';
    }

    public function label(): string
    {
        return 'پلیور اورسایز';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 7,
            'armhole_depth_extra' => 7,
            'shoulder_slope' => 3,
        ]);
    }
}
