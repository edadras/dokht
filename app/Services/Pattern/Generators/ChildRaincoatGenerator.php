<?php

namespace App\Services\Pattern\Generators;

/** بارانیِ بچگانه: گشاد، کلاه‌دار، تا زیرِ باسن. */
class ChildRaincoatGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_raincoat';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_raincoat',
            'title' => 'بارانی بچگانه',
            'form' => 'top',
            'use' => 'outdoor',
            'play' => 2.5,
            'growth' => 2.5,
            'length' => 30,
            'length_max' => 70,
            'shape' => 'straight',
            'opening' => 'button',
            'buttons' => 5,
            'collar' => 'hood',
            'collar_height' => 6,
            'sleeve_length' => 44,
            'hem_flare' => 3,
            'schema' => ['armhole_depth_extra' => 5],
            'notes' => ['درزها باید نوارِ ضدآب بخورند؛ پارچهٔ روکش‌دار سوزنِ ریز می‌خواهد.'],
        ];
    }
}
