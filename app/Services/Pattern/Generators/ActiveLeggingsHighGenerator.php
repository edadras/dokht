<?php

namespace App\Services\Pattern\Generators;

/** تایتِ کمرِ بلند با نوارِ کمرِ پهن‌تر؛ روی شکم می‌ماند و پایین نمی‌رود. */
class ActiveLeggingsHighGenerator extends ActiveTightsGenerator
{
    public static function key(): string
    {
        return 'active_leggings_high';
    }

    public function label(): string
    {
        return 'تایت کمرِ بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'waistband_height' => 12,
            'band_stretch' => 0.86,
        ]);
    }
}
