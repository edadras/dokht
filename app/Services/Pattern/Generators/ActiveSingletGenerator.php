<?php

namespace App\Services\Pattern\Generators;

/** رکابیِ دو و میدانی: حلقهٔ بسیار باز و بندِ باریک. */
class ActiveSingletGenerator extends ActiveTopBaseGenerator
{
    public static function key(): string
    {
        return 'active_singlet';
    }

    protected function active(): array
    {
        return [
            'prefix' => 'active_singlet',
            'title' => 'رکابی دو و میدانی',
            'use' => 'run',
            'stretch' => 0.94,
            'length' => 10,
            'back_drop' => 5,
            'strap' => 3,
            'armhole_lift' => 0,
            'armhole_narrow' => 4,
            'neck_width_extra' => 3,
            'neck_depth_extra' => 4,
            'back_neck_depth_extra' => 4,
            'notes' => ['حلقه عمداً بسیار باز است تا شانه در دویدن آزاد بماند؛ زیرِ آن سوتین ورزشی پوشیده می‌شود.'],
        ];
    }
}
