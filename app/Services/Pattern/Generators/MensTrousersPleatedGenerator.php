<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ دو پیلیِ مردانه: کمرِ بلند، رانِ گشاد، دمِ پای راسته. */
class MensTrousersPleatedGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_trousers_pleated';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_trousers_pleated',
            'title' => 'شلوار پیلی‌دار مردانه',
            'use' => 'office',
            'rise' => 'high',
            'thigh_ease' => 17,
            'knee_ease' => 13,
            'hem_ease' => 14,
            'hem_vs_knee' => -2.0,
            'front_waist' => 'pleat',
            'pleat_count' => 2,
            'pleat_style' => 'knife',
            'side_share' => 0.22,
            'lean_share' => 0.1,
            'notes' => ['دو پیلی، اولی روی خطِ اتو و دومی چهار سانتی‌متر کنارِ آن.'],
        ];
    }
}
