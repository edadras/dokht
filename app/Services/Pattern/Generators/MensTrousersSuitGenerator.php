<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ کت‌وشلوار: جلو صاف، کمی باریک‌شونده، فاقِ متوسط و کمربندِ باریک. */
class MensTrousersSuitGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_trousers_suit';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_trousers_suit',
            'title' => 'شلوار کت‌وشلوار مردانه',
            'use' => 'formal',
            'rise' => 'mid',
            'thigh_ease' => 10,
            'knee_ease' => 7,
            'hem_ease' => 8,
            'hem_vs_knee' => -3.0,
            'front_waist' => 'none',
            'side_share' => 0.26,
            'band_height' => 3.5,
            'notes' => ['جلو صاف است تا خطِ اتو از کمر تا دمِ پا نشکند.'],
        ];
    }
}
