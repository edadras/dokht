<?php

namespace App\Services\Pattern\Generators;

/** چینوی مردانه: جلو بی‌ساسون، پای کمی باریک‌شونده، کمربندِ چهار سانتی. */
class MensChinoGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_chino';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_chino',
            'title' => 'شلوار چینو مردانه',
            'use' => 'daily',
            'rise' => 'mid',
            'thigh_ease' => 11,
            'knee_ease' => 9,
            'hem_ease' => 11,
            'hem_vs_knee' => -4.0,
            'front_waist' => 'none',
            'shape' => ['scoop_front' => 0.58],
        ];
    }
}
