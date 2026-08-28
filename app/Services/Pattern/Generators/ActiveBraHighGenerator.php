<?php

namespace App\Services\Pattern\Generators;

/** سوتینِ ورزشیِ پرفشار برای ورزشِ ضربه‌ای. */
class ActiveBraHighGenerator extends ActiveTopBaseGenerator
{
    public static function key(): string
    {
        return 'active_bra_high';
    }

    protected function active(): array
    {
        return [
            'prefix' => 'active_bra_high',
            'title' => 'سوتین ورزشی پرفشار',
            'use' => 'run',
            'stretch' => 0.78,
            'height' => 17,
            'back_drop' => 0,
            'strap' => 5,
            'armhole_lift' => 3,
            'armhole_narrow' => 3,
            'neck_depth_extra' => 5,
            'band' => true,
            'band_height' => 5.5,
            'band_ratio' => 0.78,
            'inner' => true,
            'notes' => ['نگه‌دارندهٔ اصلی نوار زیرسینه است، نه بندها؛ اگر بند فشار آورد نوار را کوتاه‌تر بگیر، نه بند را.'],
        ];
    }
}
