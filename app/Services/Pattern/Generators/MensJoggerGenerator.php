<?php

namespace App\Services\Pattern\Generators;

/** جاگرِ مردانه: کمرِ کشی، پای باریک‌شونده و مچِ کشیِ دمِ پا. */
class MensJoggerGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_jogger';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_jogger',
            'title' => 'شلوار جاگر مردانه',
            'use' => 'sport',
            'rise' => 'mid',
            'thigh_ease' => 14,
            'knee_ease' => 12,
            'hem_ease' => 13,
            'hem_vs_knee' => -9.0,
            'front_waist' => 'gather',
            'back_waist' => 'gather',
            'waist_balance' => 0.0,
            'band' => 'elastic',
            'band_stretch' => 0.85,
            'band_height' => 4.5,
        ];
    }
}
