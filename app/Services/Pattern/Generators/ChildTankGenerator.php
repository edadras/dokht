<?php

namespace App\Services\Pattern\Generators;

/** رکابیِ بچگانه: کشباف، بی‌آستین، حلقهٔ باز. */
class ChildTankGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_tank';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_tank',
            'title' => 'رکابی بچگانه',
            'form' => 'top',
            'use' => 'play',
            'knit' => true,
            'play' => 1,
            'length' => 12,
            'shape' => 'straight',
            'opening' => 'closed',
            'sleeve' => 'none',
            'sleeve_length' => 0,
            'neck_band' => true,
            'schema' => ['armhole_depth_extra' => 3, 'neck_width_extra' => 3],
        ];
    }
}
