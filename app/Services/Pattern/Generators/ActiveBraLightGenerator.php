<?php

namespace App\Services\Pattern\Generators;

/** سوتینِ ورزشیِ کم‌فشار برای یوگا و پیاده‌روی. */
class ActiveBraLightGenerator extends ActiveTopBaseGenerator
{
    public static function key(): string
    {
        return 'active_bra_light';
    }

    protected function active(): array
    {
        return [
            'prefix' => 'active_bra_light',
            'title' => 'سوتین ورزشی سبک',
            'use' => 'yoga',
            'stretch' => 0.86,
            'height' => 15,
            'back_drop' => 0,
            'strap' => 4,
            'armhole_lift' => 2.5,
            'armhole_narrow' => 3,
            'neck_depth_extra' => 6,
            'band' => true,
            'band_height' => 4,
            'band_ratio' => 0.84,
        ];
    }
}
